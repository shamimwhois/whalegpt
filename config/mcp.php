<?php

/*
|--------------------------------------------------------------------------
| MCP Client Servers
|--------------------------------------------------------------------------
|
| External MCP servers whose tools are offered to Whale's agents.
|
| Each entry is registered as a named client and its tools become available
| to any agent that calls `Mcp::client('<name>')->tools()`. Two transports
| are supported:
|
|   'web'   => a remote server reached over HTTP (optionally with a token)
|   'local' => a server run as a subprocess, e.g. npx
|
| An entry with an empty 'url' (web) or 'command' (local) is skipped, so the
| defaults below are inert until you configure them.
|
*/

return [

    'servers' => [

        'linear' => [
            'transport' => 'web',
            'url' => env('MCP_LINEAR_URL'),
            'token' => env('MCP_LINEAR_TOKEN'),
        ],

        'filesystem' => [
            'transport' => 'local',
            'command' => env('MCP_FILESYSTEM_COMMAND'),
            'args' => [],
        ],

    ],

];
