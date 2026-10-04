<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads a single workspace file so an agent can inspect it before editing.
 */
class ReadFileTool extends WorkspaceTool
{
    public function description(): Stringable|string
    {
        return 'Read the full contents of a file in the workspace. Use the exact path returned by ListFilesTool.';
    }

    protected function execute(Request $request): string
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $path = $validated['path'];

        if (! $this->workspace->isText($path)) {
            return "The file [{$path}] is binary and cannot be read as text.";
        }

        return $this->workspace->read($path);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('The workspace-relative path of the file to read.'),
        ];
    }
}
