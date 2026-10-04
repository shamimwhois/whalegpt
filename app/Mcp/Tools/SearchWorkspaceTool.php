<?php

namespace App\Mcp\Tools;

use App\Workspace\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Search every text file in a Whale workspace for a case-insensitive substring.')]
class SearchWorkspaceTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string', 'max:64'],
            'query' => ['required', 'string', 'max:200'],
        ]);

        try {
            $workspace = Workspace::forSession($validated['workspace']);
            $matches = $workspace->search($validated['query']);
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }

        return Response::json($matches);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->required()->description('The workspace identifier.'),
            'query' => $schema->string()->required()->description('The text to search for.'),
        ];
    }
}
