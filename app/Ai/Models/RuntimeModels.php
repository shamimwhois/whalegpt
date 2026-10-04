<?php

namespace App\Ai\Models;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Discovers the models a local runtime has actually pulled.
 *
 * Ollama keeps its own model store, so the usable set is whatever the user has
 * run `ollama pull` for. Reading that from configuration would need one env var
 * per model, and only the last would survive, so the list is asked for over the
 * API instead and cached briefly.
 *
 * Every method degrades to an empty list rather than throwing: a runtime that is
 * down is a normal state for a local tool, not an error to surface in settings.
 */
class RuntimeModels
{
    /**
     * How long a discovery result is reused before asking the runtime again.
     */
    private const TTL = 300;

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * The model names a runtime reports, without its own prefixes.
     *
     * @return list<string>
     */
    public function for(string $runtime): array
    {
        $url = config("whale.runtimes.{$runtime}.url");

        if (! is_string($url) || trim($url) === '') {
            return [];
        }

        return cache()->remember(
            "whale.runtime.{$runtime}.models",
            self::TTL,
            fn (): array => $this->fetch($url),
        );
    }

    /**
     * Forget a runtime's cached list, so the next read asks it again.
     */
    public function flush(string $runtime): void
    {
        cache()->forget("whale.runtime.{$runtime}.models");
    }

    /**
     * Ask the runtime what it is serving.
     *
     * @return list<string>
     */
    private function fetch(string $url): array
    {
        $path = (string) config('whale.runtimes_paths.models_path', '/v1/models');
        $endpoint = rtrim($url, '/').'/'.ltrim($path, '/');

        try {
            $response = $this->http->acceptJson()->timeout(3)->get($endpoint);
        } catch (ConnectionException) {
            return [];
        } catch (Throwable $e) {
            Log::debug("Could not list runtime models from {$url}: {$e->getMessage()}");

            return [];
        }

        return $response->successful() ? $this->names($response) : [];
    }

    /**
     * Pull model names out of an OpenAI-compatible listing.
     *
     * @return list<string>
     */
    private function names(Response $response): array
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return [];
        }

        return collect($data)
            ->map(fn (mixed $model): mixed => is_array($model) ? ($model['id'] ?? null) : $model)
            ->filter(fn (mixed $id): bool => is_string($id) && trim($id) !== '')
            // Keep the bare name so "library/qwen3:4b" and "qwen3:4b" collapse.
            ->map(fn (string $id): string => Str::afterLast(trim($id), '/'))
            ->unique()
            ->values()
            ->all();
    }
}
