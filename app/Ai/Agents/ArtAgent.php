<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ListFilesTool;
use App\Ai\Tools\WriteFileTool;
use App\Workspace\Workspace;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * An art sub-agent that produces vector artwork as SVG files in the workspace.
 *
 * Text-to-image models return a raster image, which cannot be edited as
 * vectors, so this agent writes SVG markup directly instead. That makes the
 * result openable in the editor and scalable without loss.
 */
class ArtAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function __construct(protected readonly Workspace $workspace) {}

    public function name(): string
    {
        return 'art_agent';
    }

    public function description(): Stringable|string
    {
        return 'Delegate a vector-art task to create or edit an SVG file in the workspace — icons, logos, illustrations, diagrams. Give it a self-contained brief; it cannot see the conversation.';
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Whale's vector-art sub-agent. You create and edit SVG artwork.

        Rules:
        - Always write valid, standalone SVG markup in a `.svg` file in the
          workspace. Include the `xmlns` attribute and a `viewBox`.
        - Use semantic shapes (path, circle, rect, polygon) and clean geometry.
        - Prefer a small, deliberate palette over many colours.
        - Keep the markup readable: group related elements and add a `<title>`.
        - Never embed raster images or external fonts.
        - When editing existing art, read the file first and preserve its
          overall composition unless asked to change it.
        - Reply with a one-line summary of the file you wrote and its dimensions.
        PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new ListFilesTool($this->workspace),
            new WriteFileTool($this->workspace),
        ];
    }
}
