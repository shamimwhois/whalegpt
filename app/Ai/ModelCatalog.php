<?php

namespace App\Ai;

use App\Ai\Models\GgufModel;
use App\Ai\Models\LocalModelRegistry;
use App\Ai\Models\RuntimeModels;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Describes the AI providers and local models this application can run.
 *
 * Everything here is derived from config/ai.php and from the model files in
 * the local models directory: which providers have credentials, which
 * capabilities each exposes, and which models are actually wired up. The
 * catalog deliberately exposes no key material — the settings view only ever
 * learns whether a provider is configured, never the credential behind it.
 */
class ModelCatalog
{
    /**
     * The provider name local model files are reported under.
     */
    public const LOCAL_PROVIDER = 'local';

    public function __construct(
        private readonly LocalModelRegistry $localModels,
        private readonly RuntimeModels $runtimeModels,
    ) {}

    /**
     * The capabilities a provider can expose, in the order they are reported.
     *
     * @var list<string>
     */
    public const CAPABILITIES = ['text', 'image', 'audio', 'transcription'];

    /**
     * The config fields that prove a driver is configured, keyed by driver.
     *
     * Ollama and OpenAI-compatible providers authenticate against a base URL
     * rather than a key, so a URL is their signal.
     *
     * @var array<string, list<string>>
     */
    private const CREDENTIAL_FIELDS = [
        'ollama' => ['url'],
        'openai-compatible' => ['url'],
        'bedrock' => ['key', 'access_key_id', 'secret_access_key'],
    ];

    /**
     * The environment variable each driver needs, for display only.
     *
     * @var array<string, string>
     */
    private const ENVIRONMENT_HINTS = [
        'anthropic' => 'ANTHROPIC_API_KEY',
        'azure' => 'AZURE_OPENAI_API_KEY',
        'bedrock' => 'AWS_ACCESS_KEY_ID',
        'cohere' => 'COHERE_API_KEY',
        'deepseek' => 'DEEPSEEK_API_KEY',
        'eleven' => 'ELEVENLABS_API_KEY',
        'gemini' => 'GEMINI_API_KEY',
        'groq' => 'GROQ_API_KEY',
        'jina' => 'JINA_API_KEY',
        'mistral' => 'MISTRAL_API_KEY',
        'ollama' => 'OLLAMA_URL',
        'openai' => 'OPENAI_API_KEY',
        'openai-compatible' => 'OPENAI_COMPATIBLE_URL',
        'openrouter' => 'OPENROUTER_API_KEY',
        'typesafe' => 'TYPESAFE_API_KEY',
        'voyageai' => 'VOYAGEAI_API_KEY',
        'xai' => 'XAI_API_KEY',
    ];

    /**
     * Well-known text models per driver, offered as suggestions.
     *
     * A suggestion only becomes selectable once that model is wired up in
     * config/ai.php. The rest are listed so the settings view can name the
     * exact environment variable to set.
     *
     * @var array<string, array<string, string>>
     */
    private const SUGGESTED_MODELS = [
        'anthropic' => [
            'claude-opus-4-5' => 'Claude Opus 4.5',
            'claude-sonnet-4-5' => 'Claude Sonnet 4.5',
            'claude-haiku-4-5' => 'Claude Haiku 4.5',
        ],
        'deepseek' => [
            'deepseek-chat' => 'DeepSeek Chat',
            'deepseek-reasoner' => 'DeepSeek Reasoner',
        ],
        'gemini' => [
            'gemini-2.5-pro' => 'Gemini 2.5 Pro',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
        ],
        'groq' => [
            'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
        ],
        'mistral' => [
            'mistral-large-latest' => 'Mistral Large',
            'mistral-small-latest' => 'Mistral Small',
        ],
        'ollama' => [
            'qwen3:4b' => 'Qwen3 4B',
            'llama3.1:8b' => 'Llama 3.1 8B',
            'gemma3:4b' => 'Gemma 3 4B',
        ],
        'openai' => [
            'gpt-5' => 'GPT-5',
            'gpt-5-mini' => 'GPT-5 mini',
            'gpt-5-nano' => 'GPT-5 nano',
        ],
        'openrouter' => [
            'openai/gpt-5' => 'GPT-5 (OpenRouter)',
        ],
        'xai' => [
            'grok-4' => 'Grok 4',
        ],
    ];

    /**
     * The provider and model used when the browser has no selection.
     *
     * @return array{provider: string|null, model: string|null}
     */
    public function defaultSelection(): array
    {
        $provider = config('ai.default');

        if (! is_string($provider) || $provider === '') {
            return ['provider' => null, 'model' => null];
        }

        return [
            'provider' => $provider,
            'model' => $this->configuredModel($provider, 'text'),
        ];
    }

