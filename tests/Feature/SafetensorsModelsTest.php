<?php

use App\Ai\ModelCatalog;
use App\Ai\Models\LocalModelRegistry;
use App\Ai\Models\SafetensorsParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->use(TestCase::class)->in('Feature');

it('reads tensor names and metadata out of a safetensors header', function () {
    $path = fakeSafetensors('demo', ['model_type' => 'whisper'], [
        'encoder/attn/q',
        'encoder/attn/k',
    ]);

    $parsed = (new SafetensorsParser)->parse($path);

    expect($parsed['tensor_count'])->toBe(2)
        ->and($parsed['metadata']['model_type'])->toBe('whisper')
        ->and($parsed['tensors'])->toContain('encoder/attn/q')
        // The metadata block is not a tensor, so it must not be counted as one.
        ->and($parsed['tensors'])->not->toContain('__metadata__');

    File::delete($path);
});

it('rejects a safetensors file with an implausible header length', function () {
    $path = sys_get_temp_dir().'/bad-'.microtime(true).'.safetensors';
    file_put_contents($path, pack('P', PHP_INT_MAX));

    expect(fn () => (new SafetensorsParser)->parse($path))
        ->toThrow(RuntimeException::class, 'implausible header length');

    File::delete($path);
});

it('rejects a safetensors file that declares no tensors', function () {
    $path = sys_get_temp_dir().'/empty-'.microtime(true).'.safetensors';
    file_put_contents($path, pack('P', 2).'{}');

    expect(fn () => (new SafetensorsParser)->parse($path))
        ->toThrow(RuntimeException::class, 'declares no tensors');

    File::delete($path);
});

it('detects a safetensors file alongside GGUF files in the models directory', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);

    $gguf = fakeGguf('demo-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen2']]);
    File::copy($gguf, $path.'/demo-Q4_K_M.gguf');

    $safetensors = fakeSafetensors('speaker', ['model_type' => 'whisper'], ['encoder/attn/q']);
    File::copy($safetensors, $path.'/speaker.safetensors');

    $models = app(LocalModelRegistry::class)->models();

    expect($models)->toHaveCount(2)
        ->and($models->get('demo-Q4_K_M.gguf')?->format())->toBe('gguf')
        ->and($models->get('speaker.safetensors')?->format())->toBe('safetensors');

    File::delete($gguf);
    File::delete($safetensors);
});

it('rejects a chat message addressed to a local image model', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);
    config(['whale.runtimes.diffusers.url' => 'http://127.0.0.1:8188']);

    $file = fakeGguf('painter-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen_image21']]);
    File::copy($file, $path.'/painter-Q4_K_M.gguf');

    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'provider' => 'local',
        'model' => 'painter-Q4_K_M.gguf',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('model');

    File::delete($file);
});

it('lists partial downloads as unreadable instead of ignoring them', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);

    File::put($path.'/half-finished.gguf.crdownload', 'not enough of a model yet');

    $unreadable = app(LocalModelRegistry::class)->unreadable();

    expect($unreadable)->toHaveCount(1)
        ->and($unreadable[0]['file'])->toBe('half-finished.gguf.crdownload')
        ->and($unreadable[0]['reason']['message'])->toContain('incomplete');
});

it('recognises a Stable Diffusion safetensors checkpoint as an image model', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);

    $file = fakeSafetensors('realistic', [], [
        'model.diffusion_model.input_blocks.0.0.weight',
        'first_stage_model.encoder.conv_in.weight',
        'cond_stage_model.transformer.text_model.embeddings.position_embedding.weight',
    ]);
    File::copy($file, $path.'/realistic.safetensors');

    $model = app(LocalModelRegistry::class)->find('realistic.safetensors');

    expect($model?->architecture())->toBe('stable_diffusion')
        ->and($model?->capabilities())->toBe(['image']);

    File::delete($file);
});

it('reports runtime health on the local-models endpoint only when asked', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);
    config(['whale.runtimes.llama_cpp.url' => 'http://127.0.0.1:8080']);

    $file = fakeGguf('demo-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen2']]);
    File::copy($file, $path.'/demo-Q4_K_M.gguf');

    // A plain list stays cheap: no health probe, so no connection timeouts.
    Http::fake();

    $plain = $this->getJson(route('chat.local-models'))->assertOk()->json();

    Http::assertNothingSent();

    expect($plain)->toHaveKeys(['path', 'models', 'unreadable'])
        ->and($plain)->not->toHaveKey('runtimes');

    // Asked explicitly, only the runtime this file would use is probed.
    Http::fake([
        '127.0.0.1:8080/v1/models' => Http::response(['data' => [['id' => 'demo']]]),
    ]);

    $probed = $this->getJson(route('chat.local-models', ['health' => 1]))->assertOk()->json();

    expect($probed['runtimes'])->toHaveKey('llama_cpp')
        ->and($probed['runtimes']['llama_cpp']['status'])->toBe('online');

    File::delete($file);
});

