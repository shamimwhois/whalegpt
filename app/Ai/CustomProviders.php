<?php

namespace App\Ai;

use Illuminate\Support\Str;

/**
 * The additional OpenAI-compatible endpoints this deployment declares.
 *
 * Any number of them can be added through a single environment variable, so an
 * installation can talk to LM Studio, vLLM, a gateway or a self-hosted proxy
 * without a new variable per provider:
 *
 *   WHALE_CUSTOM_PROVIDERS='[
 *     {"name":"lmstudio","url":"http://127.0.0.1:1234/v1","key":""},
 *     {"name":"gateway","url":"https://ai.example.com/v1","key":"sk-..."}
 *   ]'
 *
 * Each entry is merged into config('ai.providers'), so a custom provider is
 * resolved by name anywhere the SDK accepts a built-in provider name.
 *
 * An entry that declares an image model is resolved through this application's
 * own image-capable driver rather than the SDK's compatible one, which serves
 * no images at all. An entry may also name "driver" explicitly to override that.
 *
 * Parsing is forgiving on purpose. Malformed JSON, a missing URL or a duplicate
 * name is a typo in .env, and taking down every request that reads the model
 * catalog would be a far worse outcome than quietly serving the providers that
 * did parse.
 */
class CustomProviders
{
    /**
     * Provider names the SDK already resolves.
     *
     * A custom provider may not take one of these: silently replacing `openai`
     * with a local endpoint because two words collided would be much harder to
     * diagnose than a refused entry.
     *
     * `openai-image` is listed even though the SDK does not ship it, because
     * this application registers that driver itself. Declaring an endpoint under
     * that name would be a collision with our own driver, not with the SDK's.
     *
     * @var list<string>
     */
    public const RESERVED_NAMES = [
        'anthropic',
        'azure',
        'bedrock',
        'cohere',
        'deepseek',
        'eleven',
        'gemini',
        'groq',
        'jina',
        'mistral',
        'ollama',
        'openai',
        'openai-compatible',
        'openai-image',
        'openrouter',
        'typesafe',
        'voyageai',
        'xai',
    ];

    /**
     * The driver an endpoint is resolved through when it serves no images.
     *
     * The SDK's own compatible driver covers text, embeddings and
     * transcription, which is what most local runtimes and gateways speak.
     *
     * @var string
     */
    public const DEFAULT_DRIVER = 'openai-compatible';

    /**
     * The driver an endpoint is resolved through when it does serve images.
     *
     * The SDK's compatible driver does not implement ImageProvider, so an
     * endpoint declared with an image model has to be resolved through one that
     * does. See App\Ai\OpenAiImageProvider.
     *
     * @var string
     */
    public const IMAGE_DRIVER = 'openai-image';

    /**
     * The capabilities a custom provider may name a default model for. These
     * are the ones the OpenAI-compatible driver can actually serve.
     *
     * @var list<string>
     */
    public const CAPABILITIES = [
        'text',
        'image',
        'audio',
        'transcription',
        'embeddings',
        'reranking',
    ];

    /**
     * The providers declared in the environment, in declaration order.
     *
     * @return list<array{
     *     name: string,
     *     label: string,
     *     url: string,
     *     key: string|null,
     *     driver: string|null,
     *     headers: array<string, string>,
     *     models: array<string, array{default: string}>
     * }>
     */
    public function all(): array
    {
        return $this->parse(config('whale.custom_providers'));
    }

    /**
     * The providers declared under the given names, for a lookup that would
     * otherwise have to scan the whole list.
     *
     * @param  list<string>  $names
     * @return array<string, array<string, mixed>>
     */
    public function only(array $names): array
    {
        return collect($this->all())
            ->filter(fn (array $provider): bool => in_array($provider['name'], $names, true))
            ->keyBy('name')
            ->all();
    }

    /**
     * Whether the given name belongs to a declared custom provider.
     */
    public function isCustom(string $name): bool
    {
        return collect($this->all())->contains(fn (array $provider): bool => $provider['name'] === $name);
    }

    /**
     * Normalise the JSON blob held in the environment.
     *
     * @return list<array{
     *     name: string,
     *     label: string,
     *     url: string,
     *     key: string|null,
     *     driver: string|null,
     *     headers: array<string, string>,
     *     models: array<string, array{default: string}>
     * }>
     */
    public function parse(?string $json): array
    {
        if (blank($json)) {
            return [];
        }

        $entries = json_decode($json, true);

        if (! is_array($entries)) {
            return [];
        }

        $providers = [];

        foreach ($entries as $entry) {
            $provider = $this->normalise(is_array($entry) ? $entry : []);

            // The first declaration of a name wins. Letting a later duplicate
            // overwrite it would make the outcome depend on list order.
            if ($provider === null || isset($providers[$provider['name']])) {
                continue;
            }

            $providers[$provider['name']] = $provider;
        }

        return array_values($providers);
    }

