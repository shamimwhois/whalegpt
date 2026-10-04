<?php

namespace App\Http\Controllers;

use App\Ai\ModelCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Ai\Messages\Message;
use Throwable;

use function Laravel\Ai\agent;

/**
 * Rewrites a draft prompt into a clearer, more specific one.
 *
 * The original text is returned alongside the rewrite so the client can offer
 * an undo, and the result is always a suggestion the user can accept or edit —
 * never something sent on their behalf.
 */
class PromptEnhanceController extends Controller
{
    public function __construct(private readonly ModelCatalog $catalog) {}

    /**
     * The shaping goals a rewrite can target.
     *
     * @var list<string>
     */
    private const STYLES = ['clearer', 'shorter', 'detailed', 'technical', 'creative'];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'style' => ['sometimes', 'nullable', Rule::in(self::STYLES)],
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:4000'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $selection = $this->catalog->resolve(
            $validated['provider'] ?? null,
            $validated['model'] ?? null,
        );

        $history = collect($validated['history'] ?? [])
            ->map(fn (array $message): Message => new Message($message['role'], $message['content']))
            ->all();

        try {
            $response = agent(
                instructions: $this->instructions($validated['style'] ?? 'clearer'),
                messages: $history,
            )->prompt(
                $validated['prompt'],
                provider: $selection['provider'],
                model: $selection['model'],
                timeout: 60,
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Prompt enhancement is unavailable. Configure a text provider with an API key in your .env file.',
            ], 503);
        }

        $enhanced = trim((string) $response->text);

        // A model that returns nothing useful should not blank the composer.
        if ($enhanced === '') {
            return response()->json([
                'original' => $validated['prompt'],
                'enhanced' => $validated['prompt'],
            ]);
        }

        return response()->json([
            'original' => $validated['prompt'],
            'enhanced' => $enhanced,
        ]);
    }

    /**
     * The rewrite instruction for a style.
     */
    private function instructions(string $style): string
    {
        $shaping = match ($style) {
            'shorter' => 'Make it more concise without losing meaning. Cut filler and redundancy.',
            'detailed' => 'Add the specifics that are missing: context, constraints, desired output and format.',
            'technical' => 'Make it precise and technical. Use correct terminology and state exact requirements.',
            'creative' => 'Make it more vivid and specific, with concrete imagery and detail.',
            default => 'Make it clearer and more specific, removing ambiguity.',
        };

        return <<<PROMPT
        You rewrite a user's draft prompt so an AI assistant can act on it better.

        Rules:
        - {$shaping}
        - Preserve the user's intent and every constraint they stated. Never add
          requirements they did not ask for.
        - Keep the user's own voice and language.
        - Reply with the rewritten prompt only. No preamble, no quotes, no
          explanation, no markdown fences.
        PROMPT;
    }
}
