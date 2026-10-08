<?php

namespace App\Ai\Agents;

use App\Ai\ResponseDepth;
use App\Ai\ResponseLength;
use App\Ai\ThinkingEffort;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * The agent behind a single chat turn.
 *
 * It exists because the package's ad-hoc agent cannot carry request options.
 * Reaching for reasoning effort, or a step budget, means the agent itself has
 * to expose them, so this class takes the turn's settings and answers to the
 * six questions TextGenerationOptions asks: instructions, messages, tools,
 * maxSteps, maxTokens and providerOptions.
 */
class ChatAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    /**
     * @param  string  $instructions  The composed system prompt for this turn.
     * @param  iterable<int, Message>  $messages  The conversation so far.
     * @param  iterable<object>  $tools  The tools this turn may use.
     */
    public function __construct(
        private readonly string $instructions,
        private readonly iterable $messages,
        private readonly iterable $tools,
        private readonly ResponseDepth $depth,
        private readonly ThinkingEffort $effort,
        private readonly ResponseLength $length = ResponseLength::Auto,
        private readonly ?string $model = null,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * The conversation so far, replayed to the provider on every turn.
     *
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * The step budget for this turn, read by TextGenerationOptions via
     * method_exists() before it ever looks at the class attributes.
     */
    public function maxSteps(): int
    {
        return $this->depth->maxSteps();
    }

    /**
     * The output-token ceiling for this turn, or null to leave the provider's
     * own default in place.
     *
     * The thinking budget is folded in here rather than at the call site,
     * because it is only knowable once both settings are: Anthropic rejects a
     * `max_tokens` at or below its extended-thinking budget, and an OpenAI
     * reasoning model draws its internal tokens from the same allowance.
     */
    public function maxTokens(): ?int
    {
        return $this->length->cap(max($this->effort->anthropicBudget(), $this->effort->geminiBudget()));
    }

    /**
     * Request options for the driver serving this turn.
     *
     * The gateways pass the driver, and an unrecognized driver or model gets
     * no reasoning parameter at all rather than one it would reject.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider->value : $provider;

        return $this->effort->providerOptions($driver, $this->model);
    }
}
