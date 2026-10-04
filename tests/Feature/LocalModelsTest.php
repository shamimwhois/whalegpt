<?php

use App\Ai\Models\GgufModel;
use App\Ai\Models\GgufParser;
use App\Ai\Models\LocalModelRegistry;
use App\Ai\Models\ModelRunner;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->use(TestCase::class)->in('Feature');

/**
 * Point the models path at a scratch directory that is removed after the test.
 */
function withModelsPath(string $path): void
{
    config(['whale.models_path' => $path]);

    File::ensureDirectoryExists($path);

    afterEach(fn () => File::deleteDirectory($path));
}

/**
 * Build a model object directly, bypassing the registry, for unit-level checks.
 *
 * @param  array<string, mixed>  $metadata
 * @param  list<string>  $tensors
 */
function fakeModel(array $metadata = [], array $tensors = [], string $fileName = 'demo.gguf'): GgufModel
{
    return new GgufModel(
        id: $fileName,
        path: $fileName,
        fileName: $fileName,
        sizeBytes: 1024,
        version: 3,
        tensorCount: count($tensors),
        metadata: $metadata,
        tensors: $tensors,
    );
}

it('reads the architecture, context and tensor names from a header', function () {
    $path = fakeGguf('demo-Q4_K_M.gguf', [
        'general.architecture' => ['type' => 8, 'value' => 'qwen2'],
        'general.name' => ['type' => 8, 'value' => 'Demo 1.5B'],
        'qwen2.context_length' => ['type' => 4, 'value' => 32768],
        'qwen2.block_count' => ['type' => 4, 'value' => 28],
    ], ['blk.0.attn_q.weight', 'token_embd.weight']);

    $parsed = (new GgufParser)->parse($path);

    expect($parsed['version'])->toBe(3)
        ->and($parsed['metadata']['general.architecture'])->toBe('qwen2')
        ->and($parsed['metadata']['general.name'])->toBe('Demo 1.5B')
        ->and($parsed['metadata']['qwen2.context_length'])->toBe(32768)
        ->and($parsed['tensor_count'])->toBe(2)
        ->and($parsed['tensors'])->toContain('blk.0.attn_q.weight');

    File::delete($path);
});

it('rejects a file that is not a GGUF', function () {
    $path = sys_get_temp_dir().'/not-a-gguf-'.microtime(true).'.gguf';
    file_put_contents($path, 'this is plainly not a model file');

    expect(fn () => (new GgufParser)->parse($path))->toThrow(RuntimeException::class);

    File::delete($path);
});

it('rejects a truncated header instead of returning half a model', function () {
    $path = sys_get_temp_dir().'/truncated-'.microtime(true).'.gguf';

    // Claims 40 metadata keys, then stops.
    file_put_contents($path, 'GGUF'.pack('V', 3).pack('P', 0).pack('P', 40));

    expect(fn () => (new GgufParser)->parse($path))->toThrow(RuntimeException::class);

    File::delete($path);
});

it('summarises a string array instead of loading a whole tokenizer', function () {
    $path = fakeGguf('tokens.gguf', [
        'general.architecture' => ['type' => 8, 'value' => 'qwen2'],
        'tokenizer.ggml.tokens' => ['type' => 9, 'value' => array_map(fn (int $i): string => 'tok'.$i, range(1, 2000))],
        'general.name' => ['type' => 8, 'value' => 'After The Array'],
    ]);

    $parsed = (new GgufParser)->parse($path);

    // The trailing key is only readable if the skip landed on its first byte.
    expect($parsed['metadata']['general.name'])->toBe('After The Array')
        ->and($parsed['metadata']['tokenizer.ggml.tokens'])->toBe(['count' => 2000, 'element_type' => 8]);

    File::delete($path);
});

it('discovers model files in the models directory and profiles them', function () {
    withModelsPath(sys_get_temp_dir().'/whale-models-'.microtime(true));

    $coder = fakeGguf('coder-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen2']]);
    $painter = fakeGguf('painter-Q4_K_M.gguf', ['general.architecture' => ['type' => 8, 'value' => 'qwen_image21']]);

    File::copy($coder, config('whale.models_path').'/coder-Q4_K_M.gguf');
    File::copy($painter, config('whale.models_path').'/painter-Q4_K_M.gguf');

    $registry = app(LocalModelRegistry::class);

    expect($registry->models())->toHaveCount(2)
        ->and($registry->find('coder-Q4_K_M.gguf')?->capabilities())->toBe(['text'])
        ->and($registry->find('painter-Q4_K_M.gguf')?->capabilities())->toBe(['image'])
        ->and($registry->find('coder-Q4_K_M.gguf')?->quantization())->toBe('Q4_K_M');

    File::delete([$coder, $painter]);
});

it('ignores files in the models directory that are not models', function () {
    withModelsPath(sys_get_temp_dir().'/whale-models-'.microtime(true));

    File::put(config('whale.models_path').'/notes.txt', 'hello');
    File::put(config('whale.models_path').'/broken.gguf', 'nope');

    expect(app(LocalModelRegistry::class)->models())->toHaveCount(0);
});

