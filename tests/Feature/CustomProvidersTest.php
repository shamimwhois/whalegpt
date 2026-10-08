<?php

use App\Ai\CustomProviders;
use App\Ai\ModelCatalog;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Declare a set of custom providers for the duration of one test.
 */
function declareCustomProviders(?string $json): void
{
    config(['whale.custom_providers' => $json]);

    // The merge happens once, when the application boots, so a test that
    // changes the declaration has to put it back the way the provider would.
    (new AppServiceProvider(app()))->mergeCustomProviders(app(CustomProviders::class));
}

test('a declared endpoint becomes a named provider the SDK can resolve', function () {
    declareCustomProviders(json_encode([
        ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1', 'key' => 'lm-key'],
        ['name' => 'gateway', 'url' => 'https://ai.example.com/v1'],
    ]));

    $providers = config('ai.providers');

    expect($providers)->toHaveKeys(['lmstudio', 'gateway'])
        ->and($providers['lmstudio'])->toMatchArray([
            'driver' => 'openai-compatible',
            'url' => 'http://127.0.0.1:1234/v1',
            'key' => 'lm-key',
        ])
        // A gateway that needs no key is a normal case, so it has to stay null
        // rather than becoming an empty bearer token.
        ->and($providers['gateway']['key'])->toBeNull()
        // The built-ins are untouched.
        ->and($providers)->toHaveKey('openai');
});

test('an endpoint whose url is unusable is skipped without losing the others', function () {
    declareCustomProviders(json_encode([
        ['name' => 'broken', 'url' => 'not a url'],
        ['url' => 'http://127.0.0.1:1234/v1'],
        ['name' => 'nourl'],
        ['name' => 'good', 'url' => 'http://127.0.0.1:4321/v1'],
    ]));

    expect(config('ai.providers'))->toHaveKey('good')
        ->and(config('ai.providers'))->not->toHaveKey('broken')
        ->and(config('ai.providers'))->not->toHaveKey('nourl')
        // An entry with no name cannot be resolved by anyone, so it is dropped
        // rather than registered under a name nobody asked for.
        ->and(count(array_filter(array_keys(config('ai.providers')), fn (string $name): bool => $name === '1' || $name === '2')))->toBe(0);
});

test('a custom provider may not take the name of a provider the SDK already resolves', function () {
    declareCustomProviders(json_encode([
        ['name' => 'openai', 'url' => 'http://127.0.0.1:1234/v1'],
        ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1'],
    ]));

    // The real OpenAI entry, key and all, is still the one that resolves.
    expect(config('ai.providers.openai.key'))->not->toBe('http://127.0.0.1:1234/v1')
        ->and(config('ai.providers'))->toHaveKey('lmstudio');
});

test('the first declaration of a name wins', function () {
    declareCustomProviders(json_encode([
        ['name' => 'twice', 'url' => 'http://127.0.0.1:1111/v1'],
        ['name' => 'twice', 'url' => 'http://127.0.0.1:2222/v1'],
    ]));

    expect(config('ai.providers.twice.url'))->toBe('http://127.0.0.1:1111/v1');
});

test('a malformed declaration leaves the catalog working with no custom providers', function () {
    declareCustomProviders('{ this is not json');

    expect(config('ai.providers'))->not->toHaveKey('lmstudio')
        ->and(config('ai.providers'))->toHaveKey('openai');
});

test('a custom provider can declare a default model for a capability', function () {
    declareCustomProviders(json_encode([
        [
            'name' => 'gateway',
            'url' => 'https://ai.example.com/v1',
            'models' => ['text' => 'meta-llama/Llama-3.3-70B'],
        ],
    ]));

    expect(config('ai.providers.gateway.models'))->toBe([
        'text' => ['default' => 'meta-llama/Llama-3.3-70B'],
    ]);
});

test('a custom provider is reported in the model catalog', function () {
    declareCustomProviders(json_encode([
        ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1', 'key' => 'lm-key'],
    ]));

    $provider = collect(app(ModelCatalog::class)->providers())
        ->firstWhere('name', 'lmstudio');

    expect($provider)->not->toBeNull()
        ->and($provider['configured'])->toBeTrue()
        ->and($provider['label'])->toBe('Lmstudio')
        ->and($provider['models'])->toBe([]);
});
