<?php

namespace App\Ai\Agents;

use App\Ai\Tools\DeleteFileTool;
use App\Ai\Tools\ListFilesTool;
use App\Ai\Tools\ReadFileTool;
use App\Ai\Tools\SearchFilesTool;
use App\Ai\Tools\WriteFileTool;
use App\Workspace\Workspace;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Whale's primary assistant.
 *
 * It answers directly for ordinary conversation and delegates to a specialist
 * sub-agent when a task needs files, research, study material or vector art.
 * The sub-agents run in isolation, so the coordinator is responsible for
 * writing a self-contained brief for each delegation.
 */
#[MaxSteps(12)]
class AssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  iterable<int, Message>  $messages  The conversation so far.
     */
    public function __construct(
        protected readonly Workspace $workspace,
        protected iterable $messages = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Whale, a capable and concise AI assistant. You help with coding,
        research, studying and art, and you have a sandboxed workspace where you
        can create real files the user can edit and preview.

        Working style:
        - Answer straightforward questions directly and briefly.
        - When a task needs files created or changed, delegate to `coding_agent`
          with a complete brief. When it needs current facts, delegate to
          `research_agent`. For learning material, use `study_agent`. For SVG
          artwork, use `art_agent`.
        - Sub-agents cannot see this conversation, so every brief must stand on
          its own: include the relevant context, constraints and expected output.
        - You may use the file tools yourself for quick reads or one-line tweaks.
        - Prefer doing the work over describing how it could be done.
        - Use markdown. Keep prose tight; let code and structure carry the detail.
        - Never invent file contents or results you did not actually produce.
        PROMPT;
    }

    /**
     * The conversation so far, replayed to the provider on every turn.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    public function tools(): iterable
    {
        return [
            new ListFilesTool($this->workspace),
            new ReadFileTool($this->workspace),
            new WriteFileTool($this->workspace),
            new DeleteFileTool($this->workspace),
            new SearchFilesTool($this->workspace),
            new CodingAgent($this->workspace),
            new ResearchAgent,
            new StudyAgent,
            new ArtAgent($this->workspace),
        ];
    }
}
