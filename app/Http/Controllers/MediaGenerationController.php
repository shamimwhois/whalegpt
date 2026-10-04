<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ArtAgent;
use App\Ai\ImageStyle;
use App\Ai\ModelCatalog;
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
    public function __construct(private readonly ModelCatalog $catalog) {}

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
            $pending = Image::of($prompt);

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
                'message' => 'Image generation is unavailable. Configure a provider that supports images (OpenAI, Gemini, or xAI) with an API key in your .env file.',
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
    private function resolveFor(array $validated, string $capability): array
    {
        $selection = $this->catalog->resolve($validated['provider'] ?? null, $validated['model'] ?? null);

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
