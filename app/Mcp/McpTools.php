<?php

namespace App\Mcp;

use Laravel\Ai\Contracts\Tool;
use Laravel\Mcp\Facades\Mcp;
use Throwable;

/**
 * Collects the tools of the external MCP servers listed in config/mcp.php.
 *
 * A server with no url (web) or command (local) was never registered, so it
 * is skipped before any connection is attempted: an unconfigured install
 * contributes nothing and behaves exactly as if the feature did not exist.
 * A server that is configured but unreachable is logged and skipped — a dead
 * external service must degrade a turn, not fail it.
 */
class McpTools
{
    /**
     * Adapter tools for every configured server.
     *
     * @return list<Tool>
     */
    public static function configured(): array
    {
        $tools = [];

        foreach (config('mcp.servers', []) as $name => $server) {
            if (! is_array($server) || ! is_string($name) || $name === '') {
                continue;
            }

            $transport = $server['transport'] ?? 'web';

            $isConfigured = $transport === 'local'
                ? ! empty($server['command'])
                : ! empty($server['url']);

            if (! $isConfigured) {
                continue;
            }

            try {
                foreach (Mcp::client($name)->tools() as $tool) {
                    $tools[] = new McpTool($name, $tool);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $tools;
    }
}
