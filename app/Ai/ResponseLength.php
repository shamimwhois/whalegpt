<?php

namespace App\Ai;

/**
 * How long a reply is allowed to be.
 *
 * Length is a request-level setting rather than a per-agent attribute, so the
 * browser can switch it mid-conversation the same way depth and effort can.
 *
 * Like depth, it never touches sampling: temperature stays with the provider,
 * because reasoning models reject any value other than their own default. What
 * length controls is the output-token ceiling, which is a real cost and latency
 * lever on its own — a short answer stops generating rather than being asked to
 * be brief.
 *
 * The ceiling cannot simply be the chosen number. On Anthropic, `max_tokens`
 * must exceed the extended-thinking budget that ThinkingEffort requests, and on
 * OpenAI a reasoning model's internal tokens are drawn from the same allowance.
 * A cap below the thinking budget is therefore a 400, not a short answer, so
 * cap() lifts the request to clear the budget.
 */
enum ResponseLength: string
{
    case Auto = 'auto';
    case Short = 'short';
    case Medium = 'medium';
    case Long = 'long';

    /**
     * Tokens left for the answer once thinking has been paid for.
     *
     * Deliberately small: it only has to be positive for the request to be
     * valid, and length is a ceiling rather than a target.
     */
    private const HEADROOM = 1_024;

    /**
     * The length used when the browser sends nothing.
     */
    public static function default(): self
    {
        return self::Auto;
    }

    /**
     * Resolve a request value, falling back to the default for anything unknown.
     */
    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /**
     * The label shown on the slider's readout.
     */
    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto',
            self::Short => 'Short',
            self::Medium => 'Medium',
            self::Long => 'Long',
        };
    }

    /**
     * A one-line description for the slider's tooltip.
     */
    public function description(): string
    {
        return match ($this) {
            self::Auto => 'Let the provider decide. No output limit is sent.',
            self::Short => 'A few paragraphs. Stops early and costs the least.',
            self::Medium => 'Room for a full explanation.',
            self::Long => 'Essays, code and long documents.',
        };
    }

    /**
     * The output-token ceiling this length asks for, or null to keep the
     * provider's own default.
     *
     * @param  int  $thinkingBudget  The largest thinking budget any supported
     *                               provider may consume for this turn. The cap
     *                               is lifted to clear it, because reasoning
     *                               tokens are drawn from the same allowance and
     *                               a cap below the budget is rejected outright.
     */
    public function cap(int $thinkingBudget = 0): ?int
    {
        if ($this === self::Auto) {
            return null;
        }

        return max($this->tokens(), $thinkingBudget + self::HEADROOM);
    }

    /**
     * The ceiling before any thinking budget is taken into account.
     */
    private function tokens(): int
    {
        return match ($this) {
            self::Auto, self::Long => 64_000,
            self::Medium => 16_000,
            self::Short => 4_000,
        };
    }

    /**
     * The catalog sent to the browser so the slider and the server agree.
     *
     * @return list<array{id: string, label: string, description: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $length): array => [
                'id' => $length->value,
                'label' => $length->label(),
                'description' => $length->description(),
            ],
            self::cases(),
        );
    }
}
