<?php

namespace App\Ai\Models;

use Illuminate\Support\Str;

/**
 * A local model file discovered in the models directory.
 *
 * GGUF carries no capability flags, so what a model can do is inferred from
 * its architecture and from the names of its tensors. Every inference is
 * reported with the evidence behind it, because a guess is exactly what a
 * settings screen must not present as fact.
 */
class GgufModel
{
    /**
     * Architecture fragments that mean the model generates images.
     *
     * @var list<string>
     */
    private const IMAGE_ARCHITECTURES = [
        'qwen_image', 'flux', 'stable_diffusion', 'sdxl', 'sd3', 'hidream',
        'lumina', 'chroma', 'auraflow',
    ];

    /**
     * Architecture fragments for models that *read* images as input.
     *
     * @var list<string>
     */
    private const VISION_ARCHITECTURES = [
        'llava', 'qwen2_vl', 'qwen2vl', 'qwen3_vl', 'qwen3vl', 'minicpmv',
        'gemma3n', 'internvl', 'pixtral', 'idefics', 'mllama', 'moondream',
        'glm4v', 'deepseek_vl', 'smolvlm', 'ovis',
    ];

    /**
     * Architecture fragments for models that read or write audio.
     *
     * @var list<string>
     */
    private const AUDIO_ARCHITECTURES = [
        'whisper', 'sensevoice', 'parakeet', 'mms', 'bark', 'outetts',
        'encodec', 'wav2vec',
    ];

    /**
     * Architecture fragments for embedding models.
     *
     * @var list<string>
     */
    private const EMBEDDING_ARCHITECTURES = ['bert', 'nomic_bert', 'gte', 'e5', 'jina_bert'];

    /**
     * Tensor name fragments that reveal a vision tower in any architecture.
     *
     * @var list<string>
     */
    private const VISION_TENSORS = ['vision_tower', 'vision_model', 'v.patch_embed', 'mmproj', 'image_embd'];

