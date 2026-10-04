<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Audio;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\AudioPrompt;
use Laravel\Ai\Prompts\ImagePrompt;
use RuntimeException;

test('an image is generated and stored on the public disk', function () {
    Storage::fake('public');
    config()->set('ai.default_for_images', 'openai');
    Image::fake();

    $response = $this->postJson(route('chat.image'), [
        'prompt' => 'A mountain valley at sunrise',
        'size' => 'landscape',
    ]);

    $response->assertOk()->assertJsonStructure(['url', 'path']);

    expect($response->json('path'))->toStartWith('ai/images/')
        ->and(Storage::disk('public')->exists($response->json('path')))->toBeTrue();

    Image::assertGenerated(fn (ImagePrompt $prompt): bool => $prompt->prompt === 'A mountain valley at sunrise'
        && $prompt->size === '3:2');
});

test('the image prompt is required', function () {
    Image::fake()->preventStrayImages();

    $this->postJson(route('chat.image'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prompt');
});

test('the image size must be a known preset', function () {
    Image::fake()->preventStrayImages();

    $this->postJson(route('chat.image'), [
        'prompt' => 'A mountain valley',
        'size' => 'panorama',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('size');
});

test('an unavailable image provider is reported as a service issue', function () {
    Image::fake(fn () => throw new RuntimeException('No image provider is configured.'));

    $this->postJson(route('chat.image'), ['prompt' => 'A mountain valley'])
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});

test('speech is synthesized and stored on the public disk', function () {
    Storage::fake('public');
    config()->set('ai.default_for_audio', 'openai');
    Audio::fake();

    $response = $this->postJson(route('chat.audio'), [
        'text' => 'Here is what I found.',
        'voice' => 'female',
    ]);

    $response->assertOk()->assertJsonStructure(['url', 'path']);

    expect($response->json('path'))->toStartWith('ai/audio/')
        ->and(Storage::disk('public')->exists($response->json('path')))->toBeTrue();

    Audio::assertGenerated(fn (AudioPrompt $prompt): bool => $prompt->text === 'Here is what I found.'
        && $prompt->voice === 'default-female');
});

test('speech text is required and limited to 4000 characters', function () {
    Audio::fake()->preventStrayAudio();

    $this->postJson(route('chat.audio'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');

    $this->postJson(route('chat.audio'), ['text' => str_repeat('a', 4001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');
});

test('an unavailable speech provider is reported as a service issue', function () {
    Audio::fake(fn () => throw new RuntimeException('No speech provider is configured.'));

    $this->postJson(route('chat.audio'), ['text' => 'Read this out loud.'])
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});

test('ocr returns the text a vision model extracts', function () {
    AnonymousAgent::fake(['Invoice 42 — total 199.00']);

    $this->post(route('chat.ocr'), [
        'image' => fakeImageUpload('invoice.png'),
    ])
        ->assertOk()
        ->assertExactJson(['text' => 'Invoice 42 — total 199.00']);
});

test('ocr only accepts images', function () {
    AnonymousAgent::fake()->preventStrayPrompts();

    $this->postJson(route('chat.ocr'), [
        'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    AnonymousAgent::assertNeverPrompted();
});

test('an unavailable vision provider is reported as a service issue', function () {
    AnonymousAgent::fake(fn () => throw new RuntimeException('No vision provider is configured.'));

    $this->post(route('chat.ocr'), [
        'image' => fakeImageUpload('invoice.png'),
    ])
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});
