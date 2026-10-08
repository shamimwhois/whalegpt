<?php

namespace App\Ai\Models;

use Illuminate\Support\Str;

/**
 * A safetensors model file discovered in the models directory.
 *
 * The format carries almost none of GGUF's self-description: there is no
 * "general.architecture" key and no quantisation field, only whatever the
 * exporter happened to write into the free-form metadata block. So the
 * architecture is recovered from that block where possible and otherwise
 * inferred from the shape of the tensor names, and the answer is reported as
 * the inference it is.
 */
class SafetensorsModel extends LocalModel
{
    /**
     * Tensor name prefixes that identify a family when the file does not say.
     *
     * Matched longest first, so a specific prefix is preferred over the generic
     * "transformer" one it nests inside. Entries that share structures go video
     * first: an AnimateDiff checkpoint carries the whole Stable Diffusion UNet
     * plus motion modules, and reporting it as an image model hides the reason
     * it needs a video runtime.
     *
     * @var array<string, list<string>>
     */
    private const TENSOR_PREFIXES = [
        'animatediff' => ['motion_modules.', 'temporal_transformer/'],
        // SDXL and plain Stable Diffusion share the UNet, so the checkable
        // difference is the conditioning: SDXL's dual-encoder "conditioner".
        'sdxl' => ['conditioner.embedders.'],
        'stable_diffusion' => ['model.diffusion_model.', 'first_stage_model.', 'cond_stage_model.'],
        'whistle' => ['encoder/', 'stack/', 'engrams_'],
        'whisper' => ['encoder.conv1.', 'encoder.positional_embedding'],
        'bert' => ['bert.encoder.', 'embeddings.word_embeddings'],
        'llava' => ['vision_tower.', 'image_newline'],
        't5' => ['shared.weight', 'encoder.block.0'],
        'gpt2' => ['transformer.wte', 'transformer.h.0'],
    ];

    public function format(): string
    {
        return 'safetensors';
    }

    /**
     * The model family, recovered from the metadata or the tensor names.
     */
    public function architecture(): string
    {
        $declared = $this->metadata['model_type']
            ?? $this->metadata['general.architecture']
            ?? $this->jsonMetadata('config')['model_type']
            ?? null;

        if (is_string($declared) && $declared !== '') {
            return $declared;
        }

        return $this->inferArchitecture();
    }

    /**
     * The quantisation, when the exporter recorded one.
     *
     * safetensors has no quantisation field, but exporters often record a
     * bit-width in their metadata. That is reported as the bit width it is,
     * rather than dressed up as a GGUF-style tag that means something else.
     */
    public function quantization(): ?string
    {
        $bits = $this->metadata['weight_bits']
            ?? $this->metadata['quantization']
            ?? $this->jsonMetadata('run')['weight_bits']
            ?? null;

        if (! is_numeric($bits)) {
            return null;
        }

        return $bits.'-bit';
    }

    /**
     * The family implied by the tensor names, when nothing declares one.
     */
    private function inferArchitecture(): string
    {
        $names = collect($this->tensors)->map(fn (string $name): string => Str::lower($name));

        foreach (self::TENSOR_PREFIXES as $architecture => $needles) {
            foreach ($needles as $needle) {
                if ($names->contains(fn (string $name): bool => str_starts_with($name, Str::lower($needle)))) {
                    return $architecture;
                }
            }
        }

        return 'unknown';
    }
}
