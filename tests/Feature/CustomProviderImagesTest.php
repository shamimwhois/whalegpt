<?php

use App\Ai\CustomModels;
use App\Ai\CustomProviders;
use App\Ai\ModelCatalog;
use App\Ai\OpenAiImageProvider;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;

uses(RefreshDatabase::class);

/**
 * Drop anything merged from a previous declaration.
 *
 * config('ai.providers') accumulates across the merge, so a declaration that
 * follows another one would see the earlier entry and skip it as a collision.
 */
function resetMergedImageProviders(): void
{
    config(['whale.custom_providers' => null]);

    foreach (array_keys(config('ai.providers', [])) as $name) {
        if (! in_array($name, CustomProviders::RESERVED_NAMES, true) && ! array_key_exists($name, config('ai.default_providers', []))) {
            unset(config('ai.providers')[$name]);
        }
    }
}

/**
 * Declare a set of custom providers for the duration of one test.
 */
function declareImageProviders(?string $json): void
{
    resetMergedImageProviders();

    config(['whale.custom_providers' => $json]);

    (new AppServiceProvider(app()))->mergeCustomProviders(app(CustomProviders::class));

    // A fake built from a URL map leaves unmatched requests to the real network,
    // so a mistyped host in a test silently becomes a real request to whatever
    // the declaration named. These tests point at a live provider, so a stray
    // request would be a real one.
    Http::preventStrayRequests();
}

test('an endpoint declaring an image model resolves through an image capable provider', function () {
    declareImageProviders(json_encode([
        ['name' => 'pollinations', 'url' => 'https://gen.pollinations.ai/v1', 'key' => 'pk',
            'models' => ['image' => ['default' => 'openai/gpt-image-2']]],
    ]));

    // The SDK's own compatible driver implements no ImageProvider, so the driver
    // choice is what decides whether the endpoint can be used for images at all.
    expect(config('ai.providers.pollinations'))->toMatchArray([
        'driver' => 'openai-image',
        'url' => 'https://gen.pollinations.ai/v1',
    ]);

    $provider = Ai::imageProvider('pollinations');

    expect($provider)->toBeInstanceOf(OpenAiImageProvider::class)
        ->and($provider)->toBeInstanceOf(ImageProvider::class)
        ->and($provider)->toBeInstanceOf(TextProvider::class)
        ->and($provider->defaultImageModel())->toBe('openai/gpt-image-2');
});

test('an endpoint declaring no image model stays on the text only driver', function () {
    declareImageProviders(json_encode([
        ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1', 'key' => 'k'],
    ]));

    expect(config('ai.providers.lmstudio')['driver'])->toBe('openai-compatible');

    // The manager refuses the selection outright, which is the point: the
    // endpoint is never quietly accepted and then fails mid-request.
    Ai::imageProvider('lmstudio');
})->throws(LogicException::class, 'does not support image generation');

test('the image driver is refused as a provider name', function () {
    declareImageProviders(json_encode([
        ['name' => 'openai-image', 'url' => 'https://evil.example.com/v1', 'key' => 'k'],
    ]));

    expect(config('ai.providers'))->not->toHaveKey('openai-image');
});

test('an unrecognised driver falls back to inference instead of dropping the endpoint', function () {
    declareImageProviders(json_encode([
        ['name' => 'pollinations', 'url' => 'https://gen.pollinations.ai/v1', 'key' => 'k',
            'driver' => 'totally-made-up',
            'models' => ['image' => ['default' => 'openai/gpt-image-2']]],
    ]));

    // Rejecting the entry would silently remove a working endpoint; an
    // unresolvable driver would instead fail on the first request.
    expect(config('ai.providers.pollinations')['driver'])->toBe('openai-image');
});

test('an explicit driver overrides the inferred one', function () {
    declareImageProviders(json_encode([
        ['name' => 'gateway', 'url' => 'https://ai.example.com/v1', 'key' => 'k',
            'driver' => 'openai-compatible',
            'models' => ['image' => ['default' => 'flux']]],
    ]));

    expect(config('ai.providers.gateway')['driver'])->toBe('openai-compatible');
});

test('the catalog offers a custom provider for images', function () {
    declareImageProviders(json_encode([
        ['name' => 'pollinations', 'url' => 'https://gen.pollinations.ai/v1', 'key' => 'pk',
            'models' => ['image' => ['default' => 'openai/gpt-image-2']]],
    ]));

    $catalog = app(ModelCatalog::class);

    expect($catalog->supports('pollinations', 'image'))->toBeTrue();

    $provider = collect($catalog->providers())->firstWhere('name', 'pollinations');

    expect($provider['capabilities'])->toContain('image')
        ->and($catalog->configuredModel('pollinations', 'image'))->toBe('openai/gpt-image-2');
});

test('the declared image model is selectable rather than refused', function () {
    declareImageProviders(json_encode([
        ['name' => 'pollinations', 'url' => 'https://gen.pollinations.ai/v1', 'key' => 'pk',
            'models' => ['image' => ['default' => 'openai/gpt-image-2']]],
    ]));

    $catalog = app(ModelCatalog::class);

    $provider = collect($catalog->providers())->firstWhere('name', 'pollinations');

    // Resolution only accepts a model the catalog lists, so an image default
    // left out of that list made the provider look configured and then refuse
    // the exact model it was configured with.
    expect(collect($provider['models'])->pluck('id'))->toContain('openai/gpt-image-2')
        ->and($catalog->resolve('pollinations', 'openai/gpt-image-2'))
        ->toBe(['provider' => 'pollinations', 'model' => 'openai/gpt-image-2']);
});

