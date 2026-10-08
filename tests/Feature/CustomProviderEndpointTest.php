<?php

use App\Ai\CustomModels;
use App\Ai\CustomProviders;
use App\Models\CustomProviderModel;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'whale.custom_providers' => json_encode([
            ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1', 'key' => 'lm-key'],
        ]),
    ]);

    (new AppServiceProvider(app()))->mergeCustomProviders(app(CustomProviders::class));
});

/**
 * @param  list<string>  $ids
 */
function listing(array $ids): array
{
    return ['data' => array_map(fn (string $id): array => ['id' => $id], $ids)];
}

test('the declared providers are listed with their kept models and never their keys', function () {
    app(CustomModels::class)->import('lmstudio', ['qwen3:4b']);

    $response = $this->getJson(route('chat.custom-providers'))->assertOk();

    $response->assertJsonPath('providers.0.name', 'lmstudio')
        ->assertJsonPath('providers.0.has_key', true)
        ->assertJsonPath('providers.0.models.0.id', 'qwen3:4b');

    // The key authenticates requests to the endpoint; it is not something the
    // browser has any use for.
    expect($response->getContent())->not->toContain('lm-key');
});

test('a provider that is not declared is rejected', function () {
    $this->getJson(route('chat.custom-providers.models', 'nope'))->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    $this->postJson(route('chat.custom-providers.sync', 'nope'))->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    $this->postJson(route('chat.custom-providers.import', 'nope'), ['models' => ['x']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');
});

test('discovering reports what the endpoint serves and reaches it with the key', function () {
    Http::fake(['*/v1/models' => Http::response(listing(['qwen3:4b']))]);

    $this->getJson(route('chat.custom-providers.models', 'lmstudio'))
        ->assertOk()
        ->assertJsonPath('reachable', true)
        ->assertJsonPath('models.0.id', 'qwen3:4b');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer lm-key'));
});

test('an endpoint that cannot be reached is reported rather than treated as empty', function () {
    Http::fake(['*/v1/models' => Http::response('', 500)]);

    $this->getJson(route('chat.custom-providers.models', 'lmstudio'))
        ->assertOk()
        ->assertJsonPath('reachable', false)
        ->assertJsonPath('models', []);
});

test('importing requires at least one model name', function () {
    $this->postJson(route('chat.custom-providers.import', 'lmstudio'), ['models' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('models');

    $this->postJson(route('chat.custom-providers.import', 'lmstudio'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('models');
});

test('importing keeps the chosen models', function () {
    $this->postJson(route('chat.custom-providers.import', 'lmstudio'), [
        'models' => ['qwen3:4b', 'gemma3:12b'],
    ])
        ->assertOk()
        ->assertJsonPath('imported', ['qwen3:4b', 'gemma3:12b']);

    expect(CustomProviderModel::query()->count())->toBe(2);
});

test('syncing reports what it added and removed', function () {
    Http::fake(['*/v1/models' => Http::response(listing(['qwen3:4b']))]);

    app(CustomModels::class)->import('lmstudio', ['retired-model']);

    $this->postJson(route('chat.custom-providers.sync', 'lmstudio'))
        ->assertOk()
        ->assertJsonPath('unreachable', false)
        ->assertJsonPath('removed', ['retired-model'])
        ->assertJsonPath('models.0.id', 'qwen3:4b');
});

test('syncing an unreachable endpoint leaves the kept models alone', function () {
    Http::fake(['*/v1/models' => Http::response('', 502)]);

    app(CustomModels::class)->import('lmstudio', ['qwen3:4b']);

    $this->postJson(route('chat.custom-providers.sync', 'lmstudio'))
        ->assertOk()
        ->assertJsonPath('unreachable', true)
        ->assertJsonPath('removed', [])
        ->assertJsonPath('models.0.id', 'qwen3:4b');
});
