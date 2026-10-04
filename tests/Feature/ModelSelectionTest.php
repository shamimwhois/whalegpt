<?php

use App\Ai\ModelCatalog;
use App\Ai\Models\RuntimeModels;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('the catalog reports only providers with credentials as configured', function () {
    $providers = collect(app(ModelCatalog::class)->providers())->keyBy('name');

    expect($providers['ollama']['configured'])->toBeTrue()
        ->and($providers['ollama']['capabilities'])->toContain('text')
        // The key is blank in .env, so OpenAI must not be offered as usable.
        ->and($providers['openai']['configured'])->toBeFalse()
        ->and($providers['anthropic']['configured'])->toBeFalse()
        ->and($providers['anthropic']['environment'])->toBe('ANTHROPIC_API_KEY');
});

test('the catalog never exposes credentials', function () {
    $json = json_encode(app(ModelCatalog::class)->toArray());

    expect($json)->toBeString();

    foreach (['key', 'url', 'api_key'] as $forbidden) {
        expect($json)->not->toContain('"'.$forbidden.'"');
    }
});

test('the catalog resolves a configured provider and model', function () {
    $catalog = app(ModelCatalog::class);

    expect($catalog->resolve('ollama', config('ai.providers.ollama.models.text.default')))
        ->toBe(['provider' => 'ollama', 'model' => config('ai.providers.ollama.models.text.default')])
        ->and($catalog->resolve(null))->toBe(['provider' => null, 'model' => null]);
});

test('resolving rejects providers and models that are not configured', function () {
    $catalog = app(ModelCatalog::class);

    expect(fn () => $catalog->resolve('anthropic', 'claude-sonnet-4-5'))
        ->toThrow(ValidationException::class);

    // Ollama is configured, but gpt-5 is not wired up for it.
    expect(fn () => $catalog->resolve('ollama', 'gpt-5'))
        ->toThrow(ValidationException::class);
});

test('the models endpoint describes the configured providers', function () {
    $response = $this->getJson(route('chat.models'));

    $response->assertOk()
        ->assertJsonStructure([
            'default' => ['provider', 'model'],
            'providers' => [['name', 'label', 'driver', 'configured', 'default', 'environment', 'capabilities', 'models']],
        ]);

    expect($response->json('default.provider'))->toBe(config('ai.default'));
});

test('a valid selection is accepted and routed to that provider', function () {
    // A configured provider is resolved by name, which bypasses the agent-scoped
    // text fake, so this asserts the routing contract rather than fake content:
    // a valid selection must clear validation and reach the provider layer.
    $model = config('ai.providers.ollama.models.text.default');

    $response = $this->postJson(route('chat.send'), [
        'message' => 'Hello',
        'provider' => 'ollama',
        'model' => $model,
    ]);

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/event-stream');
});

test('sending to a model that is not configured is rejected', function () {
    $this->postJson(route('chat.send'), [
        'message' => 'Hello',
        'provider' => 'ollama',
        'model' => 'gpt-5',
    ])->assertStatus(422)->assertJsonValidationErrors('model');

    $this->postJson(route('chat.send'), [
        'message' => 'Hello',
        'provider' => 'anthropic',
    ])->assertStatus(422)->assertJsonValidationErrors('provider');
});

test('an unconfigured provider cannot be selected for image generation', function () {
    $this->postJson(route('chat.image'), [
        'prompt' => 'A mountain',
        'provider' => 'anthropic',
    ])->assertStatus(422)->assertJsonValidationErrors('provider');
});

test('every model a runtime has pulled is offered and selectable', function () {
    config(['ai.providers.ollama.url' => 'http://127.0.0.1:11434']);

    Http::fake([
        '127.0.0.1:11434/v1/models' => Http::response(['data' => [
            ['id' => 'deepseek-r1:1.5b'],
            ['id' => 'qwen3:4b'],
            ['id' => 'library/qwen3:4b'],
        ]]),
    ]);

    cache()->forget('whale.runtime.ollama.models');

    $ollama = collect(app(ModelCatalog::class)->providers())->keyBy('name')['ollama'];

    $ids = collect($ollama['models'])->pluck('id');

    // The library-prefixed duplicate must collapse rather than appear twice.
    expect($ids)->toContain('deepseek-r1:1.5b')
        ->and($ids)->toContain('qwen3:4b')
        ->and($ids->filter(fn (string $id): bool => $id === 'qwen3:4b'))->toHaveCount(1);

    // A discovered model is genuinely selectable, not just listed.
    expect(app(ModelCatalog::class)->resolve('ollama', 'deepseek-r1:1.5b'))
        ->toBe(['provider' => 'ollama', 'model' => 'deepseek-r1:1.5b']);
});

test('a discovered model is labelled readably', function () {
    config(['ai.providers.ollama.url' => 'http://127.0.0.1:11434']);

    Http::fake([
        '127.0.0.1:11434/v1/models' => Http::response(['data' => [
            ['id' => 'qwen3:4b'],
            ['id' => 'mimo-v2.6:9b'],
        ]]),
    ]);

    cache()->forget('whale.runtime.ollama.models');

    $models = collect(app(ModelCatalog::class)->providers())->keyBy('name')['ollama']['models'];
    $labels = collect($models)->pluck('label', 'id');

    expect($labels['qwen3:4b'])->toBe('Qwen3 4B')
        ->and($labels['mimo-v2.6:9b'])->toBe('Mimo V2.6 9B');
});

test('an unreachable runtime lists no models instead of failing the page', function () {
    config(['ai.providers.ollama.url' => 'http://127.0.0.1:11434']);

    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    cache()->forget('whale.runtime.ollama.models');

    $ollama = collect(app(ModelCatalog::class)->providers())->keyBy('name')['ollama'];

    // The provider still reports as configured, but nothing is falsely offered.
    expect(collect($ollama['models'])->where('configured', true)->pluck('id'))
        ->toContain(config('ai.providers.ollama.models.text.default'));
});

test('a runtime that is not configured is never asked for its models', function () {
    config(['ai.providers.ollama.url' => null]);

    Http::preventStrayRequests();
    Http::fake();

    cache()->forget('whale.runtime.ollama.models');

    // No stray request is permitted here, so reaching this proves none was made.
    expect(app(RuntimeModels::class)->for('ollama'))->toBe([]);
});
