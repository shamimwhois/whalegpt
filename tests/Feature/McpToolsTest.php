<?php

use App\Mcp\McpTool;
use App\Mcp\McpTools;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Client\Primitives\Tool as McpToolPrimitive;

test('an unconfigured install contributes no mcp tools', function () {
    config(['mcp.servers' => [
        'linear' => ['transport' => 'web', 'url' => null, 'token' => null],
        'filesystem' => ['transport' => 'local', 'command' => null, 'args' => []],
    ]]);

    expect(McpTools::configured())->toBe([]);
});

test('the adapter exposes a servers tool description to the agent', function () {
    $primitive = new McpToolPrimitive(
        client: null,
        name: 'list_things',
        title: null,
        description: 'Lists the things.',
        inputSchema: ['type' => 'object'],
        outputSchema: null,
        annotations: [],
        meta: null,
    );

    $tool = new McpTool('demo', $primitive);

    expect((string) $tool->description())->toBe('Lists the things.')
        ->and($tool->name())->toBe('list_things');
});

test('a server failure is returned as readable tool output, not thrown', function () {
    $primitive = new McpToolPrimitive(
        client: null,
        name: 'list_things',
        title: null,
        description: 'Lists the things.',
        inputSchema: ['type' => 'object'],
        outputSchema: null,
        annotations: [],
        meta: null,
    );

    // A tool with no bound client cannot reach a server; the adapter must
    // turn that into text the model can react to instead of aborting the turn.
    $result = (new McpTool('demo', $primitive))->handle(new Request([]));

    expect((string) $result)
        ->toContain('Error calling MCP tool [list_things] on server [demo]');
});
