<?php

namespace App\Ai\Models;

use RuntimeException;

/**
 * Reads the header of a safetensors file without loading its weights.
 *
 * A safetensors file is laid out as a little-endian 64-bit header length, that
 * many bytes of JSON describing every tensor, and then the tensor data itself.
 * So the whole description of a model lives in a single JSON object that is
 * almost always far smaller than a megabyte, even when the weights it precedes
 * run to gigabytes: only that object is read here, never the data.
 *
 * The tensor count is taken from the object's own keys rather than from a
 * count stored inside it, because the format has no such field and every key
 * except the optional "__metadata__" entry is a tensor.
 */
class SafetensorsParser
{
    /**
     * The key under which the format stores free-form string metadata.
     */
    private const METADATA_KEY = '__metadata__';

    /**
     * How large a header block may be before the file is treated as hostile.
     *
     * A Qwen-class tokenizer does not appear in a safetensors header at all, so
     * real headers are comfortably under a megabyte; anything vastly larger is
     * a corrupt length rather than a model.
     */
    private const MAX_HEADER_BYTES = 1 << 24;

    /**
     * How many tensors a header may declare.
     */
    private const MAX_TENSORS = 1 << 20;

    /**
     * Parse the metadata and tensor names of a safetensors file.
     *
     * @return array{
     *     version: int,
     *     tensor_count: int,
     *     metadata: array<string, mixed>,
     *     tensors: list<string>
     * }
     *
     * @throws RuntimeException when the file is not a readable safetensors file.
     */
    public function parse(string $path): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open [{$path}].");
        }

        try {
            return $this->parseHandle($handle, $path);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array{version: int, tensor_count: int, metadata: array<string, mixed>, tensors: list<string>}
     */
    private function parseHandle($handle, string $path): array
    {
        $length = $this->uint($handle, 8, $path);

        if ($length === 0 || $length > self::MAX_HEADER_BYTES) {
            throw new RuntimeException("[{$path}] declares an implausible header length.");
        }

        $header = $this->read($handle, (int) $length, $path);

        $decoded = json_decode($header, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("[{$path}] does not hold a readable safetensors header.");
        }

        $metadata = $this->metadata($decoded);

        $tensors = array_values(array_filter(
            array_keys($decoded),
            fn (mixed $key): bool => is_string($key) && $key !== self::METADATA_KEY,
        ));

        if (count($tensors) > self::MAX_TENSORS) {
            throw new RuntimeException("[{$path}] declares an implausible tensor count.");
        }

        // A JSON object with no tensors is a file that lost its weights, which is
        // not a model anything can serve.
        if ($tensors === []) {
            throw new RuntimeException("[{$path}] declares no tensors.");
        }

        return [
            // safetensors has no version field of its own. The header length
            // prefix is its only structural marker, so 1 stands for "read it".
            'version' => 1,
            'tensor_count' => count($tensors),
            'metadata' => $metadata,
            'tensors' => $tensors,
        ];
    }

    /**
     * The optional free-form metadata block, with non-string values dropped.
     *
     * The format defines this block as a map of strings to strings. Anything
     * else is ignored rather than reported, because a settings screen has no
     * use for it and surfacing a misread value would be a worse lie.
     *
     * @param  array<array-key, mixed>  $header
     * @return array<string, mixed>
     */
    private function metadata(array $header): array
    {
        $metadata = $header[self::METADATA_KEY] ?? null;

        if (! is_array($metadata)) {
            return [];
        }

        return array_filter(
            $metadata,
            fn (mixed $value): bool => is_string($value) || is_numeric($value),
        );
    }

    /**
     * Read a little-endian unsigned integer of the given byte width.
     *
     * safetensors is little-endian by specification, unlike GGUF's mixed widths,
     * so the whole read is one unpack of the exact length.
     *
     * @param  resource  $handle
     */
    private function uint($handle, int $bytes, string $path): int
    {
        return match ($bytes) {
            1 => unpack('C', $this->read($handle, 1, $path))[1],
            2 => unpack('v', $this->read($handle, 2, $path))[1],
            4 => unpack('V', $this->read($handle, 4, $path))[1],
            8 => unpack('P', $this->read($handle, 8, $path))[1],
            default => throw new RuntimeException("[{$path}] has an unsupported integer width."),
        };
    }

    /**
     * @param  resource  $handle
     */
    private function read($handle, int $bytes, string $path): string
    {
        $data = fread($handle, $bytes);

        if ($data === false || strlen($data) !== $bytes) {
            throw new RuntimeException("[{$path}] ended unexpectedly while reading its header.");
        }

        return $data;
    }
}
