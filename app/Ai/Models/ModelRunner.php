<?php

namespace App\Ai\Models;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Runs a local model file by delegating to whichever runtime is serving it.
 *
 * This class deliberately never loads a GGUF file. Multi-gigabyte weights cannot
 * be executed from PHP, and a process that tried would either exhaust memory or
 * silently return nonsense. Instead every call is forwarded to an HTTP runtime
 * that already has the model loaded: llama.cpp's OpenAI-compatible server for
 * text and vision, or a Python image server for diffusion models.
 *
 * That delegation has one hard requirement, enforced here: a runtime is only
 * reported as healthy once it has answered a bounded probe. A URL being present
 * in configuration proves nothing, so {@see self::health()} asks the endpoint
 * what it can actually serve.
 */
class ModelRunner
{
    /**
     * How long a health probe may take before the runtime counts as unreachable.
     */
    private const HEALTH_TIMEOUT = 3;

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * Ask a runtime what it can actually serve.
     *
     * This replaces a "is a URL configured?" guess with a bounded request, so
     * the settings screen can tell a running runtime from a dead one, and from
     * one that is running but holding a different model.
     *
     * @return array{status: string, detail: string|null, models: list<string>}
     */
    public function health(string $runtime): array
    {
        $configuration = config("whale.runtimes.{$runtime}");

        if (! is_array($configuration)) {
            return $this->status('unknown', "No runtime named [{$runtime}] is configured.");
        }

        $url = $configuration['url'] ?? null;

        if (! is_string($url) || trim($url) === '') {
            return $this->status('unconfigured', $this->hint($runtime));
        }

        try {
            $response = $this->request($runtime)->timeout(self::HEALTH_TIMEOUT)->get($this->modelsUrl($url));
        } catch (ConnectionException) {
            return $this->status('unreachable', "No response from {$url}.");
        } catch (Throwable $e) {
            Log::debug("Local runtime [{$runtime}] health probe failed: {$e->getMessage()}");

            return $this->status('unreachable', $e->getMessage());
        }

        if (! $response->successful()) {
            return $this->status('unreachable', "{$url} returned HTTP {$response->status()}.");
        }

        return $this->status('online', null, $this->servedModels($response));
    }

    /**
     * Every runtime's health, keyed by runtime name.
     *
     * @return array<string, array{status: string, detail: string|null, models: list<string>}>
     */
    public function healthForAll(): array
    {
        $health = [];

        foreach (array_keys((array) config('whale.runtimes', [])) as $runtime) {
            $health[$runtime] = $this->health($runtime);
        }

        return $health;
    }

    /**
     * Health for just the named runtimes, in the order they were requested.
     *
     * Unknown names are reported as unknown rather than dropped, so a caller
     * asking about a runtime that has since been removed from configuration
     * gets an answer instead of a silent gap in the list.
     *
     * @param  list<string>  $runtimes
     * @return array<string, array{status: string, detail: string|null, models: list<string>}>
     */
    public function healthFor(array $runtimes): array
    {
        $health = [];

        foreach ($runtimes as $runtime) {
            $health[$runtime] = $this->health($runtime);
        }

        return $health;
    }

