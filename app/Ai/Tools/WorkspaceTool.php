<?php

namespace App\Ai\Tools;

use App\Workspace\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Shared plumbing for every workspace tool.
 *
 * Tools are constructed with the workspace they operate on, so an agent can
 * never reach a session it was not handed. Errors are returned as readable
 * text rather than thrown: a thrown tool exception aborts the whole agent
 * step, whereas a text result lets the model recover and try again.
 */
abstract class WorkspaceTool implements Tool
{
    public function __construct(protected readonly Workspace $workspace) {}

    /**
     * Get the description of the tool's purpose.
     */
    abstract public function description(): Stringable|string;

    /**
     * Execute the tool against the workspace.
     */
    abstract protected function execute(Request $request): string;

    /**
     * Execute the tool, converting failures into readable output.
     */
    public function handle(Request $request): Stringable|string
    {
        try {
            return $this->execute($request);
        } catch (Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
