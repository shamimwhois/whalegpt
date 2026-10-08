<?php

namespace App\Ai\Speech;

use RuntimeException;

/**
 * Transcribes audio with Cactus Compute's Whistle model.
 *
 * Whistle is a 16.9 MB speech-to-text model that runs on the CPU through Cactus
 * Compute's `needle` binary. It is deliberately not wired in as a PHP package:
 * the engine ships as a native executable, so it is invoked as a subprocess and
 * its JSON is read back. That keeps a heavy native dependency optional — if the
 * binary is not installed, this engine simply reports itself unavailable and the
 * caller falls back to a hosted provider.
 *
 * The binary is passed arguments rather than a shell string, so a filename
 * containing a space or a quote cannot alter the command.
 */
class WhistleTranscriber
{
    /**
     * Whether the binary and its weights are actually present and runnable.
     */
    public function available(): bool
    {
        return filled($this->binary()) && is_file($this->binary());
    }

    /**
     * The path to the needle binary, if one is configured.
     */
    public function binary(): ?string
    {
        $binary = config('whale.speech.binary');

        return is_string($binary) && trim($binary) !== '' ? $binary : null;
    }

    /**
     * Transcribe a WAV file on disk.
     *
     * @param  list<string>  $keywords  Terms to bias the decoder towards.
     *
     * @throws RuntimeException when Whistle is not installed or rejects the clip.
     */
    public function transcribe(string $path, array $keywords = []): Transcript
    {
        if (! $this->available()) {
            throw new RuntimeException('The Whistle binary is not installed. Run `needle download whistle` and set WHISTLE_BINARY.');
        }

        $arguments = [
            $this->binary(),
            '--model', (string) config('whale.speech.model', 'whistle.cact'),
            '--audio', $path,
            '--audio-word-timestamps',
        ];

        // Only pin a language when asked; otherwise Whistle detects it.
        if (filled($language = config('whale.speech.language')) && $language !== 'auto') {
            $arguments[] = '--audio-language';
            $arguments[] = $language;
        }

        if ($keywords !== []) {
            $arguments[] = '--audio-keywords';
            $arguments[] = implode(',', $keywords);
        }

        return $this->parse($this->run($arguments), $path);
    }

    /**
     * Run the binary and return its stdout.
     *
     * proc_open() is used with an argument array rather than a shell string, so
     * a path containing a space, a quote or a semicolon cannot inject another
     * command. The child's stderr is folded into stdout so a diagnostic is never
     * silently discarded.
     *
     * @param  list<string>  $arguments
     */
    private function run(array $arguments): string
    {
        $pipes = [];
        $process = proc_open(
            $arguments,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname((string) $this->binary()),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('The Whistle binary could not be started.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($process);
        $output = trim($stdout) !== '' ? $stdout : $stderr;

        if ($status !== 0) {
            throw new RuntimeException('Whistle failed: '.trim($output !== '' ? $output : 'no output'));
        }

        return $output;
    }

    /**
     * Read Whistle's JSON output.
     *
     * The binary prints a JSON object; some builds wrap it in extra progress
     * lines, so the last JSON line is taken rather than the whole buffer.
     */
    private function parse(string $output, string $path): Transcript
    {
        $decoded = null;

        foreach (array_reverse(preg_split('/\R/', trim($output)) ?: []) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_starts_with($line, '{')) {
                continue;
            }

            $candidate = json_decode($line, true);

            if (is_array($candidate)) {
                $decoded = $candidate;
                break;
            }
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Whistle returned no readable transcript.');
        }

        $text = $decoded['text'] ?? $decoded['audio_text'] ?? '';
        $words = $this->words($decoded);

        return new Transcript(
            text: is_string($text) ? trim($text) : '',
            engine: 'whistle',
            language: isset($decoded['language']) && is_string($decoded['language']) ? $decoded['language'] : null,
            words: $words,
            durationSeconds: $words === []
                ? 0.0
                : max(array_column($words, 'end')),
        );
    }

    /**
     * Normalise Whistle's word timestamps, which are reported in milliseconds.
     *
     * @param  array<string, mixed>  $decoded
     * @return list<array{text: string, start: float, end: float, confidence: float|null}>
     */
    private function words(array $decoded): array
    {
        $raw = $decoded['word_timestamps'] ?? $decoded['words'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->filter(fn (mixed $word): bool => is_array($word) && filled($word['word'] ?? $word['text'] ?? null))
            ->map(function (array $word): array {
                $confidence = $word['probability'] ?? $word['confidence'] ?? null;

                return [
                    'text' => (string) ($word['word'] ?? $word['text']),
                    'start' => round(((float) ($word['start'] ?? 0)) / 1000, 3),
                    'end' => round(((float) ($word['end'] ?? 0)) / 1000, 3),
                    'confidence' => is_numeric($confidence) ? round((float) $confidence, 4) : null,
                ];
            })
            ->values()
            ->all();
    }
}
