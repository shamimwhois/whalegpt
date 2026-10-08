<?php

namespace App\Ai;

use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Providers\Concerns\GeneratesImages;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasImageGateway;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

/**
 * An OpenAI-compatible endpoint that also serves images.
 *
 * The SDK's own openai-compatible driver deliberately implements only text,
 * embeddings and transcription: an endpoint that speaks /chat/completions
 * cannot be assumed to serve /images/generations, so the capability is left
 * off. That is the right default for a local chat runtime, but it leaves an
 * endpoint that genuinely serves images -- Pollinations, LM Studio's image
 * models, a vLLM deployment with an image model, a gateway fronting either --
 * with no way to be selected for them.
 *
 * This provider is that missing combination: the same OpenAI request shape for
 * both, pointed at a base URL and key supplied in configuration. It composes the
 * SDK's own OpenAiGateway rather than reimplementing the request, so bearer
 * auth, base-URL resolution, b64_json decoding, usage extraction and the error
 * wrapper all behave exactly as they do for a built-in provider. Only the
 * capability surface is new.
 */
class OpenAiImageProvider extends Provider implements ImageProvider, TextProvider
{
    use GeneratesImages;
    use GeneratesText;
    use HasImageGateway;
    use HasTextGateway;
    use StreamsText;

    /**
     * Create a new provider instance.
     */
    public function __construct(protected array $config, Dispatcher $events)
    {
        parent::__construct(new OpenAiGateway($events), $config, $events);
    }

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string
    {
        return $this->config['models']['text']['default'] ?? throw new InvalidArgumentException(
            "The [{$this->name()}] provider requires a default text model. Set [models.text.default] in its configuration or pass a model explicitly."
        );
    }

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string
    {
        return $this->config['models']['text']['cheapest'] ?? $this->defaultTextModel();
    }

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string
    {
        return $this->config['models']['text']['smartest'] ?? $this->defaultTextModel();
    }

    /**
     * Get the name of the default image model.
     *
     * Unlike text, this falls back to a usable default rather than throwing.
     * An endpoint reached through this provider was declared as one that serves
     * images, and refusing at model-resolution time would turn that declaration
     * into the "image generation is unavailable" message the picker already
     * shows for a provider with no image model configured.
     */
    public function defaultImageModel(): string
    {
        return $this->config['models']['image']['default'] ?? 'gpt-image-2';
    }

    /**
     * Get the default / normalized image options for the provider.
     *
     * @param  'low'|'medium'|'high'|null  $quality
     */
    public function defaultImageOptions(?string $size = null, ?string $quality = null): array
    {
        return array_filter([
            'size' => match ($size) {
                '1:1' => '1024x1024',
                '2:3' => '1024x1536',
                '3:2' => '1536x1024',
                default => $size,
            },
            'quality' => $quality,
        ]);
    }
}
