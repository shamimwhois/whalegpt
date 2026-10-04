<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lists every file in the workspace so an agent can orient itself before
 * reading or editing anything.
 */
class ListFilesTool extends WorkspaceTool
{
    public function description(): Stringable|string
    {
        return 'List every file in the current workspace with its size. Call this first when you need to know what already exists.';
    }

    protected function execute(Request $request): string
    {
        $files = $this->workspace->files();

        if ($files === []) {
            return 'The workspace is empty.';
        }

        return collect($files)
            ->map(fn (array $file): string => sprintf(
                '%s (%s)%s',
                $file['path'],
                number_format($file['size']).' bytes',
                $file['binary'] ? ' [binary]' : '',
            ))
            ->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
