<?php

use App\Ai\ModelCatalog;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Image;
use RuntimeException;

test('a provider with a key but no pinned model still reports its image default', function () {
    // config/ai.php gives Gemini an image fallback matching the SDK provider's
    // own default. Reading configuration alone used to hide this, and the UI then
    // told a correctly configured user that images were unavailable.
    config(['ai.providers.gemini.key' => 'test-key']);

    $catalog = app(ModelCatalog::class);

    expect(config('ai.providers.gemini.models.image.default'))->toBe('gemini-3.1-flash-image')
        ->and($catalog->configuredModel('gemini', 'image'))->toBe('gemini-3.1-flash-image')
        ->and($catalog->supports('gemini', 'image'))->toBeTrue();

    $gemini = collect($catalog->providers())->keyBy('name')['gemini'];

    expect($gemini['capabilities'])->toContain('image');
});

test('a capability with neither a model nor a default stays unavailable', function () {
    expect(app(ModelCatalog::class)->configuredModel('gemini', 'transcription'))->toBeNull()
        ->and(app(ModelCatalog::class)->supports('gemini', 'transcription'))->toBeFalse();
});

test('an explicitly configured model is never overridden by the default', function () {
    config(['ai.providers.openai.models.image.default' => 'my-custom-image-model']);

    expect(app(ModelCatalog::class)->configuredModel('openai', 'image'))->toBe('my-custom-image-model');
});

test('a failing image provider reports the reason rather than a generic setup message', function () {
    Image::fake(fn () => throw new RuntimeException('quota exceeded'));

    $response = $this->postJson(route('chat.image'), ['prompt' => 'A mountain valley']);

    $response->assertStatus(503);

    // The old message told a user with a working key to go and configure one.
    expect($response->json('message'))
        ->toContain('quota exceeded')
        ->not->toContain('Configure a provider that supports images');
});

test('a rate-limited provider falls back to another capable one', function () {
    config(['ai.providers.gemini' => ['driver' => 'gemini', 'key' => 'k', 'models' => ['image' => ['default' => 'gemini-3.1-flash-image']]]]);
    config(['ai.providers.openai' => ['driver' => 'openai', 'key' => 'k', 'models' => ['image' => ['default' => 'gpt-image-2']]]]);

    Storage::fake('public');

    $attempted = [];

    Image::fake(function ($prompt) use (&$attempted) {
        $attempted[] = $prompt->provider->name();

        if ($prompt->provider->name() === 'gemini') {
            throw new RateLimitedException('quota exhausted');
        }

        // Null makes the fake return a valid placeholder image.
        return null;
    });

    $this->postJson(route('chat.image'), ['prompt' => 'A mountain'])->assertOk();

    expect($attempted)->toBe(['gemini', 'openai']);
});
