<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * A study sub-agent that turns a topic or document into learning material.
 *
 * It works from the text it is given, so the parent should include any source
 * material in the task description.
 */
class StudyAgent implements Agent, CanActAsTool
{
    use Promptable;

    public function name(): string
    {
        return 'study_agent';
    }

    public function description(): Stringable|string
    {
        return 'Delegate a learning task — explain a topic, build a study plan, write flashcards or a quiz. Include any source material in the task, as it cannot see the conversation.';
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Whale's study sub-agent. You turn topics and source material into
        clear, structured learning aids.

        Rules:
        - Lead with a one-paragraph plain-language summary.
        - Break the topic into progressive sections, simplest first.
        - Use concrete examples and analogies over abstract definitions.
        - When asked for flashcards, output a markdown table with "Front" and
          "Back" columns.
        - When asked for a quiz, output numbered questions, then an "Answers"
          section with brief explanations.
        - Flag anything commonly misunderstood.
        PROMPT;
    }
}
