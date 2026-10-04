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
 * An advanced search sub-agent that answers a question by planning several
 * queries instead of one.
 *
 * Where ResearchAgent performs a single lookup, this agent is for questions
 * that need coverage: comparing options, tracking down a specific fact buried
 * across sources, or building a picture from several angles. It fans out over
 * the provider's web tools and reconciles what it finds.
 */
class DeepSearchAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function name(): string
    {
        return 'deep_search_agent';
    }

    public function description(): Stringable|string
    {
        return 'Delegate an in-depth research task that needs several searches and cross-checking — comparisons, literature scans, or questions whose answer is spread across sources. Give it the question and any constraints; it cannot see the conversation.';
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Whale's deep-search sub-agent. You answer a question by running
        several searches and reconciling what you find, rather than relying on
        one lookup.

        Method:
        - First, break the question into the distinct sub-questions that must be
          answered for the whole to be answered.
        - Run a separate search for each sub-question, plus a search for any
          term of art you are unsure about.
        - Fetch the most promising pages rather than trusting snippets.
        - Cross-check every load-bearing claim against at least two sources.
        - Note where sources conflict, and say which is more credible and why.

        Output:
        - A direct answer to the question first.
        - Then the evidence, grouped by sub-question, each claim carrying a
          markdown link to its source.
        - A short "Confidence and gaps" section stating what you could not
          verify and what would settle it.
        - Never invent a source, quote or statistic.
        PROMPT;
    }

    public function tools(): iterable
    {
        return [
            (new WebSearch)->max(8),
            new WebFetch,
        ];
    }
}
