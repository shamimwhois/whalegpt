<?php

namespace App\Ai\Agents;

use App\Ai\ResponseDepth;
use App\Ai\ResponseLength;
use App\Ai\ThinkingEffort;
use App\Workspace\Workspace;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Whale's primary assistant.
 *
 * It is the chat assistant: it holds the session's sandboxed workspace and
 * delegates to the specialist sub-agents (coding, research, deep-search,
 * study, security and vector-art) as soon as a task needs files, current
 * facts, study material or artwork. Because it extends {@see ChatAgent}, the
 * streaming path, response-length budgeting and provider-specific options are
 * inherited, and a workspace-owned reply is guaranteed rather than accidentally
 * supplied through the tool list.
 *
 * @extends ChatAgent<\DateTimeInterface>
 */
#[MaxSteps(12)]
class AssistantAgent extends ChatAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    /**
     * @param  iterable<int, Message>  $messages  The conversation so far.
     * @param  list<object>  $tools  The tools this turn may use.
     */
    public function __construct(
        public readonly Workspace $workspace,
        string $instructions,
        iterable $messages,
        iterable $tools,
        ResponseDepth $depth,
        ThinkingEffort $effort,
        ResponseLength $length = ResponseLength::Auto,
        ?string $model = null,
    ) {
        parent::__construct($instructions, $messages, $tools, $depth, $effort, $length, $model);
    }
}
