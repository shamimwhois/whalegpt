<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ArtAgent;
use App\Ai\ImageStyle;
use App\Ai\ModelCatalog;
use App\Ai\Models\LocalModelRegistry;
use App\Workspace\Workspace;
use App\Workspace\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Ai\Audio;
use Laravel\Ai\Image;
use RuntimeException;
use Throwable;

use function Laravel\Ai\agent;

class MediaGenerationController extends Controller
{
    public function __construct(
        private readonly ModelCatalog $catalog,
        private readonly LocalModelRegistry $registry,
    ) {}

    /**
     * Generate an image from a text prompt and store it on the public disk.
     */
    public function generateImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'size' => ['sometimes', 'nullable', 'in:square,portrait,landscape'],
            'style' => ['sometimes', 'nullable', Rule::in(self::styleIds())],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->resolveFor($validated, 'image');
        $prompt = ImageStyle::apply($validated['prompt'], $validated['style'] ?? null);

        try {
            $path = $this->generateWithFailover($prompt, $validated['size'] ?? null, $selection);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $this->mediaUnavailable('image', $e),
            ], 503);
        }

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    /**
     * Edit an existing image by re-generating it with the image attached.
     *
     * Providers with multimodal image support treat the attached image as
     * source material, so "make the sky purple" changes the uploaded picture
     * rather than starting from scratch.
     */
    public function editImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'prompt' => ['required', 'string', 'max:2000'],
            'size' => ['sometimes', 'nullable', 'in:square,portrait,landscape'],
            'style' => ['sometimes', 'nullable', Rule::in(self::styleIds())],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->resolveFor($validated, 'image');
        $prompt = ImageStyle::apply($validated['prompt'], $validated['style'] ?? null);

        try {
            $pending = Image::of($prompt)->attachments([$request->file('image')]);

            match ($validated['size'] ?? null) {
                'square' => $pending->square(),
                'portrait' => $pending->portrait(),
                'landscape' => $pending->landscape(),
                default => $pending,
            };

            $path = $pending->timeout(120)->generate($selection['provider'], $selection['model'])->storePublicly('ai/images', 'public');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Image editing is unavailable. Configure a provider that supports image editing (OpenAI or Gemini) with an API key in your .env file.',
            ], 503);
        }

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    /**
     * Synthesize speech from a text prompt and store it on the public disk.
     */
    public function generateAudio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'voice' => ['sometimes', 'nullable', 'in:male,female'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->resolveFor($validated, 'audio');

        try {
            $pending = Audio::of($validated['text']);

            if (($validated['voice'] ?? null) === 'male') {
                $pending->male();
            } elseif (($validated['voice'] ?? null) === 'female') {
                $pending->female();
            }

            $path = $pending->timeout(60)->generate($selection['provider'], $selection['model'])->storePublicly('ai/audio', 'public');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Audio generation is unavailable. Configure a provider that supports text-to-speech (OpenAI, ElevenLabs, or Mistral) with an API key in your .env file.',
            ], 503);
        }

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    /**
     * Extract text from an uploaded image (OCR) via a vision-capable model.
     */
    public function ocr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->catalog->resolve($validated['provider'] ?? null, $validated['model'] ?? null);

        $image = $request->file('image');

        try {
            $response = agent(
                instructions: 'You are an OCR engine. Extract every piece of text visible in the image verbatim, preserving line breaks and reading order. Reply with only the extracted text — no commentary.',
            )->prompt(
                'Extract the text from this image.',
                attachments: [$image],
                provider: $selection['provider'],
                model: $selection['model'],
                timeout: 120,
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'OCR is unavailable. Configure a vision-capable provider with an API key in your .env file.',
            ], 503);
        }

        return response()->json(['text' => $response->text]);
    }

    /**
     * Create or edit a vector (SVG) file in the workspace via the art sub-agent.
     *
     * Text-to-image models only return raster output, so vector work is routed
     * to an agent that writes SVG markup directly into the workspace. The file
     * is then openable in the editor and the live preview.
     */
    public function generateVector(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'style' => ['sometimes', 'nullable', Rule::in(self::styleIds())],
            'workspace' => ['sometimes', 'nullable', 'string', 'max:64'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->catalog->resolve($validated['provider'] ?? null, $validated['model'] ?? null);

        $workspace = WorkspaceContext::for($request);

        $path = $validated['path'] ?? 'art/'.Str::slug(Str::limit($validated['prompt'], 40, '')).'.svg';

        if (! Str::endsWith(Str::lower($path), '.svg')) {
            $path .= '.svg';
        }

        try {
            $response = (new ArtAgent($workspace))->prompt(
                "Create vector artwork at the path \"{$path}\" for this brief: "
                .ImageStyle::apply($validated['prompt'], $validated['style'] ?? null)
                .'. '
                .'Write the complete SVG markup to that file with your tool.',
                provider: $selection['provider'],
                model: $selection['model'],
                timeout: 180,
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Vector generation is unavailable. Configure a text provider with an API key in your .env file.',
            ], 503);
        }

        $svg = null;

        try {
            $svg = $workspace->read($path);
        } catch (RuntimeException) {
            // The agent may have chosen a different filename; report what exists.
        }

        return response()->json([
            'path' => $path,
            'svg' => $svg,
            'summary' => $response->text,
            'url' => $svg !== null ? route('chat.workspace.preview', ['path' => $path, 'workspace' => $validated['workspace'] ?? null]) : null,
        ]);
    }

    /**
     * Build a video storyboard: a sequence of generated frames plus an
     * animated HTML player written into the workspace.
     *
     * The SDK has no text-to-video provider, so rather than pretend otherwise
     * this composes stills into a timed, playable HTML animation. The result is
     * a real file the user can preview, edit and export.
     */
    public function generateVideo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'frames' => ['sometimes', 'integer', 'min:2', 'max:6'],
            'seconds_per_frame' => ['sometimes', 'numeric', 'min:0.5', 'max:5'],
            'style' => ['sometimes', 'nullable', Rule::in(self::styleIds())],
            'workspace' => ['sometimes', 'nullable', 'string', 'max:64'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->resolveFor($validated, 'image');

        $frameCount = (int) ($validated['frames'] ?? 4);
        $seconds = (float) ($validated['seconds_per_frame'] ?? 1.5);

        // Every frame gets the same style directive, which is what keeps a
        // storyboard visually consistent across its stills.
        $prompt = ImageStyle::apply($validated['prompt'], $validated['style'] ?? null);

        $workspace = WorkspaceContext::for($request);

        $frames = [];

        try {
            for ($index = 1; $index <= $frameCount; $index++) {
                $path = Image::of(sprintf(
                    '%s — storyboard frame %d of %d, cinematic still, consistent subject and style',
                    $prompt,
                    $index,
                    $frameCount,
                ))
                    ->landscape()
                    ->timeout(120)
                    ->generate($selection['provider'], $selection['model'])
                    ->storePublicly('ai/video-frames', 'public');

                $frames[] = Storage::disk('public')->url($path);
            }
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Video storyboard generation is unavailable. Configure a provider that supports images (OpenAI, Gemini, or xAI) with an API key in your .env file.',
            ], 503);
        }

        $slug = Str::slug(Str::limit($validated['prompt'], 40, '')) ?: 'storyboard';
        $path = "video/{$slug}.html";

        $workspace->write($path, $this->storyboardDocument($validated['prompt'], $frames, $seconds));

        return response()->json([
            'path' => $path,
            'frames' => $frames,
            'url' => route('chat.workspace.preview', ['path' => $path, 'workspace' => $validated['workspace'] ?? null]),
        ]);
    }

    /**
     * The style ids a request may name.
     *
     * "any" is included so the browser can be explicit about wanting no style
     * rather than omitting the field and hoping.
     *
     * @return list<string>
     */
    private static function styleIds(): array
    {
        return array_column(ImageStyle::toArray(), 'id');
    }

    /**
     * Resolve the requested provider and model for a media operation.
     *
     * Image and speech have their own configured models, so a selection made
     * for chat text is not automatically valid here — the provider falls back
     * to its own model for that capability instead of failing.
     *
     * @param  array<string, mixed>  $validated
     * @return array{provider: string|null, model: string|null}
     */
    /**
     * Generate an image, falling back to another capable provider if one fails.
     *
     * A provider being exhausted or throttled is routine, not exceptional: a
     * Gemini key with no image quota would otherwise make the whole feature look
     * broken even when another configured provider could serve it. The requested
     * provider is always tried first, so an explicit choice is honoured, and the
     * last failure is the one reported.
     *
     * @param  array{provider: string|null, model: string|null}  $selection
     */
    private function generateWithFailover(string $prompt, ?string $size, array $selection): string
    {
        $attempts = collect($this->capableProviders('image'))
            ->prepend($selection)
            ->filter(fn (array $attempt): bool => filled($attempt['provider']))
            ->unique(fn (array $attempt): string => (string) $attempt['provider']);

        $last = null;

        foreach ($attempts as $attempt) {
            try {
                $pending = Image::of($prompt);

                match ($size) {
                    'square' => $pending->square(),
                    'portrait' => $pending->portrait(),
                    'landscape' => $pending->landscape(),
                    default => $pending,
                };

                return $pending
                    ->timeout(120)
                    ->generate($attempt['provider'], $attempt['model'])
                    ->storePublicly('ai/images', 'public');
            } catch (Throwable $e) {
                report($e);

                $last = $e;
            }
        }

        throw $last ?? new RuntimeException('No configured provider can generate images.');
    }

    /**
     * Every configured provider that can serve a capability.
     *
     * This reads config/ai.php directly rather than going through the catalog.
     * The catalog also scans the local model directory, which parses multi-gigabyte
     * GGUF headers, and asks each runtime what it holds over HTTP — work that has
     * nothing to do with hosted media providers and would add seconds to every
     * image request.
     *
     * @return list<array{provider: string, model: string|null}>
     */
    private function capableProviders(string $capability): array
    {
        return collect((array) config('ai.providers', []))
            ->filter(fn (mixed $configuration): bool => is_array($configuration))
            ->filter(fn (array $configuration): bool => filled($configuration['key'] ?? $configuration['url'] ?? null))
            ->map(fn (array $configuration, string $name): array => [
                'provider' => $name,
                'model' => $this->catalog->configuredModel($name, $capability),
            ])
            ->filter(fn (array $candidate): bool => filled($candidate['model']))
            ->values()
            ->all();
    }

    /**
     * Explain why a media request failed, naming the actual cause.
     *
     * The previous message told a user who already had a working Gemini key to
     * configure a provider they had configured, which sent them looking in the
     * wrong place. When a provider is present the likely fault is the model id
     * or the quota, so that is what gets reported instead.
     */
    private function mediaUnavailable(string $capability, Throwable $exception): string
    {
        $configured = collect($this->catalog->providers())
            ->filter(fn (array $provider): bool => $provider['configured'])
            ->filter(fn (array $provider): bool => $this->catalog->supports($provider['name'], $capability))
            ->map(fn (array $provider): string => $provider['name'])
            ->values();

        if ($configured->isEmpty()) {
            return sprintf(
                '%s is unavailable: no configured provider offers it. Set an API key and a matching model in your .env file.',
                ucfirst($capability),
            );
        }

        return sprintf(
            '%s failed using %s. A provider is configured, so check the %s model id and the provider quota. (%s)',
            ucfirst($capability),
            $configured->implode(', '),
            $capability,
            $exception->getMessage(),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{provider: string|null, model: string|null}
     */
    private function resolveFor(array $validated, string $capability): array
    {
        $requested = $validated['provider'] ?? null;

        // The default provider is chosen for chat, not for every capability:
        // AI_PROVIDER is frequently a local runtime that only does text, so
        // falling back to it blindly is what made image generation report itself
        // unavailable while a capable provider was configured. Pick one that can
        // actually serve the capability instead.
        if (blank($requested)) {
            $requested = $this->capableProvider($capability) ?? config('ai.default');
        }

        $selection = $this->catalog->resolve($requested, $validated['model'] ?? null);

        // The selection is shared with chat, where a text model is the whole
        // point. Handing that same selection to an image request would ask a
        // checkpoint that cannot paint to paint, so a local model that lacks
        // the capability is dropped and a capable provider is chosen instead.
        if ($this->localModelLacks($selection, $capability)) {
            $requested = $this->capableProvider($capability) ?? config('ai.default');
            $selection = $this->catalog->resolve($requested, null);
        }

        if ($selection['provider'] === null) {
            return $selection;
        }

        if ($selection['model'] === null) {
            return [
                'provider' => $selection['provider'],
                'model' => $this->catalog->configuredModel($selection['provider'], $capability),
            ];
        }

        return $selection;
    }

    /**
     * Whether a resolved selection is a local file that cannot serve a capability.
     *
     * Hosted providers are left alone: their model list already reflects what
     * they are configured for, and resolve() has rejected anything unusable.
     * Only a local file needs this, because its capabilities were inferred from
     * tensors rather than declared, and the same file is offered to chat, the
     * studio and the workspace alike.
     *
     * @param  array{provider: string|null, model: string|null}  $selection
     */
    private function localModelLacks(array $selection, string $capability): bool
    {
        if ($selection['provider'] !== ModelCatalog::LOCAL_PROVIDER || blank($selection['model'])) {
            return false;
        }

        $model = $this->registry->find($selection['model']);

        return $model !== null && ! in_array($capability, $model->capabilities(), true);
    }

    /**
     * A configured provider that can serve a capability.
     *
     * @return string|null The provider name, or null when none is configured.
     */
    private function capableProvider(string $capability): ?string
    {
        $default = config('ai.default');
        $candidates = $this->capableProviders($capability);

        // The configured default wins when it qualifies, so a deliberate choice
        // is never silently overridden.
        $preferred = collect($candidates)
            ->first(fn (array $candidate): bool => $candidate['provider'] === $default)
            ?? collect($candidates)->first();

        return $preferred['provider'] ?? null;
    }

    /**
     * A self-contained HTML document that plays the generated frames in order.
     *
     * @param  list<string>  $frames
     */
    private function storyboardDocument(string $prompt, array $frames, float $seconds): string
    {
        $safePrompt = e($prompt);
        $duration = max(0.5, $seconds);
        $total = $duration * count($frames);

        $images = collect($frames)
            ->map(fn (string $url, int $index): string => sprintf(
                '<img src="%s" alt="Frame %d" class="frame%s" style="animation-delay:%.2fs">',
                e($url),
                $index + 1,
                $index === 0 ? ' first' : '',
                $index * $duration,
            ))
            ->implode("\n      ");

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Storyboard — {$safePrompt}</title>
            <style>
                :root { color-scheme: dark; }
                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    min-height: 100vh;
                    display: grid;
                    place-items: center;
                    background: #0b0b0c;
                    font: 14px/1.5 system-ui, sans-serif;
                    color: #ececec;
                }
                .stage { width: min(960px, 92vw); }
                .reel {
                    position: relative;
                    aspect-ratio: 16 / 9;
                    border-radius: 16px;
                    overflow: hidden;
                    background: #141416;
                    box-shadow: 0 24px 60px rgba(0, 0, 0, .5);
                }
                .frame {
                    position: absolute;
                    inset: 0;
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    opacity: 0;
                    animation: play {$total}s steps(1, end) infinite;
                }
                @keyframes play {
                    0%, 100% { opacity: 0; }
                    0.01% { opacity: 1; }
                }
                .frame.first { opacity: 1; animation: none; }
                .caption { margin-top: 14px; opacity: .7; }
                .caption strong { color: #fff; }
            </style>
        </head>
        <body>
            <div class="stage">
                <div class="reel">
                    {$images}
                </div>
                <p class="caption"><strong>Storyboard:</strong> {$safePrompt}</p>
            </div>
        </body>
        </html>
        HTML;
    }
}
