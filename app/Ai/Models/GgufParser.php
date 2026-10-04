<?php

namespace App\Ai\Models;

use RuntimeException;

/**
 * Reads the header of a GGUF file without loading its weights.
 *
 * GGUF files are routinely several gigabytes, so nothing here ever reads the
 * whole file: only the magic, the key/value metadata block and the tensor
 * directory are parsed, all of which sit at the very start of the file. A file
 * descriptor is used instead of file_get_contents for the same reason.
 */
class GgufParser
{
    private const MAGIC = 'GGUF';

    /**
     * Guard rails for a corrupt or hostile header.
     *
     * Array counts are generous because a Qwen-class tokenizer legitimately holds
     * 150k+ entries; payloads are seeked over, never materialised.
     */
    private const MAX_STRING_BYTES = 1 << 20;

    private const MAX_ARRAY_ITEMS = 1 << 24;

    private const MAX_METADATA_KEYS = 4096;

    private const MAX_TENSORS = 1 << 20;

    /**
     * Parse the metadata and tensor names of a GGUF file.
     *
     * @return array{
     *     version: int,
     *     tensor_count: int,
     *     metadata: array<string, mixed>,
     *     tensors: list<string>
     * }
     *
     * @throws RuntimeException when the file is not a readable GGUF.
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
        if ($this->read($handle, 4, $path) !== self::MAGIC) {
            throw new RuntimeException("[{$path}] is not a GGUF file.");
        }

        $version = $this->uint($handle, 4, $path);
        $tensorCount = $this->uint($handle, 8, $path);
        $keyCount = $this->uint($handle, 8, $path);

        if ($keyCount > self::MAX_METADATA_KEYS || $tensorCount > self::MAX_TENSORS) {
            throw new RuntimeException("[{$path}] declares an implausible header.");
        }

        $metadata = [];

        for ($i = 0; $i < $keyCount; $i++) {
            $key = $this->string($handle, $path);
            $type = $this->uint($handle, 4, $path);
            $metadata[$key] = $this->value($handle, $type, $path);
        }

        $tensors = [];

        for ($i = 0; $i < $tensorCount; $i++) {
            $tensors[] = $this->string($handle, $path);
            $dimensions = $this->uint($handle, 4, $path);

            if ($dimensions > 8) {
                throw new RuntimeException("[{$path}] declares an implausible tensor shape.");
            }

            $this->read($handle, (8 * $dimensions) + 4 + 8, $path);
        }

        return [
            'version' => $version,
            'tensor_count' => $tensorCount,
            'metadata' => $metadata,
            'tensors' => $tensors,
        ];
    }

    /**
     * Read a value of the given GGUF type.
     *
     * @param  resource  $handle
     * @return scalar|list<scalar>
     */
    private function value($handle, int $type, string $path): mixed
    {
        return match ($type) {
            0 => $this->uint($handle, 1, $path),                       // uint8
            1 => $this->signed($handle, 1, $path),                     // int8
            2 => $this->uint($handle, 2, $path),                       // uint16
            3 => $this->signed($handle, 2, $path),                     // int16
            4 => $this->uint($handle, 4, $path),                       // uint32
            5 => $this->signed($handle, 4, $path),                     // int32
            6 => $this->float($handle, 4, $path),                      // float32
            7 => $this->uint($handle, 1, $path) === 1,                 // bool
            8 => $this->string($handle, $path),                        // string
            10 => $this->uint($handle, 8, $path),                      // uint64
            11 => $this->signed($handle, 8, $path),                    // int64
            12 => $this->float($handle, 8, $path),                     // float64
            9 => $this->array($handle, $path),                         // array
            default => throw new RuntimeException("[{$path}] uses unknown GGUF value type [{$type}]."),
        };
    }

    /**
     * Summarise an array and step over its payload.
     *
     * A model's tokenizer alone can run to well over a hundred thousand
     * entries, so values are counted and skipped rather than read into memory:
     * nothing in the catalog derives from token lists, and materialising them
     * would turn a cheap header read into a slow one.
     *
     * @param  resource  $handle
     * @return array{count: int, element_type: int}
     */
    private function array($handle, string $path): array
    {
        $elementType = $this->uint($handle, 4, $path);
        $count = $this->uint($handle, 8, $path);

        if ($count > self::MAX_ARRAY_ITEMS) {
            throw new RuntimeException("[{$path}] declares an implausible array length.");
        }

        $this->skip($handle, $elementType, $count, $path, 1);

        return ['count' => $count, 'element_type' => $elementType];
    }