    /**
     * The config/ai.php shape for one custom provider.
     *
     * The driver follows from what the endpoint is declared to serve. An entry
     * with an image model is resolved through the image-capable driver, because
     * the SDK's compatible driver implements no ImageProvider and would accept
     * the selection and then fail the request.
     *
     * @param  array<string, mixed>  $provider
     * @return array<string, mixed>
     */
    public function toSdkConfiguration(array $provider): array
    {
        $servesImages = isset($provider['models']['image']['default']);

        return [
            'driver' => $provider['driver'] ?? ($servesImages ? self::IMAGE_DRIVER : self::DEFAULT_DRIVER),
            'url' => $provider['url'],
            'key' => $provider['key'],
            'headers' => $provider['headers'],
            'models' => $provider['models'],
        ];
    }

    /**
     * Turn one declared entry into a provider, or reject it.
     *
     * @param  array<string, mixed>  $entry
     * @return array{name: string, label: string, url: string, key: string|null, driver: string|null, headers: array<string, string>, models: array<string, array{default: string}>}|null
     */
    private function normalise(array $entry): ?array
    {
        $name = Str::lower(trim((string) ($entry['name'] ?? '')));
        $name = Str::of($name)->replaceMatches('/[^a-z0-9._-]+/', '-')->trim('-')->toString();

        // A URL is the one field the OpenAI-compatible driver cannot do without,
        // and a name has to be resolvable, so either one missing rejects the
        // whole entry rather than registering a provider that cannot answer.
        if ($name === '' || in_array($name, self::RESERVED_NAMES, true)) {
            return null;
        }

        $url = rtrim(trim((string) ($entry['url'] ?? '')), '/');

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $key = trim((string) ($entry['key'] ?? ''));

        return [
            'name' => $name,
            'label' => $this->label($entry['label'] ?? null, $name),
            'url' => $url,
            'key' => $key === '' ? null : $key,
            'driver' => $this->driver($entry['driver'] ?? null),
            'headers' => $this->headers($entry['headers'] ?? []),
            'models' => $this->models($entry['models'] ?? []),
        ];
    }

    /**
     * The driver an entry explicitly asks for, if it asks for a known one.
     *
     * An entry may name its own driver to override the inference above, but only
     * among the two this application knows how to resolve: an arbitrary string
     * would be registered as a provider the SDK cannot build, which fails at the
     * first request rather than at boot. A bad value falls back to inference
     * rather than rejecting the entry, so a typo cannot silently remove a
     * working endpoint.
     */
    private function driver(mixed $driver): ?string
    {
        $driver = Str::lower(trim((string) $driver));

        return in_array($driver, [self::DEFAULT_DRIVER, self::IMAGE_DRIVER], true)
            ? $driver
            : null;
    }

    /**
     * The display name for a provider.
     */
    private function label(mixed $label, string $name): string
    {
        $label = trim((string) $label);

        if ($label !== '') {
            return $label;
        }

        return Str::of($name)->replace(['-', '_'], ' ')->title()->toString();
    }

    /**
     * Extra headers sent with every request to the provider.
     *
     * @return array<string, string>
     */
    private function headers(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $normalised = [];

        foreach ($headers as $header => $value) {
            if (! is_string($header) || trim($header) === '') {
                continue;
            }

            $value = is_scalar($value) ? trim((string) $value) : '';

            if ($value !== '') {
                $normalised[trim($header)] = $value;
            }
        }

        return $normalised;
    }

    /**
     * Default models per capability.
     *
     * Both shapes are accepted, because the shorthand is what people write by
     * hand and the long form is what the rest of config/ai.php uses:
     *
     *   {"text": "gpt-4"}                  and                  {"text": {"default": "gpt-4"}}
     *
     * @return array<string, array{default: string}>
     */
    private function models(mixed $models): array
    {
        if (! is_array($models)) {
            return [];
        }

        $normalised = [];

        foreach (self::CAPABILITIES as $capability) {
            $declared = $models[$capability] ?? null;

            $default = match (true) {
                is_string($declared) => trim($declared),
                is_array($declared) && is_string($declared['default'] ?? null) => trim($declared['default']),
                default => '',
            };

            if ($default !== '') {
                $normalised[$capability] = ['default' => $default];
            }
        }

        return $normalised;
    }
}