it('tags a local media model so the picker never offers it for chat', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);
    config(['whale.runtimes.diffusers.url' => 'http://127.0.0.1:8188']);

    $file = fakeGguf('painter-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen_image21']]);
    File::copy($file, $path.'/painter-Q4_K_M.gguf');

    $entry = collect(app(ModelCatalog::class)->providers())
        ->firstWhere('name', 'local')['models'][0] ?? null;

    expect($entry)->not->toBeNull()
        ->and($entry['type'])->toBe('image')
        ->and($entry['media'])->toBeTrue()
        ->and($entry['capabilities'])->toBe(['image']);

    File::delete($file);
});

it('reports a video capability for a text-to-video checkpoint', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);

    $file = fakeGguf('wan-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'wan2.1']]);
    File::copy($file, $path.'/wan-Q4_K_M.gguf');

    $model = app(LocalModelRegistry::class)->find('wan-Q4_K_M.gguf');

    // A video checkpoint must not also claim to be a text model: chatting to
    // it produces an empty reply that reads like a broken runtime.
    expect($model?->capabilities())->toBe(['video']);

    File::delete($file);
});

it('reports a video capability from temporal tensor names alone', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);

    $file = fakeSafetensors(
        'animator',
        [],
        ['diffusion_model.temporal_transformer/attn_q', 'diffusion_model.temporal_transformer/attn_k'],
    );
    File::copy($file, $path.'/animator.safetensors');

    $model = app(LocalModelRegistry::class)->find('animator.safetensors');

    expect($model?->capabilities())->toBe(['video']);

    File::delete($file);
});

it('streams a selected local model through its runtime rather than the AI SDK', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);
    config(['whale.runtimes.llama_cpp.url' => 'http://127.0.0.1:8080']);

    $file = fakeGguf('demo-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen2']]);
    File::copy($file, $path.'/demo-Q4_K_M.gguf');

    Http::fake([
        '127.0.0.1:8080/v1/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"local\"}}]}\n\ndata: [DONE]\n\n",
        ),
    ]);

    $response = $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'provider' => 'local',
        'model' => 'demo-Q4_K_M.gguf',
    ]);

    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');

    // The reply has to arrive as protocol events, or the composer never
    // finishes rendering it.
    $body = $response->streamedContent();

    expect($body)->toContain('"type":"text-delta"')
        ->and($body)->toContain('local')
        ->and($body)->toContain('"type":"finish"');

    Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:8080/v1/chat/completions');

    File::delete($file);
});

it('reports a local runtime failure as a protocol error event', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);
    config(['whale.runtimes.llama_cpp.url' => 'http://127.0.0.1:8080']);

    $file = fakeGguf('demo-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen2']]);
    File::copy($file, $path.'/demo-Q4_K_M.gguf');

    Http::fake([
        '127.0.0.1:8080/v1/chat/completions' => Http::response([
            'error' => ['message' => 'the model failed to load'],
        ], 500),
    ]);

    $response = $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'provider' => 'local',
        'model' => 'demo-Q4_K_M.gguf',
    ]);

    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');

    // A failed runtime must still close the protocol, or the composer waits
    // forever on a terminal event the failure already made impossible.
    $body = $response->streamedContent();

    expect($body)->toContain('"type":"error"')
        ->and($body)->toContain('the model failed to load')
        ->and($body)->toContain('"type":"finish"');

    File::delete($file);
});

it('rejects a local model that no runtime is serving', function () {
    $path = sys_get_temp_dir().'/whale-models-'.microtime(true);
    withModelsPath($path);
    config(['whale.runtimes.llama_cpp.url' => null]);

    $file = fakeGguf('demo-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen2']]);
    File::copy($file, $path.'/demo-Q4_K_M.gguf');

    // Resolution happens before anything is sent, so a model with no runtime is
    // a validation error rather than a stream that fails halfway.
    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'provider' => 'local',
        'model' => 'demo-Q4_K_M.gguf',
    ])->assertStatus(422);

    File::delete($file);
});
