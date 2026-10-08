<?php

use App\Ai\CustomModels;
use App\Ai\CustomProviders;
use App\Ai\ModelCatalog;
use App\Models\CustomProviderModel;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Declare a custom provider and return the models endpoint it will be asked for.
 */
function declareProvider(?string $json = null): string
{
    config([
        'whale.custom_providers' => $json ?? json_encode([
            ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1', 'key' => 'lm-key'],
        ]),
    ]);

    (new AppServiceProvider(app()))->mergeCustomProviders(app(CustomProviders::class));

    return 'http://127.0.0.1:1234/v1/models';
}

/**
 * A listing in the shape an OpenAI-compatible endpoint returns.
 *
 * @param  list<string>  $ids
 */
function modelsListing(array $ids): array
{
    return ['data' => array_map(fn (string $id): array => ['id' => $id], $ids)];
}

test('an endpoint is asked for its models over v1 models', function () {
    $endpoint = declareProvider();
    Http::fake([$endpoint => Http::response(modelsListing(['qwen3:4b', 'gemma3:12b']))]);

    $discovered = app(CustomModels::class)->discover('lmstudio');

    expect($discovered)->toBe([
        ['id' => 'gemma3:12b', 'label' => 'Gemma3 12B'],
        ['id' => 'qwen3:4b', 'label' => 'Qwen3 4B'],
    ]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer lm-key'));
});

test('discovering stores nothing until models are imported', function () {
    $endpoint = declareProvider();
    Http::fake([$endpoint => Http::response(modelsListing(['qwen3:4b']))]);

    app(CustomModels::class)->discover('lmstudio');

    expect(CustomProviderModel::query()->count())->toBe(0);
});

test('an imported model is kept and offered to the picker', function () {
    declareProvider();

    $imported = app(CustomModels::class)->import('lmstudio', ['qwen3:4b']);

    expect($imported)->toBe(['qwen3:4b'])
        ->and(app(CustomModels::class)->for('lmstudio'))->toBe([
            ['id' => 'qwen3:4b', 'label' => 'Qwen3 4B'],
        ]);
});

test('importing the same model twice keeps one row', function () {
    declareProvider();

    $models = app(CustomModels::class);

    $models->import('lmstudio', ['qwen3:4b']);
    $second = $models->import('lmstudio', ['qwen3:4b']);

    expect($second)->toBe([])
        ->and(CustomProviderModel::query()->count())->toBe(1);
});

test('syncing keeps what the endpoint offers and drops what it does not', function () {
    $endpoint = declareProvider();
    Http::fake([$endpoint => Http::response(modelsListing(['qwen3:4b', 'gemma3:12b']))]);

    $models = app(CustomModels::class);
    $models->import('lmstudio', ['retired-model']);

    $result = $models->sync('lmstudio');

    expect($result['added'])->toBe(['gemma3:12b', 'qwen3:4b'])
        ->and($result['removed'])->toBe(['retired-model'])
        ->and($result['unreachable'])->toBeFalse()
        ->and($models->for('lmstudio'))->toBe([
            ['id' => 'gemma3:12b', 'label' => 'Gemma3 12B'],
            ['id' => 'qwen3:4b', 'label' => 'Qwen3 4B'],
        ]);
});

test('syncing an endpoint that cannot be reached keeps every imported model', function () {
    $endpoint = declareProvider();
    Http::fake([$endpoint => Http::response('gateway timeout', 504)]);

    $models = app(CustomModels::class);
    $models->import('lmstudio', ['qwen3:4b']);

    $result = $models->sync('lmstudio');

    // A provider that is briefly down must not be able to empty the catalogue.
    expect($result['unreachable'])->toBeTrue()
        ->and($result['removed'])->toBe([])
        ->and($models->for('lmstudio'))->toBe([['id' => 'qwen3:4b', 'label' => 'Qwen3 4B']]);
});

test('syncing an endpoint that serves nothing does clear its models', function () {
    $endpoint = declareProvider();
    Http::fake([$endpoint => Http::response(['data' => []])]);

    $models = app(CustomModels::class);
    $models->import('lmstudio', ['qwen3:4b']);

    $result = $models->sync('lmstudio');

    expect($result['unreachable'])->toBeFalse()
        ->and($result['removed'])->toBe(['qwen3:4b'])
        ->and($models->for('lmstudio'))->toBe([]);
});

test('discovering an undeclared provider reports that it could not be asked', function () {
    declareProvider();
    Http::fake();

    // No request may be made on behalf of a provider that is not configured.
    expect(app(CustomModels::class)->discover('not-declared'))->toBeNull();

    Http::assertNothingSent();
});

test('an imported model becomes a selectable model in the catalog', function () {
    declareProvider();
    app(CustomModels::class)->import('lmstudio', ['qwen3:4b']);

    $provider = collect(app(ModelCatalog::class)->providers())
        ->firstWhere('name', 'lmstudio');

    expect($provider['capabilities'])->toContain('text')
        ->and($provider['models'])->toHaveCount(1)
        ->and($provider['models'][0]['id'])->toBe('qwen3:4b')
        // Selectable, which is the whole point of importing it.
        ->and($provider['models'][0]['configured'])->toBeTrue();
});

test('a model the provider no longer serves can no longer be selected', function () {
    declareProvider();
    app(CustomModels::class)->import('lmstudio', ['retired-model']);

    expect(app(ModelCatalog::class)->resolve('lmstudio', 'retired-model'))->not->toThrow(
        ValidationException::class
    );

    // After a sync removes it, the stale selection must be rejected rather than
    // sent on to an endpoint that will refuse it.
    Http::fake(['*/models' => Http::response(modelsListing(['qwen3:4b']))]);
    app(CustomModels::class)->sync('lmstudio');

    app(ModelCatalog::class)->resolve('lmstudio', 'retired-model');
})->throws(ValidationException::class);

test('models are never shared between providers', function () {
    config([
        'whale.custom_providers' => json_encode([
            ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1'],
            ['name' => 'gateway', 'url' => 'https://ai.example.com/v1'],
        ]),
    ]);
    (new AppServiceProvider(app()))->mergeCustomProviders(app(CustomProviders::class));

    $models = app(CustomModels::class);
    $models->import('lmstudio', ['qwen3:4b']);

    expect($models->for('gateway'))->toBe([]);
});

test('models for a provider removed from the environment are pruned', function () {
    declareProvider();
    app(CustomModels::class)->import('lmstudio', ['qwen3:4b']);

    // The provider is taken back out of .env.
    config(['whale.custom_providers' => null]);

    expect(app(CustomModels::class)->prune())->toBe(1)
        ->and(CustomProviderModel::query()->count())->toBe(0);
});
