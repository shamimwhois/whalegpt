<?php

namespace App\Ai;

use App\Models\CustomProviderModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The models behind a custom provider: what it serves, what has been kept, and
 * how the two are brought back into agreement.
 *
 * Three verbs, deliberately distinct:
 *
 *   discover  ask the endpoint what it has right now, storing nothing
 *   import    keep specific models the operator picked out of that list
 *   sync      re-ask, then make the stored set match the answer
 *
 * Sync is a reconcile, not a merge, which is why it needs to be able to tell an
 * endpoint that serves nothing from one it could not reach. Conflating those
 * would let a provider that was briefly down wipe every model an operator had
 * imported.
 */
class CustomModels
{
    /**
     * How long to wait on a custom endpoint before treating it as unreachable.
     */
    private const TIMEOUT = 10;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CustomProviders $customProviders,
    ) {}

    /**
     * The models this endpoint is kept for, as the picker reads them.
     *
     * @return list<array{id: string, label: string}>
     */
    public function for(string $provider): array
    {
        return CustomProviderModel::query()
            ->forProvider($provider)
            ->orderBy('model_id')
            ->get()
            ->map(fn (CustomProviderModel $model): array => [
                'id' => $model->model_id,
                'label' => $model->label ?: $this->label($model->model_id),
            ])
            ->all();
    }

    /**
     * What the endpoint is serving right now.
     *
     * Returns null, never an empty list, when the endpoint could not be asked:
     * "it has no models" and "I could not reach it" lead to opposite decisions
     * during a sync.
     *
     * @return list<array{id: string, label: string}>|null
     */
    public function discover(string $provider): ?array
    {
        $configuration = $this->configuration($provider);

        if ($configuration === null) {
            return null;
        }

        // Relative to the provider's own base URL, which is an OpenAI-compatible
        // base and already carries the /v1. Appending the runtime listing path
        // instead would ask http://host/v1/v1/models.
        $path = (string) config('whale.custom_providers_models_path', '/models');
        $endpoint = rtrim($configuration['url'], '/').'/'.ltrim($path, '/');

        try {
            $request = $this->http->acceptJson()->timeout(self::TIMEOUT);

            if (filled($configuration['key'] ?? null)) {
                $request = $request->withToken($configuration['key']);
            }

            $response = $request->get($endpoint);
        } catch (ConnectionException $e) {
            Log::info("Could not reach custom provider {$provider}: {$e->getMessage()}");

            return null;
        } catch (Throwable $e) {
            Log::info("Could not list models for custom provider {$provider}: {$e->getMessage()}");

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->entries($response->json());
    }

    /**
     * Keep the given models for a provider, ignoring any already kept.
     *
     * @param  list<string>  $modelIds
     * @return list<string> the ids that were newly stored
     */
    public function import(string $provider, array $modelIds): array
    {
        $existing = $this->storedIds($provider);

        $imported = [];

        foreach ($modelIds as $modelId) {
            $modelId = trim((string) $modelId);

            if ($modelId === '' || in_array($modelId, $existing, true)) {
                continue;
            }

            CustomProviderModel::query()->create([
                'provider' => $provider,
                'model_id' => $modelId,
                'label' => $this->label($modelId),
            ]);

            $existing[] = $modelId;
            $imported[] = $modelId;
        }

        return $imported;
    }

    /**
     * Bring the stored set back into line with the endpoint.
     *
     * Models the endpoint no longer lists are dropped, because a model it will
     * not serve is worse than no model at all: the picker would offer a
     * selection that fails on the next message.
     *
     * @return array{added: list<string>, removed: list<string>, unreachable: bool}
     */
    public function sync(string $provider): array
    {
        $discovered = $this->discover($provider);

        if ($discovered === null) {
            // Nothing is deleted on an unreachable endpoint. Reporting it as a
            // failure rather than an empty result is the whole point of
            // discover() returning null.
            return ['added' => [], 'removed' => [], 'unreachable' => true];
        }

        $offered = array_column($discovered, 'id');
        $stored = $this->storedIds($provider);

        $added = array_values(array_diff($offered, $stored));
        $removed = array_values(array_diff($stored, $offered));

        $this->import($provider, $added);

        if ($removed !== []) {
            CustomProviderModel::query()
                ->forProvider($provider)
                ->whereIn('model_id', $removed)
                ->delete();
        }

        return ['added' => $added, 'removed' => $removed, 'unreachable' => false];
    }

    /**
     * Forget every model kept for a provider that is no longer declared.
     *
     * Rows outlive the .env entry that created them, so removing a provider
     * from the environment would otherwise leave its models stranded in the
     * picker with nothing to resolve them against.
     */
    public function prune(): int
    {
        $declared = array_column($this->customProviders->all(), 'name');

        return CustomProviderModel::query()
            ->whereNotIn('provider', $declared)
            ->delete();
    }

    /**
     * The model ids currently kept for a provider.
     *
     * @return list<string>
     */
    private function storedIds(string $provider): array
    {
        return CustomProviderModel::query()
            ->forProvider($provider)
            ->pluck('model_id')
            ->all();
    }

    /**
     * The declared configuration for a custom provider.
     *
     * @return array<string, mixed>|null
     */
    private function configuration(string $provider): ?array
    {
        return $this->customProviders->only([$provider])[$provider] ?? null;
    }

    /**
     * Pull model entries out of a provider's catalogue.
     *
     * Two shapes are accepted. The OpenAI one is an envelope:
     * `{"data": [{"id": "..."}]}`. Several endpoints that also serve images
     * publish a per-modality catalogue as a bare array keyed on `name` instead,
     * so that is read too rather than being reported as an empty endpoint.
     *
     * @return list<array{id: string, label: string}>
     */
    private function entries(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        // An envelope wraps the list under "data"; a bare list does not.
        $models = array_is_list($data)
            ? $data
            : ($data['data'] ?? null);

        if (! is_array($models)) {
            return [];
        }

        $entries = [];

        foreach ($models as $model) {
            $id = match (true) {
                is_array($model) => $model['id'] ?? $model['name'] ?? null,
                is_string($model) => $model,
                default => null,
            };

            if (! is_string($id) || trim($id) === '') {
                continue;
            }

            $id = trim($id);

            // The id is kept exactly as the endpoint serves it. An earlier
            // version trimmed any owner prefix, on the grounds that endpoints
            // commonly serve the same weights both ways -- but for a hosted
            // gateway the prefix is the model: "openai/gpt-image-2" is not
            // served as "gpt-image-2", and the shortened id would be rejected
            // at generation time with nothing to explain why the model had
            // disappeared from the endpoint's own list.
            if (isset($entries[$id])) {
                continue;
            }

            $label = match (true) {
                is_array($model) && is_string($model['display_name'] ?? null) => trim($model['display_name']),
                is_array($model) && is_string($model['title'] ?? null) => trim($model['title']),
                default => '',
            };

            $entries[$id] = ['id' => $id, 'label' => $label !== '' ? $label : $this->label($id)];
        }

        ksort($entries);

        return array_values($entries);
    }

    /**
     * A readable name for a model id, for example "Qwen3 4B".
     */
    private function label(string $modelId): string
    {
        // Only the last segment is the model's own name; an owner prefix like
        // "black-forest-labs/" is an identifier to send, not something to
        // title-case into the label.
        $modelId = Str::afterLast($modelId, '/');

        [$name, $size] = array_pad(explode(':', $modelId, 2), 2, null);

        $label = Str::of($name)
            ->replace(['-', '_'], ' ')
            ->squish()
            ->title();

        return $size === null || $size === '' ? (string) $label : $label.' '.Str::upper($size);
    }
}
