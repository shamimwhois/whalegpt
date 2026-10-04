<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Creates or replaces a workspace file. The primary way an agent produces
 * code for the editor and the live preview.
 */
class WriteFileTool extends WorkspaceTool
{
    public function description(): Stringable|string
    {
        return 'Create a new file or replace the entire contents of an existing file in the workspace. Provide the complete file contents, not a diff.';
    }

    protected function execute(Request $request): string
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'contents' => ['required', 'string'],
        ]);

        $this->workspace->write($validated['path'], $validated['contents']);

        return sprintf('Wrote %s bytes to [%s].', number_format(strlen($validated['contents'])), $validated['path']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('The workspace-relative path to write, e.g. "index.html" or "src/app.js".'),
            'contents' => $schema->string()->required()->description('The complete contents of the file.'),
        ];
    }
}
