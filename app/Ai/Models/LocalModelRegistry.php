<?php

namespace App\Ai\Models;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Discovers the local model files dropped into the models directory.
 *
 * Detection is deliberately read-only: the application never loads, executes
 * or modifies a model. It reads each file's header to work out what the model
 * is and what it can do, then hands that to the catalog so the model picker
 * and settings view describe reality instead of guessing.
 *
 * Both GGUF and safetensors are read. They describe a model the same way once
 * their headers are parsed, so they are profiled into the same LocalModel
 * hierarchy and are indistinguishable to everything downstream except for the
 * format they report.
 */
class LocalModelRegistry
{
    /**
     * The file extensions treated as model files, mapped to the parser that
     * reads them.
     *
     * @var array<string, class-string>
     */
    private const PARSERS = [
        'gguf' => GgufParser::class,
        'safetensors' => SafetensorsParser::class,
    ];

    /**
     * Extensions a browser leaves behind for a download that has not finished.
     *
     * @var list<string>
     */
    private const PARTIAL_DOWNLOADS = ['crdownload', 'part', 'partial', 'download', 'tmp', 'temp'];

    public function __construct(
        private readonly Filesystem $files,
        private readonly GgufParser $parser,
        private readonly Cache $cache,
        private readonly SafetensorsParser $safetensorsParser,
    ) {}

    /**
     * Every model file found, keyed by its file name.
     *
     * @return Collection<string, LocalModel>
     */
    public function models(): Collection
    {
        $path = $this->path();

        if (! $this->files->isDirectory($path)) {
            return collect();
        }

        return collect($this->files->files($path))
            ->filter(fn (\SplFileInfo $file): bool => $this->isModelFile($file))
            ->sortBy(fn (\SplFileInfo $file): int => $file->getMTime())
            ->mapWithKeys(fn (\SplFileInfo $file): array => [$file->getFilename() => $this->profile($file)])
            ->filter();
    }

    /**
     * The detected models as plain arrays for the browser.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->models()
            ->map(fn (LocalModel $model): array => $model->toArray())
            ->values()
            ->all();
    }

    /**
     * Find one detected model by its file name or id.
     */
    public function find(string $identifier): ?LocalModel
    {
        return $this->models()->get($identifier)
            ?? $this->models()->first(fn (LocalModel $model): bool => $model->id === $identifier);
    }

    /**
     * Files in the models directory that are not usable models.
     *
     * @return list<array{file: string, size_bytes: int, reason: array{message: string}}>
     */
    public function unreadable(): array
    {
        $path = $this->path();

        if (! $this->files->isDirectory($path)) {
            return [];
        }

        return collect($this->files->files($path))
            ->filter(fn (\SplFileInfo $file): bool => $this->isModelFile($file) || $this->isPartialDownload($file))
            ->map(fn (\SplFileInfo $file): array => [
                'file' => $file->getFilename(),
                'size_bytes' => $file->getSize(),
                'reason' => $this->isPartialDownload($file)
                    ? ['message' => 'Download incomplete; this is not a usable model yet.']
                    : $this->reasonFor($file),
            ])
            // A file that parsed cleanly has no reason to report, so it is not
            // unreadable and must not be listed here.
            ->filter(fn (array $entry): bool => $entry['reason'] !== null)
            ->values()
            ->all();
    }

    /**
     * The directory being watched.
     */
    public function path(): string
    {
        return (string) config('whale.models_path', base_path('app/Ai/Models'));
    }

    /**
     * Whether a file is one this registry knows how to read.
     *
     * Dotfiles are skipped: a partially written ".model.gguf.swp" or an editor's
     * lock file shares the extension of a real model but is not one, and trying
     * to parse it would report a working model as broken.
     */
    private function isModelFile(\SplFileInfo $file): bool
    {
        return isset(self::PARSERS[strtolower($file->getExtension())])
            && ! str_starts_with($file->getBasename(), '.');
    }

