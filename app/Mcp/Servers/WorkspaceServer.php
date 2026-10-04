<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListWorkspaceFilesTool;
use App\Mcp\Tools\ReadWorkspaceFileTool;
use App\Mcp\Tools\SearchWorkspaceTool;
use App\Mcp\Tools\WriteWorkspaceFileTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Exposes Whale's sandboxed workspaces over MCP.
 *
 * An external agent can list, read, search and write files in a named
 * workspace. Every path still goes through App\Workspace, so the same
 * traversal and size limits apply as in the UI.
 */
#[Name('Whale Workspace')]
#[Version('1.0.0')]
#[Instructions('Read and write files inside a Whale workspace. Every tool takes a "workspace" identifier. Paths are confined to that workspace.')]
class WorkspaceServer extends Server
{
    protected array $tools = [
        ListWorkspaceFilesTool::class,
        ReadWorkspaceFileTool::class,
        WriteWorkspaceFileTool::class,
        SearchWorkspaceTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