it('does not hide a model that a transient read failure once excluded', function () {
    withModelsPath(sys_get_temp_dir().'/whale-models-'.microtime(true));

    $path = config('whale.models_path').'/flaky-Q4_K_M.gguf';

    // A parser that fails once, then succeeds, stands in for a real transient
    // error: a network share hiccupping, or a half-copied file being finished.
    $parser = Mockery::mock(GgufParser::class);
    $parser->shouldReceive('parse')->once()->andThrow(new RuntimeException('temporarily unavailable'));
    $parser->shouldReceive('parse')->once()->andReturn([
        'version' => 3,
        'tensor_count' => 0,
        'metadata' => ['general.architecture' => 'qwen2'],
        'tensors' => [],
    ]);

    File::put($path, 'GGUF');

    $registry = new LocalModelRegistry(
        new Filesystem,
        $parser,
        app(Repository::class),
    );

    expect($registry->models())->toHaveCount(0);

    // The second scan must retry rather than trust the cached failure.
    expect($registry->models())->toHaveCount(1)
        ->and($registry->find('flaky-Q4_K_M.gguf')?->architecture())->toBe('qwen2');
});

it('reports a broken model file as unreadable rather than as a model', function () {
    withModelsPath(sys_get_temp_dir().'/whale-models-'.microtime(true));

    File::put(config('whale.models_path').'/broken.gguf', 'definitely not a gguf');
    File::put(config('whale.models_path').'/notes.txt', 'ignore me');

    $registry = app(LocalModelRegistry::class);

    expect($registry->models())->toHaveCount(0)
        ->and($registry->unreadable())->toHaveCount(1)
        ->and($registry->unreadable()[0]['file'])->toBe('broken.gguf')
        ->and($registry->unreadable()[0]['reason']['message'])->toContain('GGUF');
});

it('infers a vision tower from tensor names when the architecture is generic', function () {
    $model = fakeModel(
        ['general.architecture' => 'qwen2'],
        ['vision_tower.blocks.0.attn.weight', 'blk.0.attn_q.weight'],
    );

    expect($model->capabilities())->toContain('vision');
});

it('does not offer an image model as a text model', function () {
    $model = fakeModel(['general.architecture' => 'qwen_image21'], [], 'painter-Q4_K_M.gguf');

    expect($model->capabilities())->toBe(['image'])
        ->and($model->capabilities())->not->toContain('text');
});

it('reads the quantisation from the file name, not the format version', function () {
    $model = fakeModel(
        ['general.architecture' => 'qwen2', 'general.quantization_version' => 2],
        [],
        'model-Q4_K_M.gguf',
    );

    expect($model->quantization())->toBe('Q4_K_M');
});

it('reports a runtime with no URL as unconfigured rather than ready', function () {
    config(['whale.runtimes.llama_cpp.url' => null]);

    $health = app(ModelRunner::class)->health('llama_cpp');

    expect($health['status'])->toBe('unconfigured')
        ->and($health['detail'])->toContain('llama-server');
});

it('reports a runtime that answers its probe as online and lists what it serves', function () {
    config(['whale.runtimes.llama_cpp.url' => 'http://127.0.0.1:8080']);

    Http::fake([
        '127.0.0.1:8080/v1/models' => Http::response([
            'data' => [['id' => 'qwen2:latest'], ['id' => 'deepseek-r1']],
        ]),
    ]);

    $health = app(ModelRunner::class)->health('llama_cpp');

    expect($health['status'])->toBe('online')
        ->and($health['models'])->toBe(['qwen2:latest', 'deepseek-r1']);
});

it('reports a runtime that is not listening as unreachable', function () {
    config(['whale.runtimes.llama_cpp.url' => 'http://127.0.0.1:9']);

    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(app(ModelRunner::class)->health('llama_cpp')['status'])->toBe('unreachable');
});

it('refuses to stream from a model no runtime supports', function () {
    config(['whale.runtimes' => []]);

    $model = fakeModel(['general.architecture' => 'qwen2']);

    expect(fn () => app(ModelRunner::class)->stream($model, [['role' => 'user', 'content' => 'hi']])->current())
        ->toThrow(RuntimeException::class, 'No configured runtime');
});

it('streams text deltas from an OpenAI compatible runtime', function () {
    config(['whale.runtimes.llama_cpp.url' => 'http://127.0.0.1:8080']);

    $seen = null;

    Http::fake(function ($request) use (&$seen) {
        $seen = $request->url();

        return Http::response(implode("\n", [
            'data: {"choices":[{"delta":{"content":"Hel"}}]}',
            'data: {"choices":[{"delta":{"content":"lo"}}]}',
            '',
            'data: [DONE]',
        ]));
    });

    $model = fakeModel(['general.architecture' => 'qwen2']);

    $deltas = iterator_to_array(
        app(ModelRunner::class)->stream($model, [['role' => 'user', 'content' => 'hi']])
    );

    expect($deltas)->toBe(['Hel', 'lo'])
        ->and($seen)->toBe('http://127.0.0.1:8080/v1/chat/completions');
});

it('surfaces a runtime error instead of streaming nothing', function () {
    config(['whale.runtimes.llama_cpp.url' => 'http://127.0.0.1:8080']);

    Http::fake([
        'http://127.0.0.1:8080/v1/chat/completions' => Http::response(
            ['error' => ['message' => 'model not loaded']],
            503,
        ),
    ]);

    $model = fakeModel(['general.architecture' => 'qwen2']);

    expect(fn () => app(ModelRunner::class)->stream($model, [])->current())
        ->toThrow(RuntimeException::class, 'model not loaded');
});
