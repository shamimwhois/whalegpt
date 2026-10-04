<?php

namespace App\Mcp\Tools;

use App\Workspace\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * MCP tool: list every file in a Whale workspace.
 *
 * Exposing the workspace over MCP lets an external agent (an IDE, another
 * assistant, a CLI) inspect the same sandbox the chat UI uses, without any
 * direct access to the filesystem.
 */
#[Description('List every file in a Whale workspace, with size and whether it is binary.')]
class ListWorkspaceFilesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string', 'max:64'],
        ]);

        try {
            $workspace = Workspace::forSession($validated['workspace']);
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }

        $files = $workspace->files();

        if ($files === []) {
            return Response::text('The workspace is empty.');
        }

        return Response::json($files);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->required()->description('The workspace identifier (a session id).'),
        ];
    }
}
