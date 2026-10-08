<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build an uploaded PNG. Fake image generation needs the GD extension, which is
 * not guaranteed to be installed, so a real one-pixel image is used instead.
 */
function fakeImageUpload(string $name = 'image.png'): UploadedFile
{
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
    );

    return UploadedFile::fake()->createWithContent($name, $png);
}

/**
 * Write a synthetic GGUF file and return its path.
 *
 * Building the file byte by byte keeps the parser under test independent of the
 * multi-gigabyte models that happen to be sitting in the models directory, and
 * lets a test declare an intentionally corrupt header.
 *
 * @param  array<string, array{type: int, value: mixed}>  $metadata
 * @param  list<string>  $tensors
 */
function fakeGguf(string $name, array $metadata, array $tensors = []): string
{
    $path = sys_get_temp_dir().'/whale-test-'.getmypid().'-'.substr(sha1($name.microtime(true)), 0, 8).'.gguf';

    $bytes = 'GGUF'.pack('V', 3).pack('P', count($tensors)).pack('P', count($metadata));

    foreach ($metadata as $key => $definition) {
        $bytes .= pack('P', strlen((string) $key)).$key.pack('V', $definition['type']);

        $bytes .= match ($definition['type']) {
            8 => pack('P', strlen((string) $definition['value'])).$definition['value'],
            4 => pack('V', (int) $definition['value']),
            12 => pack('E', (float) $definition['value']),
            9 => fakeGgufStringArray((array) $definition['value']),
            default => throw new InvalidArgumentException("Unsupported fixture type [{$definition['type']}]."),
        };
    }

    foreach ($tensors as $tensor) {
        $bytes .= pack('P', strlen($tensor)).$tensor.pack('V', 1).pack('P', 8).pack('V', 0).pack('P', 0);
    }

    file_put_contents($path, $bytes);

    return $path;
}

/**
 * Encode a GGUF array of strings: element type, count, then interleaved
 * (length, bytes) pairs.
 *
 * @param  list<string>  $values
 */
function fakeGgufStringArray(array $values): string
{
    $bytes = pack('V', 8).pack('P', count($values));

    foreach ($values as $value) {
        $bytes .= pack('P', strlen((string) $value)).$value;
    }

    return $bytes;
}

/**
 * Point the models path at a scratch directory that is removed after the test.
 *
 * Declared here rather than in a single test file so every suite that writes
 * model fixtures can reach it, whichever file is run on its own.
 */
function withModelsPath(string $path): void
{
    config(['whale.models_path' => $path]);

    File::ensureDirectoryExists($path);

    afterEach(fn () => File::deleteDirectory($path));
}

/**
 * Write a synthetic safetensors file and return its path.
 *
 * The real format is an 8-byte little-endian header length, that many bytes of
 * JSON describing the tensors, and then the data. The fixture omits the data
 * entirely, because everything under test reads only the header.
 *
 * @param  array<string, mixed>  $metadata
 * @param  list<string>  $tensors
 */
function fakeSafetensors(string $name, array $metadata = [], array $tensors = ['encoder/block/0/attn']): string
{
    $path = sys_get_temp_dir().'/whale-test-'.getmypid().'-'.substr(sha1($name.microtime(true)), 0, 8).'.safetensors';

    $header = [];

    foreach ($tensors as $index => $tensor) {
        $header[$tensor] = ['dtype' => 'F32', 'shape' => [4], 'data_offsets' => [$index * 16, ($index + 1) * 16]];
    }

    if ($metadata !== []) {
        $header['__metadata__'] = $metadata;
    }

    $json = json_encode($header, JSON_THROW_ON_ERROR);

    file_put_contents($path, pack('P', strlen($json)).$json);

    return $path;
}
