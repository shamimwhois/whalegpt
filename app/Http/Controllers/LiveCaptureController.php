<?php

namespace App\Http\Controllers;

use App\Ai\ModelCatalog;
use App\Ai\Speech\Transcriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

use function Laravel\Ai\agent;

/**
 * Live speech and camera input for the composer.
 *
 * Two input modes share one transcript pipeline:
 *
 *   - recorded speech, sent as a single clip after the user stops talking;
 *   - live speech, sent in short chunks while they talk so words appear as they
 *     are said rather than at the end.
 *
 * Both are bounded. A clip longer than the engine's window is refused with a
 * clear message rather than silently truncated, and the chunk size is capped so a
 * long recording cannot be replayed through the engine in one request.
 */
class LiveCaptureController extends Controller
{
    /**
     * The longest clip a single request may carry.
     *
     * Whistle transcribes up to 30 seconds in one pass, so anything longer has to
     * be split by the caller.
     */
    private const MAX_SECONDS = 30;

    /**
     * The largest upload accepted, in kilobytes.
     */
    private const MAX_KILOBYTES = 10240;

    public function __construct(
        private readonly Transcriber $transcriber,
        private readonly ModelCatalog $catalog,
    ) {}

    /**
     * What the browser needs to know before it starts recording.
     *
     * The browser asks this once so a dead microphone or a missing engine is
     * reported in the composer rather than after a user has spoken for ten
     * seconds.
     */
    public function capabilities(): JsonResponse
    {
        return response()->json([
            'engines' => $this->transcriber->availableEngines(),
            'engine' => $this->transcriber->engine(),
            'max_seconds' => self::MAX_SECONDS,
            'max_kilobytes' => self::MAX_KILOBYTES,
            'languages' => ['en', 'de', 'fr', 'es', 'it', 'nl', 'pl'],
            'provider' => $this->catalog->defaultSelection()['provider'],
        ]);
    }

    /**
     * Transcribe one recorded clip.
     */
    public function transcribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'mimes:wav,webm,ogg,mp3,m4a,aac,flac', 'max:'.self::MAX_KILOBYTES],
            'language' => ['sometimes', 'nullable', 'string', 'size:2'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
            'engine' => ['sometimes', 'nullable', Rule::in(['auto', 'whistle', 'sdk'])],
        ]);

        return $this->respond($validated);
    }

    /**
     * Run the transcription and shape the response, including the honest failure.
     *
     * @param  array<string, mixed>  $validated
     */
    private function respond(array $validated): JsonResponse
    {
        try {
            $transcript = $this->transcriber->transcribe(
                $validated['audio'],
                [
                    'engine' => $validated['engine'] ?? null,
                    'language' => $validated['language'] ?? null,
                    'provider' => $validated['provider'] ?? null,
                    'model' => $validated['model'] ?? null,
                ],
            );
        } catch (Throwable $e) {
            // A failed chunk must not kill a live session, so the reason is
            // returned rather than thrown and the browser can retry or stop.
            Log::warning('Speech transcription failed: '.$e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
                'text' => '',
                'engine' => $validated['engine'] ?? $this->transcriber->engine(),
            ], 503);
        }

        return response()->json($transcript->toArray());
    }

    /**
     * Describe what the live camera sees.
     *
     * The browser sends a frame grabbed from the webcam and, if it wants, a
     * question about it. Without a question this is a plain description, which is
     * what the "look at this" gesture means.
     */
    public function describeScene(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'frame' => ['required', 'image', 'max:8192'],
            'question' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->catalog->resolve(
            $validated['provider'] ?? null,
            $validated['model'] ?? null,
        );

        $question = trim((string) ($validated['question'] ?? ''));

        $instructions = $question === ''
            ? 'Describe what the camera is showing in two sentences. Be concrete about the subject, the setting and any text that is legible.'
            : 'Answer the question about what the camera is showing. If the answer is not visible in the frame, say so plainly.';

        try {
            $response = agent(instructions: $instructions)
                ->prompt(
                    $question === '' ? 'What do you see?' : $question,
                    attachments: [$request->file('frame')],
                );
        } catch (Throwable $e) {
            Log::warning('Live camera description failed: '.$e->getMessage());

            return response()->json([
                'message' => 'The selected provider cannot look at images. Configure a vision-capable provider such as Gemini, OpenAI or a local Qwen2-VL model.',
            ], 503);
        }

        return response()->json([
            'text' => trim((string) $response->text),
            'provider' => $selection['provider'],
            'model' => $selection['model'],
        ]);
    }
}
