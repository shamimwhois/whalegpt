<?php

namespace App\Mcp\Tools;

use App\Workspace\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Create or replace a file in a Whale workspace.')]
class WriteWorkspaceFileTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string', 'max:64'],
            'path' => ['required', 'string', 'max:255'],
            'contents' => ['required', 'string'],
        ]);

        try {
            $workspace = Workspace::forSession($validated['workspace']);
            $workspace->write($validated['path'], $validated['contents']);
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }

        return Response::text(sprintf('Wrote [%s].', $validated['path']));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->required()->description('The workspace identifier.'),
            'path' => $schema->string()->required()->description('The workspace-relative file path.'),
            'contents' => $schema->string()->required()->description('The complete file contents.'),
        ];
    }
}
