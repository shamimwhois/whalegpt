<?php

namespace App\Ai\Models;

/**
 * A local model file discovered in the models directory.
 *
 * GGUF carries no capability flags, so what a model can do is inferred from
 * its architecture and from the names of its tensors. Every inference is
 * reported with the evidence behind it, because a guess is exactly what a
 * settings screen must not present as fact.
 */
class GgufModel extends LocalModel
{
    public function format(): string
    {
        return 'gguf';
    }

    /**
     * The model family, for example "qwen_image21".
     */
    public function architecture(): string
    {
        $architecture = $this->metadata['general.architecture'] ?? 'unknown';

        return is_string($architecture) ? $architecture : 'unknown';
    }

    /**
     * The quantisation the weights are stored in.
     *
     * Read from the filename rather than the header: the metadata key
     * general.quantization_version describes the GGUF format revision, not the
     * quantisation, so deriving "Q2" from it would be a lie about the file.
     */
    public function quantization(): ?string
    {
        if (preg_match('/-(Q\d+_[A-Z0-9_]+|IQ\d_[A-Z0-9]+|F16|F32|BF16|TQ\d+_\d+)\.gguf$/i', $this->fileName, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }
}