    /**
     * @param  resource  $handle
     */
    private function string($handle, string $path): string
    {
        $length = $this->uint($handle, 8, $path);

        if ($length > self::MAX_STRING_BYTES) {
            throw new RuntimeException("[{$path}] declares an implausible string length.");
        }

        return $this->read($handle, $length, $path);
    }

    /**
     * Read a little-endian unsigned integer of the given byte width.
     *
     * @param  resource  $handle
     */
    private function uint($handle, int $bytes, string $path): int
    {
        $raw = $this->read($handle, $bytes, $path);
        $value = 0;

        for ($i = $bytes - 1; $i >= 0; $i--) {
            $value = ($value << 8) | ord($raw[$i]);
        }

        return $value;
    }

    /**
     * Read a little-endian signed integer of the given byte width.
     *
     * @param  resource  $handle
     */
    private function signed($handle, int $bytes, string $path): int
    {
        $value = $this->uint($handle, $bytes, $path);
        $signBit = 1 << ($bytes * 8 - 1);

        return $value >= $signBit ? $value - ($signBit << 1) : $value;
    }

    /**
     * @param  resource  $handle
     */
    private function float($handle, int $bytes, string $path): float
    {
        $raw = $this->read($handle, $bytes, $path);
        $unpacked = unpack($bytes === 4 ? 'Gvalue' : 'Evalue', strrev($raw));

        return $unpacked === false ? 0.0 : (float) $unpacked['value'];
    }

    /**
     * @param  resource  $handle
     */
    private function read($handle, int $bytes, string $path): string
    {
        if ($bytes <= 0) {
            return '';
        }

        $data = fread($handle, $bytes);

        if ($data === false || strlen($data) !== $bytes) {
            throw new RuntimeException("[{$path}] ended unexpectedly while reading its header.");
        }

        return $data;
    }

    /**
     * Step over a run of values without reading them.
     *
     * Returns the number of bytes consumed so nested arrays can account for the
     * element type they declare.
     *
     * @param  resource  $handle
     */
    private function skip($handle, int $type, int $count, string $path, int $depth): int
    {
        if ($count === 0) {
            return 0;
        }

        if ($depth > 4) {
            throw new RuntimeException("[{$path}] nests arrays too deeply.");
        }

        return match ($type) {
            0, 1, 7 => $this->advance($handle, 1 * $count),              // uint8, int8, bool
            2, 3 => $this->advance($handle, 2 * $count),                 // uint16, int16
            4, 5, 6 => $this->advance($handle, 4 * $count),              // uint32, int32, float32
            10, 11, 12 => $this->advance($handle, 8 * $count),           // uint64, int64, float64
            8 => $this->advance($handle, $this->skipStrings($handle, $count, $path)),
            9 => $this->skipArray($handle, $count, $path, $depth),
            default => throw new RuntimeException("[{$path}] uses unknown GGUF value type [{$type}]."),
        };
    }

    /**
     * Move the cursor forward, refusing to run off the end of the file.
     *
     * @param  resource  $handle
     */
    private function advance($handle, int $bytes, string $path = ''): int
    {
        if ($bytes === 0) {
            return 0;
        }

        if (fseek($handle, $bytes, SEEK_CUR) !== 0) {
            throw new RuntimeException($path === ''
                ? 'Unable to step over this GGUF value.'
                : "[{$path}] ended unexpectedly while reading its header.");
        }

        return $bytes;
    }

    /**
     * Step over a run of length-prefixed strings.
     *
     * The strings are stepped over one length at a time and the running total is
     * used to land exactly on the next metadata key, since fread() buffers ahead
     * of the logical position.
     *
     * @param  resource  $handle
     */
    private function skipStrings($handle, int $count, string $path): int
    {
        $start = ftell($handle);
        $total = 0;

        for ($i = 0; $i < $count; $i++) {
            $length = $this->uint($handle, 8, $path);

            if ($length > self::MAX_STRING_BYTES) {
                throw new RuntimeException("[{$path}] declares an implausible string length.");
            }

            fseek($handle, $length, SEEK_CUR);
            $total += 8 + $length;
        }

        // Land on the position the layout dictates, undoing any read-ahead.
        if ($start !== false) {
            fseek($handle, $start + $total);
        }

        // The handle is already positioned at the end of the run, so the caller
        // must not seek this many bytes again.
        return 0;
    }

    /**
     * @param  resource  $handle
     */
    private function skipArray($handle, int $count, string $path, int $depth): int
    {
        $elementType = $this->uint($handle, 4, $path);

        return 4 + $this->skip($handle, $elementType, $count, $path, $depth + 1);
    }
}