test('a custom provider that declared no image model is still refused for images', function () {
    declareImageProviders(json_encode([
        ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1', 'key' => 'k'],
    ]));

    // The regression this guards: importing models used to be enough to make a
    // provider look capable of everything, which offered it for images and
    // failed the request.
    app(CustomModels::class)->import('lmstudio', ['sdxl-1.0']);

    expect(app(ModelCatalog::class)->supports('lmstudio', 'image'))->toBeFalse();
});

test('the image provider builds its request from configuration', function () {
    $provider = new OpenAiImageProvider(
        [
            'name' => 'pollinations',
            'driver' => 'openai-image',
            'key' => 'test-key',
            'url' => 'https://gen.pollinations.ai/v1',
            'models' => ['image' => ['default' => 'openai/gpt-image-2']],
        ],
        app(Dispatcher::class),
    );

    // The gateway is the SDK's own, so bearer auth and base-url resolution are
    // identical to a built-in provider rather than reimplemented here.
    expect($provider->imageGateway())->toBeInstanceOf(OpenAiGateway::class)
        ->and($provider->additionalConfiguration()['url'])->toBe('https://gen.pollinations.ai/v1')
        ->and($provider->providerCredentials())->toBe(['key' => 'test-key']);
});

test('an aspect ratio is translated to the pixel size the endpoint expects', function () {
    $provider = new OpenAiImageProvider(
        ['name' => 'pollinations', 'driver' => 'openai-image', 'key' => 'k', 'url' => 'https://x.test/v1'],
        app(Dispatcher::class),
    );

    expect($provider->defaultImageOptions('1:1'))->toBe(['size' => '1024x1024'])
        ->and($provider->defaultImageOptions('2:3'))->toBe(['size' => '1024x1536'])
        ->and($provider->defaultImageOptions('3:2'))->toBe(['size' => '1536x1024'])
        // An explicit pixel size is passed through untouched.
        ->and($provider->defaultImageOptions('512x512'))->toBe(['size' => '512x512']);
});

test('the image model falls back to a default instead of refusing the selection', function () {
    $provider = new OpenAiImageProvider(
        ['name' => 'pollinations', 'driver' => 'openai-image', 'key' => 'k', 'url' => 'https://x.test/v1'],
        app(Dispatcher::class),
    );

    // Throwing here would surface as "image generation is unavailable", the very
    // message that hides a provider which is in fact configured for images.
    expect($provider->defaultImageModel())->toBe('gpt-image-2');
});

test('a text model is still required when text is asked for', function () {
    $provider = new OpenAiImageProvider(
        ['name' => 'pollinations', 'driver' => 'openai-image', 'key' => 'k', 'url' => 'https://x.test/v1'],
        app(Dispatcher::class),
    );

    $provider->defaultTextModel();
})->throws(InvalidArgumentException::class);

test('owner qualified model ids are kept exactly as the endpoint serves them', function () {
    declareImageProviders(json_encode([
        ['name' => 'pollinations', 'url' => 'https://gen.pollinations.ai/v1', 'key' => 'k'],
    ]));

    Http::fake([
        'gen.pollinations.ai/v1/models' => Http::response(['data' => [
            ['id' => 'openai/gpt-image-2'],
            ['id' => 'black-forest-labs/flux.1.1-pro'],
        ]]),
    ]);

    $models = app(CustomModels::class)->discover('pollinations');

    // Trimming the owner would produce an id the endpoint does not serve.
    expect(collect($models)->pluck('id')->all())->toBe([
        'black-forest-labs/flux.1.1-pro',
        'openai/gpt-image-2',
    ]);
});

test('a bare array keyed on name is read as a catalogue too', function () {
    declareImageProviders(json_encode([
        ['name' => 'pollinations', 'url' => 'https://gen.pollinations.ai/v1', 'key' => 'k'],
    ]));

    // This is the shape /image/models serves, rather than the OpenAI envelope.
    config(['whale.custom_providers_models_path' => '/image/models']);

    Http::fake([
        'gen.pollinations.ai/v1/image/models' => Http::response([
            ['name' => 'openai/gpt-image-2', 'title' => 'GPT Image 2'],
            ['name' => 'tongyi-mai/z-image-turbo'],
        ]),
    ]);

    $models = app(CustomModels::class)->discover('pollinations');

    expect(collect($models)->pluck('id')->all())->toBe(['openai/gpt-image-2', 'tongyi-mai/z-image-turbo'])
        ->and($models[0]['label'])->toBe('GPT Image 2');
});

test('an owner prefix is not title cased into the label', function () {
    declareImageProviders(json_encode([
        ['name' => 'pollinations', 'url' => 'https://gen.pollinations.ai/v1', 'key' => 'k'],
    ]));

    Http::fake([
        'gen.pollinations.ai/v1/models' => Http::response(['data' => [
            ['id' => 'black-forest-labs/flux.1.1-pro'],
        ]]),
    ]);

    expect(app(CustomModels::class)->discover('pollinations')[0]['label'])->toBe('Flux.1.1 Pro');
});

test('a plain string list is still read', function () {
    declareImageProviders(json_encode([
        ['name' => 'lmstudio', 'url' => 'http://127.0.0.1:1234/v1', 'key' => 'k'],
    ]));

    Http::fake([
        '127.0.0.1:1234/v1/models' => Http::response(['data' => ['qwen3:4b', 'gemma3:12b']]),
    ]);

    expect(collect(app(CustomModels::class)->discover('lmstudio'))->pluck('id')->all())
        ->toBe(['gemma3:12b', 'qwen3:4b']);
});
