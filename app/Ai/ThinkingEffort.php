<?php

namespace App\Ai;

/**
 * How much reasoning effort the provider should spend on a reply.
 *
 * This is deliberately separate from ResponseDepth: depth decides how thorough
 * the answer is and how many tool steps it may take, while effort decides how
 * much raw thinking the model does before it starts writing. A "fast" depth
 * with "extra high" effort gives you a terse but careful answer.
 *
 * The effort is only translated into request options for a provider and model
 * that are known to understand it. Guessing wrong turns a working chat into a
 * 400, so an unrecognised pair simply gets no reasoning parameter and the depth
 * still shapes the behaviour through the system prompt and step budget.
 */
enum ThinkingEffort: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case ExtraHigh = 'xhigh';

    /**
     * The effort used when the browser sends nothing.
     */
    public static function default(): self
    {
        return self::Medium;
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
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::ExtraHigh => 'Extra high',
        };
    }

    /**
     * A one-line description for the picker.
     */
    public function description(): string
    {
        return match ($this) {
            self::Low => 'Minimal deliberation. Quickest first token.',
            self::Medium => 'Balanced reasoning. The default.',
            self::High => 'Work through hard problems properly.',
            self::ExtraHigh => 'Maximum deliberation for the hardest questions.',
        };
    }

    /**
     * The instruction appended to the system prompt for this effort.
     *
     * When the provider cannot be told directly, this is what carries the
     * setting, so the behaviour still changes on every model.
     */
    public function instruction(): string
    {
        return match ($this) {
            self::Low => 'Thinking effort: low. Do not deliberate. Answer from what you already know.',
            self::Medium => 'Thinking effort: medium. Brief reasoning where it genuinely helps.',
            self::High => 'Thinking effort: high. Reason through the problem step by step before answering.',
            self::ExtraHigh => 'Thinking effort: extra high. Think as hard as you can. Consider several approaches, check your reasoning for mistakes, then answer.',
        };
    }

    /**
     * The provider's own reasoning-effort value for this level.
     */
    public function effort(): string
    {
        return match ($this) {
            self::Low => 'low',
            self::Medium => 'medium',
            self::High => 'high',
            self::ExtraHigh => 'high',
        };
    }

    /**
     * Anthropic's extended-thinking token budget for this level.
     *
     * The SDK's default cap is 64,000 output tokens, so no budget exceeds it.
     */
    public function anthropicBudget(): int
    {
        return match ($this) {
            self::Low => 2_000,
            self::Medium => 8_000,
            self::High => 24_000,
            self::ExtraHigh => 48_000,
        };
    }

    /**
     * Gemini's thinking budget in tokens, or 0 to disable thinking.
     */
    public function geminiBudget(): int
    {
        return match ($this) {
            self::Low => 1_024,
            self::Medium => 8_192,
            self::High => 24_576,
            self::ExtraHigh => 32_768,
        };
    }

    /**
     * Request options for a provider and model, or [] when unsupported.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(?string $driver, ?string $model): array
    {
        $model = strtolower($model ?? '');

        if ($model === '') {
            return [];
        }

        return match ($driver) {
            'anthropic' => $this->supportsClaudeThinking($model)
                ? ['thinking' => ['type' => 'enabled', 'budget_tokens' => $this->anthropicBudget()]]
                : [],
            'gemini' => $this->supportsGeminiThinking($model)
                ? ['thinkingConfig' => ['thinkingBudget' => $this->geminiBudget()]]
                : [],
            'openai' => $this->supportsOpenAiReasoning($model) ? ['reasoning_effort' => $this->effort()] : [],
            'deepseek' => $this->supportsDeepSeekReasoning($model) ? ['reasoning_effort' => $this->effort()] : [],
            'xai' => str_contains($model, 'grok-4') || str_contains($model, 'grok-3') ? ['reasoning_effort' => $this->effort()] : [],
            'groq' => $this->supportsGroqReasoning($model) ? ['reasoning_effort' => $this->effort()] : [],
            'openrouter' => ['reasoning' => ['effort' => $this->effort()]],
            default => [],
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
            fn (self $effort): array => [
                'id' => $effort->value,
                'label' => $effort->label(),
                'description' => $effort->description(),
            ],
            self::cases(),
        );
    }

    /**
     * Claude models that accept an extended-thinking budget.
     */
    private function supportsClaudeThinking(string $model): bool
    {
        return str_contains($model, 'claude-4')
            || str_contains($model, 'claude-sonnet-4')
            || str_contains($model, 'claude-opus-4')
            || str_contains($model, 'claude-3-7')
            || str_contains($model, 'claude-3-8');
    }

    /**
     * Gemini models that accept a thinking budget.
     */
    private function supportsGeminiThinking(string $model): bool
    {
        return str_contains($model, 'gemini-2.5') || str_contains($model, 'gemini-3');
    }

    /**
     * OpenAI models that accept a reasoning effort.
     */
    private function supportsOpenAiReasoning(string $model): bool
    {
        return str_starts_with($model, 'gpt-5')
            || str_starts_with($model, 'o1')
            || str_starts_with($model, 'o3')
            || str_starts_with($model, 'o4');
    }

    /**
     * DeepSeek's reasoner model accepts an effort; the chat model does not.
     */
    private function supportsDeepSeekReasoning(string $model): bool
    {
        return str_contains($model, 'reasoner') || str_contains($model, 'r1');
    }

    /**
     * Groq hosts a handful of reasoning models, not the whole catalogue.
     */
    private function supportsGroqReasoning(string $model): bool
    {
        return str_contains($model, 'r1') || str_contains($model, 'qwq') || str_contains($model, 'gpt-oss');
    }
}
