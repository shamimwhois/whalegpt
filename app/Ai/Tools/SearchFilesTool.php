<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Case-insensitive full-text search across the workspace, returning file,
 * line number and an excerpt for each hit.
 */
class SearchFilesTool extends WorkspaceTool
{
    public function description(): Stringable|string
    {
        return 'Search every text file in the workspace for a case-insensitive substring. Returns matching paths, line numbers and excerpts.';
    }

    protected function execute(Request $request): string
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:200'],
        ]);

        $matches = $this->workspace->search($validated['query']);

        if ($matches === []) {
            return "No matches for [{$validated['query']}].";
        }

        return collect($matches)
            ->map(fn (array $match): string => sprintf('%s:%d: %s', $match['path'], $match['line'], $match['excerpt']))
            ->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('The text to search for.'),
        ];
    }
}
