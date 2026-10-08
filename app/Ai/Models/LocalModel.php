<?php

namespace App\Ai\Models;

use Illuminate\Support\Str;

/**
 * A model file discovered in the local models directory.
 *
 * Both supported formats — GGUF and safetensors — describe a model the same
 * way once their header has been read: a bag of key/value metadata plus the
 * names of the tensors that make it up. Neither carries capability flags, so
 * what a model can do is inferred from that metadata and from the tensor
 * names, and every inference is reported with the evidence behind it, because
 * a guess is exactly what a settings screen must not present as fact.
 *
 * Subclasses only supply what is genuinely format-specific: how to read the
 * header, and which fields to pull the architecture and quantisation out of.
 */
abstract class LocalModel
{
    /**
     * Architecture fragments that mean the model generates video.
     *
     * Checked before images because a video diffusion model is an image model
     * with a temporal axis bolted on, so its architecture names also contain
     * the image fragments. Reporting "image" for a text-to-video checkpoint
     * would send it to a generator that cannot produce frames over time.
     *
     * @var list<string>
     */
    private const VIDEO_ARCHITECTURES = [
        'wan', 'cogvideox', 'cogvideo', 'hunyuan_video', 'hunyuanvideo',
        'mochi', 'ltx_video', 'opengvlab', 'svd', 'animatediff',
        'modelscope_text_to_video', 'videodiffusion', 'i2vgen',
    ];

    /**
     * Architecture fragments that mean the model generates images.
     *
     * @var list<string>
     */
    private const IMAGE_ARCHITECTURES = [
        'qwen_image', 'stable_diffusion', 'sdxl', 'flux', 'sd3', 'hidream',
        'lumina', 'chroma', 'auraflow', 'animatediff',
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
        'encodec', 'wav2vec', 'whistle',
    ];

    /**
     * Architecture fragments for embedding models.
     *
     * @var list<string>
     */
    private const EMBEDDING_ARCHITECTURES = ['bert', 'nomic_bert', 'gte', 'e5', 'jina_bert'];

    /**
     * Tensor name fragments that reveal a temporal (video) axis.
     *
     * Matched anywhere in the tensor list, so a checkpoint whose architecture
     * string was stripped by an exporter still reports what it is.
     *
     * @var list<string>
     */
    private const VIDEO_TENSORS = [
        'temporal_conv', 'temporal_attention', 'time_embedding',
        'video_tower', 'motion_module', 'temporal_transformer',
    ];

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
        protected readonly array $metadata,
        protected readonly array $tensors,
    ) {}

    /**
     * The file format, "gguf" or "safetensors".
     */
    abstract public function format(): string;

    /**
     * The model family, for example "qwen_image21".
     */
    abstract public function architecture(): string;

    /**
     * The quantisation the weights are stored in, when the file reveals one.
     */
    abstract public function quantization(): ?string;

    /**
     * The human readable name recorded inside the file, falling back to the
     * filename when the file does not name itself.
     */
    public function name(): string
    {
        $name = $this->metadata['general.name']
            ?? $this->metadata['name']
            ?? $this->metadata['model_type']
            ?? null;

        return is_string($name) && $name !== ''
            ? $name
            : Str::of($this->fileName)->beforeLast('.')->toString();
    }

    /**
     * The context window in tokens, when the file declares one.
     */
    public function contextLength(): ?int
    {
        $architecture = $this->architecture();
        $length = $this->metadata["{$architecture}.context_length"]
            ?? $this->metadata['general.context_length']
            ?? $this->metadata['max_position_embeddings']
            ?? $this->metadata['context_length']
            ?? $this->numericMetadata('config', 'max_seq_len')
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
            ?? $this->metadata['num_hidden_layers']
            ?? $this->metadata['n_layer']
            ?? $this->numericMetadata('config', 'num_layers')
            ?? $this->numericMetadata('config', 'enc_layers')
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

        // Video is resolved first: a video checkpoint also matches the image
        // fragments, and only the first branch runs.
        if ($this->matches($architecture, self::VIDEO_ARCHITECTURES)
            || $this->matches($tensorNames, self::VIDEO_TENSORS)) {
            $capabilities[] = 'video';
        } elseif ($this->matches($architecture, self::IMAGE_ARCHITECTURES)) {
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

        // A generator is never also a text model: qwen-image does not answer a
        // question, and offering it for chat is how a blank reply looks like a
        // bug in the runtime.
        if (array_intersect(['image', 'video'], $capabilities) === []) {
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
            'format' => $this->format(),
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
     * Read a nested numeric value out of a metadata entry holding JSON.
     *
     * safetensors stores its configuration as one opaque JSON string rather than
     * as separate keys, so the fields a GGUF file would expose as
     * "qwen2.block_count" have to be dug out of it. Returns null when the entry
     * is absent, is not JSON, or does not hold the key, so a malformed file
     * degrades to "unknown" instead of throwing.
     */
    protected function numericMetadata(string $entry, string $key): ?int
    {
        $value = $this->jsonMetadata($entry)[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Decode a metadata entry that holds a JSON document.
     *
     * @return array<string, mixed>
     */
    protected function jsonMetadata(string $entry): array
    {
        $raw = $this->metadata[$entry] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
