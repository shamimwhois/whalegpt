<?php

namespace App\Ai\Agents;

use App\Ai\Tools\DeleteFileTool;
use App\Ai\Tools\ListFilesTool;
use App\Ai\Tools\ReadFileTool;
use App\Ai\Tools\SearchFilesTool;
use App\Ai\Tools\WriteFileTool;
use App\Workspace\Workspace;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * A coding sub-agent that reads and writes files in a single workspace.
 *
 * It runs in isolation: it cannot see the parent conversation, so the parent
 * must hand it a self-contained task. Its whole purpose is to turn a request
 * into files the editor and live preview can immediately use.
 */
class CodingAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function __construct(protected readonly Workspace $workspace) {}

    public function name(): string
    {
        return 'coding_agent';
    }

    public function description(): Stringable|string
    {
        return 'Delegate a coding task to write, edit or delete files in the user\'s workspace. Give it a complete, self-contained brief — it cannot see the conversation.';
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Whale's coding sub-agent. You build working projects inside a
        sandboxed workspace by creating and editing files.

        Rules:
        - Start by listing the files that already exist so you do not clobber work.
        - Read a file before you change it.
        - Write complete file contents, never a diff or a placeholder comment.
        - For a web project, create a runnable `index.html` (with inline CSS/JS
          or linked files) so the live preview works immediately.
        - Prefer plain HTML/CSS/JS unless the task explicitly calls for a
          framework.
        - When you are done, reply with a short summary of the files you wrote
          and how to run or open them. Do not paste the file contents back.
        PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new ListFilesTool($this->workspace),
            new ReadFileTool($this->workspace),
            new WriteFileTool($this->workspace),
            new DeleteFileTool($this->workspace),
            new SearchFilesTool($this->workspace),
        ];
    }
}
