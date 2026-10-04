<?php

namespace App\Mcp\Tools;

use App\Workspace\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Read the contents of a text file in a Whale workspace.')]
class ReadWorkspaceFileTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string', 'max:64'],
            'path' => ['required', 'string', 'max:255'],
        ]);

        try {
            $workspace = Workspace::forSession($validated['workspace']);
            $contents = $workspace->read($validated['path']);
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }

        return Response::text($contents);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->required()->description('The workspace identifier.'),
            'path' => $schema->string()->required()->description('The workspace-relative file path.'),
        ];
    }
}
