<?php

namespace App\Ai\Speech;

use App\Ai\ModelCatalog;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Transcription;

/**
 * Transcribes speech with whichever engine is actually available.
 *
 * Whistle is preferred because it runs on the CPU, costs nothing per minute and
 * needs no key, but it is a native binary that may not be installed. The AI SDK
 * is the fallback and speaks to whichever provider config/ai.php resolves for the
 * `transcription` capability. Both return the same {@see Transcript} so callers
 * never branch on the engine.
 */
class Transcriber
{
    public function __construct(
        private readonly AudioNormalizer $audio,
        private readonly WhistleTranscriber $whistle,
        private readonly ModelCatalog $catalog,
    ) {}

    /**
     * Whether any configured provider can transcribe.
     *
     * This is checked up front so the composer can say "no transcription provider
     * is configured" before the user records anything, rather than failing on the
     * first clip with a provider-level error.
     */
    public function sdkAvailable(): bool
    {
        foreach (array_keys((array) config('ai.providers', [])) as $provider) {
            if ($this->catalog->configuredModel((string) $provider, 'transcription') !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * The engines that could serve a request right now.
     *
     * @return list<string>
     */
    public function availableEngines(): array
    {
        return array_values(array_filter(
            ['whistle', 'sdk'],
            fn (string $engine): bool => match ($engine) {
                'whistle' => $this->whistle->available(),
                'sdk' => $this->sdkAvailable(),
                default => false,
            },
        ));
    }

    /**
     * The engine that would be used for the next request.
     */
    public function engine(): string
    {
        $preferred = (string) config('whale.speech.engine', 'auto');
        $available = $this->availableEngines();

        if ($preferred !== 'auto') {
            return in_array($preferred, $available, true) ? $preferred : 'none';
        }

        return $available[0] ?? 'none';
    }

    /**
     * Transcribe an uploaded clip.
     *
     * @param  array{provider?: string|null, model?: string|null, language?: string|null}  $options
     *
     * @throws \RuntimeException when no engine can serve the request.
     */
    public function transcribe(UploadedFile $audio, array $options = []): Transcript
    {
        $engine = $options['engine'] ?? $this->engine();

        if ($engine === 'none') {
            throw new \RuntimeException(
                'No speech-to-text engine is available. Install Whistle (`needle download whistle`, then set WHISTLE_BINARY) or configure a transcription provider.',
            );
        }

        $language = $options['language'] ?? null;

        // A WAV is handed straight to the engine. Anything else has to be
        // converted first, which needs FFmpeg.
        $wav = $this->audio->toWav($audio, $language);

        try {
            return match ($engine) {
                'whistle' => $this->whistle->transcribe($wav, $this->keywords()),
                default => $this->viaSdk($wav, $options),
            };
        } finally {
            // The converted clip is scratch data and must not outlive the request.
            @unlink($wav);
        }
    }

    /**
     * Transcribe through whichever provider the SDK resolves.
     *
     * @param  array<string, mixed>  $options
     */
    private function viaSdk(string $wav, array $options): Transcript
    {
        $pending = Transcription::fromPath($wav, 'audio/wav')->timeout(120);

        if (filled($options['language'] ?? null)) {
            $pending = $pending->language((string) $options['language']);
        }

        $response = $pending->generate($options['provider'] ?? null, $options['model'] ?? null);

        return new Transcript(
            text: trim((string) $response->text),
            engine: 'sdk',
            language: $options['language'] ?? null,
            words: $response->segments
                ->map(fn ($segment): array => [
                    'text' => trim((string) $segment->text),
                    'start' => (float) $segment->startSeconds,
                    'end' => (float) $segment->endSeconds,
                    'confidence' => null,
                ])
                ->values()
                ->all(),
            durationSeconds: $this->audio->duration($wav),
        );
    }

    /**
     * The terms Whisley's decoder is biased towards.
     *
     * @return list<string>
     */
    private function keywords(): array
    {
        return (array) config('whale.speech.keywords', []);
    }
}
