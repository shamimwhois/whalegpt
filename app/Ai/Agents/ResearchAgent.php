<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

/**
 * A research sub-agent that gathers current information from the web.
 *
 * It relies on the provider's own web-search and fetch tools, so it needs a
 * provider that supports them (OpenAI, Anthropic or Gemini).
 */
class ResearchAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function name(): string
    {
        return 'research_agent';
    }

    public function description(): Stringable|string
    {
        return 'Delegate a research question to look up current, source-backed information on the web. Give it a specific question; it cannot see the conversation.';
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Whale's research sub-agent. You answer questions with current,
        verifiable information from the web.

        Rules:
        - Search before you answer. Never rely on memory for facts that change.
        - Prefer primary and authoritative sources.
        - Cite each claim with a markdown link to the page you found it on.
        - If sources disagree, say so and explain the difference.
        - Be concise: a short synthesis followed by a "Sources" list.
        - If you cannot verify something, say that plainly instead of guessing.
        PROMPT;
    }

    public function tools(): iterable
    {
        return [
            (new WebSearch)->max(5),
            new WebFetch,
        ];
    }
}