    /**
     * Tensor name fragments that reveal an audio tower.
     *
     * @var list<string>
     */
    private const AUDIO_TENSORS = ['audio_tower', 'mel_filters', 'whisper', 'audio_embd'];

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $tensors
     */
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly string $fileName,
        public readonly int $sizeBytes,
        public readonly int $version,
        public readonly int $tensorCount,
        private readonly array $metadata,
        private readonly array $tensors,
    ) {}

    /**
     * The model family, for example "qwen_image21".
     */
    public function architecture(): string
    {
        $architecture = $this->metadata['general.architecture'] ?? 'unknown';

        return is_string($architecture) ? $architecture : 'unknown';
    }

    /**
     * The human readable name recorded inside the file, falling back to the
     * filename when the file does not name itself.
     */
    public function name(): string
    {
        $name = $this->metadata['general.name'] ?? null;

        return is_string($name) && $name !== ''
            ? $name
            : Str::of($this->fileName)->beforeLast('.')->toString();
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

    /**
     * The context window in tokens, when the file declares one.
     */
    public function contextLength(): ?int
    {
        $architecture = $this->architecture();
        $length = $this->metadata["{$architecture}.context_length"]
            ?? $this->metadata['general.context_length']
            ?? null;

        return is_numeric($length) ? (int) $length : null;
    }

    /**
     * How many layers the architecture declares, when known.
     */
    public function layers(): ?int
    {
        $architecture = $this->architecture();
        $layers = $this->metadata["{$architecture}.block_count"]
            ?? $this->metadata["{$architecture}.n_layer"]
            ?? null;

        return is_numeric($layers) ? (int) $layers : null;
    }

    /**
     * The capabilities inferred for this model.
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        if (! config('whale.detect_capabilities', true)) {
            return ['text'];
        }

        $architecture = Str::lower($this->architecture());
        $tensorNames = Str::lower(implode(' ', $this->tensors));
        $capabilities = [];

        if ($this->matches($architecture, self::IMAGE_ARCHITECTURES)) {
            $capabilities[] = 'image';
        } elseif ($this->matches($architecture, self::VISION_ARCHITECTURES)
            || $this->matches($tensorNames, self::VISION_TENSORS)) {
            $capabilities[] = 'vision';
        }

        if ($this->matches($architecture, self::AUDIO_ARCHITECTURES)
            || $this->matches($tensorNames, self::AUDIO_TENSORS)) {
            $capabilities[] = 'audio';
        }

        if ($this->matches($architecture, self::EMBEDDING_ARCHITECTURES)) {
            $capabilities[] = 'embeddings';
        }

        if (! in_array('image', $capabilities, true)) {
            $capabilities[] = 'text';
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * The local runtime that would serve this model.
     *
     * Image generation is the one capability llama.cpp cannot serve, so it is
     * called out here rather than left to fail on the first request.
     *
     * A model *file* on disk can only be served by a runtime that loads files,
     * so a runtime backed by its own model store is never chosen here. Ollama
     * serves whatever has been pulled into it; pointing it at a path in this
     * directory would fail at the first request with "model not found".
     *
     * "configured" only means a URL is set. Whether the runtime is actually up
     * is a separate question answered by {@see ModelRunner::health()}, which
     * costs a request; conflating the two would let the UI claim a dead runtime
     * is ready.
     *
     * @return array{name: string, supports: list<string>, configured: bool, command: string|null}
     */
    public function runtime(): array
    {
        $capabilities = $this->capabilities();

        foreach ($this->candidateRuntimes() as $name => $runtime) {
            if (! array_intersect($capabilities, $runtime['supports'] ?? [])) {
                continue;
            }

            $configured = filled($runtime['url'] ?? null);

            return [
                'name' => $name,
                'supports' => $runtime['supports'] ?? [],
                'configured' => $configured,
                'command' => $configured ? null : $this->runCommand($name, $capabilities),
            ];
        }

        return [
            'name' => 'none',
            'supports' => [],
            'configured' => false,
            'command' => null,
        ];
    }

    /**
     * The runtimes that can serve a file from this directory, best first.
     *
     * @return array<string, array<string, mixed>>
     */
    private function candidateRuntimes(): array
    {
        $runtimes = array_filter(
            (array) config('whale.runtimes', []),
            'is_array',
        );

        uasort(
            $runtimes,
            fn (array $a, array $b): int => ($b['serves_files'] ?? false) <=> ($a['serves_files'] ?? false),
        );

        return $runtimes;
    }

    /**
     * Whether a runtime has been pointed at for this model.
     *
     * This is a statement about configuration, not health. Use
     * {@see ModelRunner::health()} to find out whether the runtime answers.
     */
    public function isConfigured(): bool
    {
        $runtime = $this->runtime();

        return $runtime['name'] !== 'none' && $runtime['configured'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name(),
            'file' => $this->fileName,
            'architecture' => $this->architecture(),
            'quantization' => $this->quantization(),
            'context_length' => $this->contextLength(),
            'layers' => $this->layers(),
            'tensor_count' => $this->tensorCount,
            'size_bytes' => $this->sizeBytes,
            'gguf_version' => $this->version,
            'capabilities' => $this->capabilities(),
            'runtime' => $this->runtime(),
            'configured' => $this->isConfigured(),
        ];
    }

    /**
     * @param  list<string>  $needles
     */
    private function matches(string $haystack, array $needles): bool
    {
        return collect($needles)
            ->contains(fn (string $needle): bool => str_contains($haystack, Str::lower($needle)));
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function runCommand(string $runtime, array $capabilities): ?string
    {
        if ($runtime === 'llama_cpp') {
            return sprintf('llama-server --model %s --port 8080', $this->fileName);
        }

        return in_array('image', $capabilities, true)
            ? 'Start a diffusers or ComfyUI endpoint and set LOCAL_IMAGE_URL'
            : null;
    }
}
