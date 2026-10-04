<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Removes a workspace file.
 */
class DeleteFileTool extends WorkspaceTool
{
    public function description(): Stringable|string
    {
        return 'Delete a file from the workspace. Only use this when the user asks for a file to be removed.';
    }

    protected function execute(Request $request): string
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $this->workspace->delete($validated['path']);

        return "Deleted [{$validated['path']}].";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('The workspace-relative path of the file to delete.'),
        ];
    }
}
