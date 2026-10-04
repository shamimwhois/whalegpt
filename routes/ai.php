<?php

use App\Mcp\Servers\WorkspaceServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| This file is loaded automatically by the MCP package (unprefixed), so it
| only registers MCP servers. The application's own AI routes live in
| routes/chat.php and are mounted under the "ai" prefix.
|
*/

Mcp::web('/mcp/workspace', WorkspaceServer::class);