    /**
     * Stream a chat completion from the runtime serving this model.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return \Generator<int, string> Plain text deltas, in order.
     *
     * @throws RuntimeException when the model has no runtime, or the runtime refuses.
     */
    public function stream(LocalModel $model, array $messages, array $options = []): \Generator
    {
        $runtime = $model->runtime();
        $name = $runtime['name'];

        if ($name === 'none') {
            throw new RuntimeException(sprintf(
                'No configured runtime supports %s. %s',
                implode('+', $model->capabilities()),
                $this->hintForCapabilities($model),
            ));
        }

        $url = (string) config("whale.runtimes.{$name}.url");

        if (trim($url) === '') {
            throw new RuntimeException("The [{$name}] runtime has no URL configured.");
        }

        $payload = [
            'model' => $options['model'] ?? $model->fileName,
            'messages' => $messages,
            'stream' => true,
        ];

        // Only drop the sampling options that were genuinely not set. An empty
        // messages array must survive: filtering it out would send a malformed
        // request and hand back a runtime error instead of the real problem.
        foreach (['temperature' => null, 'max_tokens' => null, 'top_p' => null] as $option => $default) {
            $value = $options[$option] ?? $default;

            if ($value !== null) {
                $payload[$option] = $value;
            }
        }

        try {
            $response = $this->request($name)
                ->timeout((int) ($options['timeout'] ?? 120))
                ->withOptions(['stream' => true])
                ->post($this->chatUrl($url), $payload);
        } catch (Throwable $e) {
            throw new RuntimeException("Could not reach the [{$name}] runtime: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->describeFailure($response));
        }

        yield from $this->deltas($response->body());
    }

    /**
     * Stream a completion for a model file identified by name or id.
     *
     * This is what the chat layer calls for the "local" provider: it resolves
     * the file through the registry and delegates to the runtime serving it, so
     * a selected local model reaches llama.cpp instead of being handed to the
     * AI SDK as though it were a hosted provider.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return \Generator<int, string>
     *
     * @throws RuntimeException when the file is unknown, has no runtime, or the runtime refuses.
     */
    public function streamLocal(LocalModelRegistry $registry, string $identifier, array $messages, array $options = []): \Generator
    {
        $model = $registry->find($identifier);

        if ($model === null) {
            throw new RuntimeException(sprintf(
                'No local model file named [%s] was found in %s.',
                $identifier,
                config('whale.models_path'),
            ));
        }

        yield from $this->stream($model, $messages, $options);
    }

    /**
     * The endpoint that lists what a runtime is serving.
     */
    private function modelsUrl(string $url): string
    {
        return $this->join($url, (string) config('whale.runtimes_paths.models_path', '/v1/models'));
    }

    /**
     * The endpoint that accepts chat completions.
     */
    private function chatUrl(string $url): string
    {
        return $this->join($url, (string) config('whale.runtimes_paths.chat_path', '/v1/chat/completions'));
    }

    /**
     * Build a request for a runtime, attaching any credentials it needs.
     */
    private function request(string $runtime): PendingRequest
    {
        $request = $this->http->acceptJson()->asJson();
        $apiKey = config("whale.runtimes.{$runtime}.api_key");

        return is_string($apiKey) && $apiKey !== '' ? $request->withToken($apiKey) : $request;
    }

    /**
     * Pull text out of an OpenAI-compatible streaming response.
     *
     * The body is Server-Sent Events: one `data:` line per chunk, ended by a
     * `data: [DONE]` sentinel. Unparseable frames are skipped rather than
     * surfaced, because one stray keep-alive line should not truncate a reply
     * the user is watching.
     *
     * @return \Generator<int, string>
     */
    private function deltas(string $body): \Generator
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));

            if ($payload === '[DONE]') {
                break;
            }

            if ($payload === '') {
                continue;
            }

            $decoded = json_decode($payload, true);
            $delta = is_array($decoded) ? ($decoded['choices'][0]['delta']['content'] ?? null) : null;

            if (is_string($delta) && $delta !== '') {
                yield $delta;
            }
        }
    }

    /**
     * Turn an error response into something worth showing a user.
     */
    private function describeFailure(Response $response): string
    {
        $error = $response->json('error.message') ?? $response->json('message');

        return is_string($error) && $error !== ''
            ? $error
            : "The runtime returned HTTP {$response->status()}.";
    }

    /**
     * The model names a runtime advertises, when it advertises any.
     *
     * @return list<string>
     */
    private function servedModels(Response $response): array
    {
        $models = $response->json('data');

        if (! is_array($models)) {
            return [];
        }

        return collect($models)
            ->map(fn (mixed $model): mixed => is_array($model) ? ($model['id'] ?? null) : $model)
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            // Runtimes report names like "qwen2:latest"; a file name is used here.
            ->map(fn (string $id): string => Str::afterLast($id, '/'))
            ->values()
            ->all();
    }

    /**
     * The advice shown when a runtime has no URL configured.
     */
    private function hint(string $runtime): string
    {
        return match ($runtime) {
            'ollama' => 'Set OLLAMA_URL, then run `ollama serve`.',
            'llama_cpp' => 'Set LOCAL_LLM_URL, then run `llama-server --model <file.gguf> --port 8080`.',
            'diffusers' => 'Set LOCAL_IMAGE_URL to a running diffusers or ComfyUI server.',
            default => "Configure a URL for the [{$runtime}] runtime.",
        };
    }

    /**
     * The advice shown when nothing can serve a model's capabilities.
     */
    private function hintForCapabilities(LocalModel $model): string
    {
        return in_array('image', $model->capabilities(), true)
            ? 'Image models need a Python runtime such as diffusers or ComfyUI; llama.cpp cannot serve them.'
            : 'Start a runtime that supports these capabilities and set its URL.';
    }

    /**
     * @return array{status: string, detail: string|null, models: list<string>}
     */
    private function status(string $status, ?string $detail, array $models = []): array
    {
        return ['status' => $status, 'detail' => $detail, 'models' => $models];
    }

    /**
     * Join a runtime's base URL with one of its paths, tolerating a trailing slash.
     */
    private function join(string $url, string $path): string
    {
        return rtrim($url, '/').'/'.ltrim($path, '/');
    }
}