    /**
     * Whether a file is a download that never finished.
     *
     * Browser downloads land in the models directory under a temporary name
     * with the real one on the end, so "model.gguf.crdownload" is a gigabyte of
     * weights that will never be parseable until the download completes. It is
     * listed rather than ignored, because silently skipping it makes a user
     * think the detector failed to see the file they just saved here.
     */
    private function isPartialDownload(\SplFileInfo $file): bool
    {
        return in_array(strtolower($file->getExtension()), self::PARTIAL_DOWNLOADS, true)
            && ! str_starts_with($file->getBasename(), '.');
    }

    /**
     * The parser that reads a file of this format.
     */
    private function parserFor(\SplFileInfo $file): GgufParser|SafetensorsParser
    {
        return match (strtolower($file->getExtension())) {
            'safetensors' => $this->safetensorsParser,
            default => $this->parser,
        };
    }

    /**
     * Read a model file's header, caching the result against its fingerprint.
     */
    private function profile(\SplFileInfo $file): ?LocalModel
    {
        $path = $file->getPathname();
        $cacheKey = 'whale.model.'.md5($path);
        $cached = $this->cached($cacheKey, $file);

        if (is_array($cached) && ($cached['model'] ?? null) instanceof LocalModel) {
            return $cached['model'];
        }

        try {
            $parsed = $this->parserFor($file)->parse($path);
        } catch (Throwable $e) {
            Log::debug("Skipping unreadable local model [{$file->getFilename()}]: {$e->getMessage()}");

            return null;
        }

        $model = strtolower($file->getExtension()) === 'safetensors'
            ? new SafetensorsModel(
                id: $file->getFilename(),
                path: $path,
                fileName: $file->getFilename(),
                sizeBytes: (int) $file->getSize(),
                version: $parsed['version'],
                tensorCount: $parsed['tensor_count'],
                metadata: $parsed['metadata'],
                tensors: $parsed['tensors'],
            )
            : new GgufModel(
                id: $file->getFilename(),
                path: $path,
                fileName: $file->getFilename(),
                sizeBytes: (int) $file->getSize(),
                version: $parsed['version'],
                tensorCount: $parsed['tensor_count'],
                metadata: $parsed['metadata'],
                tensors: $parsed['tensors'],
            );

        // Only successes are cached. Caching a failure would hide a working model for
        // the whole TTL: a file's size and mtime do not change when the parser is
        // fixed or when a read fails transiently, so nothing would ever retry it.
        $this->cache->put(
            $cacheKey,
            ['fingerprint' => $this->fingerprint($file), 'model' => $model],
            (int) config('whale.cache.ttl', 3600),
        );

        return $model;
    }

    /**
     * The cached entry for a file, or null when it is stale or absent.
     *
     * @return array<string, mixed>|null
     */
    private function cached(string $cacheKey, \SplFileInfo $file): ?array
    {
        $cached = $this->cache->get($cacheKey);

        if (! is_array($cached) || ($cached['fingerprint'] ?? null) !== $this->fingerprint($file)) {
            return null;
        }

        return $cached;
    }

    /**
     * What identifies a file's contents for caching purposes.
     */
    private function fingerprint(\SplFileInfo $file): string
    {
        return $file->getSize().':'.$file->getMTime();
    }

    /**
     * Explain why a file in the models directory is not a usable model.
     *
     * @return array<string, mixed>|null
     */
    private function reasonFor(\SplFileInfo $file): ?array
    {
        $extension = strtolower($file->getExtension());

        if (! isset(self::PARSERS[$extension])) {
            return null;
        }

        $cacheKey = 'whale.model.error.'.md5($file->getPathname());
        $cached = $this->cached($cacheKey, $file);

        if (is_array($cached) && array_key_exists('reason', $cached)) {
            return $cached['reason'];
        }

        try {
            $this->parserFor($file)->parse($file->getPathname());
            $reason = null;
        } catch (Throwable $e) {
            $reason = ['message' => $e->getMessage()];
        }

        // As with profile(), only a real failure is worth remembering. Caching a
        // clean parse here would report a working model as broken for the TTL.
        if ($reason !== null) {
            $this->cache->put(
                $cacheKey,
                ['fingerprint' => $this->fingerprint($file), 'reason' => $reason],
                (int) config('whale.cache.ttl', 3600),
            );
        }

        return $reason;
    }
}
