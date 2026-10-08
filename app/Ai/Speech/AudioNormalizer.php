<?php

namespace App\Ai\Speech;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Turns whatever the browser recorded into the audio a speech model expects.
 *
 * A browser hands over WebM or Opus in a container chosen for streaming, usually
 * 48 or 44.1 kHz. Whistle wants 16 kHz mono PCM in a WAV, and most hosted
 * transcribers want a format they can read directly. Rather than guess, this
 * class decodes the container to raw PCM, resamples it, and writes a plain WAV
 * that every engine accepts.
 *
 * Decoding uses FFmpeg when it is installed. When it is not, an already-WAV
 * upload is passed through untouched so a correctly recorded file still works.
 */
class AudioNormalizer
{
    /**
     * Memoised FFmpeg lookup, keyed so a miss is not re-run on every call.
     *
     * @var array<string, string|null>
     */
    private array $resolved = [];

    /**
     * The target sample rate for speech recognition.
     */
    private function targetRate(): int
    {
        return (int) config('whale.speech.sample_rate', 16000);
    }

    /**
     * Whether real transcoding is possible on this machine.
     */
    public function canConvert(): bool
    {
        return filled($this->ffmpeg());
    }

    /**
     * Path to an FFmpeg binary, if one is installed.
     *
     * FFmpeg is normally on PATH rather than in a known location, so it is
     * located once and the answer cached for the life of the request.
     */
    public function ffmpeg(): ?string
    {
        if (array_key_exists('resolved', $this->resolved)) {
            return $this->resolved['resolved'];
        }

        return $this->resolved['resolved'] = $this->locateFfmpeg();
    }

    /**
     * Find an FFmpeg binary by asking the shell which one it would run.
     */
    private function locateFfmpeg(): ?string
    {
        foreach (['ffmpeg', 'ffmpeg.exe'] as $name) {
            $pipes = [];
            $process = @proc_open(
                [$name, '-version'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );

            if (! is_resource($process)) {
                continue;
            }

            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            if (proc_close($process) === 0) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Normalise an uploaded clip to 16 kHz mono WAV and return its path.
     *
     * @param  string|null  $language  ISO-639-1 hint passed through to the engine.
     */
    public function toWav(UploadedFile $file, ?string $language = null): string
    {
        $target = $this->targetRate();

        // A WAV that is already mono at the target rate needs no conversion, so
        // it survives even on a machine without FFmpeg.
        if ($this->isUsableWav($file)) {
            $path = $this->temporaryPath('wav');
            $file->move(dirname($path), basename($path));

            return $path;
        }

        if (! $this->canConvert()) {
            throw new FileException('Audio conversion needs FFmpeg installed. Install it and try again.');
        }

        $source = $file->getRealPath() ?: $file->getPathname();
        $path = $this->temporaryPath('wav');

        $arguments = [
            $this->ffmpeg(),
            '-hide_banner',
            '-loglevel', 'error',
            '-i', $source,
            '-vn',
            '-ac', '1',
            '-ar', (string) $target,
            '-c:a', 'pcm_s16le',
            '-f', 'wav',
            $path,
        ];

        $pipes = [];
        $process = proc_open($arguments, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            throw new FileException('FFmpeg could not be started.');
        }

        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0 || ! is_file($path)) {
            @unlink($path);

            throw new FileException('The recording could not be converted: '.trim($error ?: 'unknown error'));
        }

        return $path;
    }

    /**
     * How long a WAV lasts, in seconds.
     *
     * Read from the header rather than by decoding, so a duration can be shown
     * before a transcription is requested.
     */
    public function duration(string $wavPath): float
    {
        $handle = @fopen($wavPath, 'rb');

        if ($handle === false) {
            return 0.0;
        }

        $header = (string) fread($handle, 64);
        fclose($handle);

        // The data chunk header sits at a fixed offset for a canonical WAV.
        if (! Str::startsWith($header, 'RIFF') || strlen($header) < 44) {
            return 0.0;
        }

        $byteRate = unpack('V', substr($header, 28, 4))[1] ?? 0;

        if ($byteRate <= 0 || ! is_file($wavPath)) {
            return 0.0;
        }

        return round((filesize($wavPath) - 44) / $byteRate, 3);
    }

    /**
     * Whether the upload is already a WAV this class can use as-is.
     */
    private function isUsableWav(UploadedFile $file): bool
    {
        if (strtolower((string) $file->getClientOriginalExtension()) !== 'wav') {
            return false;
        }

        $handle = @fopen($file->getPathname(), 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = (string) fread($handle, 12);
        fclose($handle);

        // RIFF....WAVE, and the format chunk must declare 16-bit mono PCM.
        return Str::startsWith($magic, 'RIFF') && Str::contains($magic, 'WAVE');
    }

    /**
     * A unique path in the system temp directory.
     */
    private function temporaryPath(string $extension): string
    {
        return sprintf(
            '%s%s/whale-audio-%s.%s',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            Str::random(32),
            $extension,
        );
    }
}