    /**
     * Every provider this application knows about, with its status.
     *
     * @return list<array{
     *     name: string,
     *     label: string,
     *     driver: string,
     *     configured: bool,
     *     default: bool,
     *     environment: string,
     *     capabilities: list<string>,
     *     models: list<array{id: string, label: string, configured: bool, default: bool}>
     * }>
     */
    public function providers(): array
    {
        $default = config('ai.default');

        $providers = [];

        foreach (config('ai.providers', []) as $name => $configuration) {
            if (! is_array($configuration) || blank($configuration['driver'] ?? null)) {
                continue;
            }

            $driver = $configuration['driver'];

            $providers[] = [
                'name' => $name,
                'label' => $this->label($name),
                'driver' => $driver,
                'configured' => $this->isConfigured($driver, $configuration),
                'default' => $name === $default,
                'environment' => self::ENVIRONMENT_HINTS[$driver] ?? Str::upper($driver).'_API_KEY',
                'capabilities' => $this->capabilities($configuration),
                'models' => $this->models($driver, $configuration, $name === $default),
            ];
        }

        $local = $this->localProvider();

        return $local === null ? $providers : [$local, ...$providers];
    }

    /**
     * The detected local model files, presented as a provider.
     *
     * Local models are reported separately from API providers because they are
     * served by a runtime you run locally rather than by a hosted endpoint, and
     * because only the ones with a configured runtime are selectable.
     *
     * @return array<string, mixed>|null
     */
    private function localProvider(): ?array
    {
        $models = $this->localModels->models();

        if ($models->isEmpty()) {
            return null;
        }

        $capabilities = $models
            ->flatMap(fn (GgufModel $model): array => $model->capabilities())
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'name' => self::LOCAL_PROVIDER,
            'label' => 'Local models',
            'driver' => 'local',
            'configured' => $models->contains(fn (GgufModel $model): bool => $model->isConfigured()),
            'default' => false,
            'environment' => config('whale.models_path'),
            'capabilities' => $capabilities,
            'models' => $models
                ->map(fn (GgufModel $model): array => $this->modelEntry(
                    $model->id,
                    $model->isConfigured(),
                    false,
                    $model->name(),
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * The catalog as sent to the browser.
     *
     * @return array{default: array{provider: string|null, model: string|null}, providers: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'default' => $this->defaultSelection(),
            'providers' => $this->providers(),
        ];
    }

    /**
     * Resolve a requested provider and model into something safe to send.
     *
     * Unconfigured providers, and models that are not wired up, are rejected
     * so a stale selection stored in the browser can never become a request
     * the AI layer would only fail on later.
     *
     * @return array{provider: string|null, model: string|null}
     *
     * @throws ValidationException
     */
    public function resolve(?string $provider, ?string $model = null): array
    {
        if (blank($provider)) {
            return ['provider' => null, 'model' => null];
        }

        $match = collect($this->providers())
            ->first(fn (array $candidate): bool => $candidate['name'] === $provider);

        if ($match === null || ! $match['configured']) {
            throw ValidationException::withMessages([
                'provider' => sprintf(
                    'The "%s" provider is not configured. Add %s to your .env file.',
                    $provider,
                    self::ENVIRONMENT_HINTS[$match['driver'] ?? ''] ?? 'its API key',
                ),
            ]);
        }

        if (blank($model)) {
            return ['provider' => $provider, 'model' => null];
        }

        $requested = collect($match['models'])
            ->first(fn (array $candidate): bool => $candidate['id'] === $model && $candidate['configured']);

        if ($requested === null) {
            throw ValidationException::withMessages([
                'model' => sprintf(
                    'The model "%s" is not configured for %s. Set %s_MODEL in your .env file.',
                    $model,
                    $match['label'],
                    Str::upper($match['driver']),
                ),
            ]);
        }

        return ['provider' => $provider, 'model' => $model];
    }

    /**
     * The configured model for a capability, by provider name.
     */
    public function configuredModel(string $provider, string $capability): ?string
    {
        $configuration = config("ai.providers.{$provider}");

        if (! is_array($configuration)) {
            return null;
        }

        $model = $configuration['models'][$capability]['default'] ?? null;

        return filled($model) ? $model : null;
    }

    /**
     * Whether a configured provider has a model for the given capability.
     */
    public function supports(string $provider, string $capability): bool
    {
        return $this->configuredModel($provider, $capability) !== null;
    }

    /**
     * A human readable provider name.
     */
    private function label(string $name): string
    {
        return match ($name) {
            'openai' => 'OpenAI',
            'openai-compatible' => 'OpenAI compatible',
            'openrouter' => 'OpenRouter',
            'azure' => 'Azure OpenAI',
            'xai' => 'xAI',
            'eleven' => 'ElevenLabs',
            'voyageai' => 'Voyage AI',
            'typesafe' => 'TypeSafe',
            'deepseek' => 'DeepSeek',
            default => Str::of($name)->replace('-', ' ')->title()->toString(),
        };
    }

    /**
     * Whether a driver has the credentials it needs to answer a request.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function isConfigured(string $driver, array $configuration): bool
    {
        $fields = self::CREDENTIAL_FIELDS[$driver] ?? ['key'];

        return collect($fields)
            ->contains(fn (string $field): bool => filled($configuration[$field] ?? null));
    }

    /**
     * The capabilities a provider exposes, read from its configured models.
     *
     * @param  array<string, mixed>  $configuration
     * @return list<string>
     */
    private function capabilities(array $configuration): array
    {
        return array_values(array_filter(
            self::CAPABILITIES,
            fn (string $capability): bool => filled($configuration['models'][$capability]['default'] ?? null),
        ));
    }

    /**
     * The text models to offer for a provider.
     *
     * The wired-up model always leads the list, followed by suggestions that
     * stay unselectable until they are configured themselves. Each model is
     * tagged with a type so the picker can filter by intent — a reasoning
     * model and a chat model are different answers to different questions.
     *
     * @param  array<string, mixed>  $configuration
     * @return list<array{id: string, label: string, configured: bool, default: bool, type: string, reasoning: bool}>
     */
    private function models(string $driver, array $configuration, bool $isDefaultProvider): array
    {
        $models = [];

        $configured = $configuration['models']['text']['default'] ?? null;

        if (filled($configured)) {
            $models[$configured] = $this->modelEntry($configured, true, $isDefaultProvider);
        }

        // A runtime that keeps its own model store can be asked what it holds,
        // which beats one env var per model: duplicates collapse and only the
        // last would ever be read.
        foreach ($this->discovered($driver, $configuration) as $id) {
            if (isset($models[$id])) {
                continue;
            }

            $models[$id] = $this->modelEntry($id, true, false, $this->modelLabel($id));
        }

        foreach (self::SUGGESTED_MODELS[$driver] ?? [] as $id => $label) {
            if (isset($models[$id])) {
                continue;
            }

            $models[$id] = $this->modelEntry($id, false, false, $label);
        }

        return array_values($models);
    }

    /**
     * The models a store-backed runtime reports as pulled, if this driver is one.
     *
     * @param  array<string, mixed>  $configuration
     * @return list<string>
     */
    private function discovered(string $driver, array $configuration): array
    {
        foreach ((array) config('whale.runtimes', []) as $name => $runtime) {
            if (! is_array($runtime)
                || ($runtime['discovers_models'] ?? false) === false
                || ($runtime['driver'] ?? null) !== $driver) {
                continue;
            }

            // Only ask a runtime that is actually configured, so an unreachable
            // one cannot add a request to every page load.
            if (blank($configuration['url'] ?? null)) {
                return [];
            }

            return $this->runtimeModels->for($name);
        }

        return [];
    }

    /**
     * A readable label for a discovered model, for example "Qwen3 4B".
     */
    private function modelLabel(string $id): string
    {
        [$name, $size] = array_pad(explode(':', $id, 2), 2, null);

        $label = Str::of($name)
            ->afterLast('/')
            ->replace(['-', '_'], ' ')
            ->squish()
            ->title();

        return $size === null || $size === '' ? (string) $label : $label.' '.Str::upper($size);
    }

    /**
     * A single model entry as the picker reads it.
     *
     * @return array{id: string, label: string, configured: bool, default: bool, type: string, reasoning: bool}
     */
    private function modelEntry(string $id, bool $configured, bool $isDefault, ?string $label = null): array
    {
        $reasoning = $this->isReasoningModel($id);

        return [
            'id' => $id,
            'label' => $label ?? $id,
            'configured' => $configured,
            'default' => $isDefault,
            'type' => $reasoning ? 'reasoning' : 'fast',
            'reasoning' => $reasoning,
        ];
    }

    /**
     * Whether a model id looks like a reasoning model.
     *
     * This drives a display filter rather than any request, so a false positive
     * only mislabels a chip and never costs a failed call.
     */
    private function isReasoningModel(string $id): bool
    {
        $id = Str::lower($id);

        foreach (['reasoner', '-r1', 'qwq', 'thinking', 'o1', 'o3', 'o4', 'grok-4', 'gpt-5', 'gemini-2.5-pro', 'gemini-3', 'deepseek-reasoner'] as $marker) {
            if (Str::contains($id, $marker)) {
                return true;
            }
        }

        // A leading digit check would over-match; keep to the named families.
        return false;
    }
}
