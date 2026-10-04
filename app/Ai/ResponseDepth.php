<?php

namespace App\Ai;

/**
 * How thoroughly the assistant should answer a request.
 *
 * The depth is a request-level setting rather than a per-agent attribute, so
 * the browser can switch it mid-conversation. Each case knows the instruction
 * it appends to the system prompt and how many tool steps it permits.
 *
 * Depth is distinct from ThinkingEffort: depth governs how much work the
 * assistant does (tool calls, structure, coverage), effort governs how much it
 * deliberates before writing a word. Both are sent on every request.
 *
 * Note that no temperature is ever sent. Reasoning models reject any value
 * other than their own default, so sampling is left to the provider and the
 * response is shaped through the prompt and step budget instead.
 */
enum ResponseDepth: string
{
    case DeepThinking = 'deep';
    case Fast = 'fast';
    case SuperFast = 'super';

    /**
     * The depth used when the browser sends nothing.
     */
    public static function default(): self
    {
        return self::Fast;
    }

    /**
     * Resolve a request value, falling back to the default for anything unknown.
     */
    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /**
     * The label shown in the composer.
     */
    public function label(): string
    {
        return match ($this) {
            self::DeepThinking => 'Deep thinking',
            self::Fast => 'Fast',
            self::SuperFast => 'Super fast',
        };
    }

    /**
     * A one-line description for the picker.
     */
    public function description(): string
    {
        return match ($this) {
            self::DeepThinking => 'Reasoning models, more tool steps, thorough answers.',
            self::Fast => 'Balanced speed and quality. The default.',
            self::SuperFast => 'Fewest steps, lowest latency, terse answers.',
        };
    }

    /**
     * The instruction appended to the system prompt for this depth.
     */
    public function instruction(): string
    {
        return match ($this) {
            self::DeepThinking => 'Response depth: deep thinking. Reason carefully before answering, weigh alternatives, and verify claims. Prefer thorough, well-structured answers over brevity. Use as many tool steps as the task genuinely needs.',
            self::Fast => 'Response depth: fast. Answer directly and efficiently. Keep prose tight and avoid unnecessary tool calls.',
            self::SuperFast => 'Response depth: super fast. Reply in the fewest words that fully answer the question. No preamble, no restating the question, no filler. Avoid tool calls unless the task is impossible without one.',
        };
    }

    /**
     * How many tool-calling steps the assistant may take.
     */
    public function maxSteps(): int
    {
        return match ($this) {
            self::DeepThinking => 24,
            self::Fast => 12,
            self::SuperFast => 4,
        };
    }

    /**
     * The catalog sent to the browser so the picker and the server agree.
     *
     * @return list<array{id: string, label: string, description: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $depth): array => [
                'id' => $depth->value,
                'label' => $depth->label(),
                'description' => $depth->description(),
            ],
            self::cases(),
        );
    }
}
