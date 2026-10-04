<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Client;
use Laravel\Mcp\Facades\Mcp;

/**
 * Registers the external MCP servers listed in config/mcp.php.
 *
 * A server with no URL (web) or command (local) is skipped, so an
 * unconfigured install carries no dead connections. Once registered, a server's
 * tools can be offered to an agent with `Mcp::client('<name>')->tools()`.
 */
class McpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Register the configured MCP clients.
     */
    public function boot(): void
    {
        foreach (config('mcp.servers', []) as $name => $server) {
            if (! is_array($server)) {
                continue;
            }

            $factory = $this->factoryFor($server);

            if ($factory === null) {
                continue;
            }

            Mcp::registerClient($name, $factory);
        }
    }

    /**
     * Build the client factory for a server definition, or null when the
     * definition is incomplete.
     *
     * @param  array<string, mixed>  $server
     */
    private function factoryFor(array $server): ?\Closure
    {
        $transport = $server['transport'] ?? 'web';

        if ($transport === 'local') {
            $command = $server['command'] ?? null;

            if (! is_string($command) || $command === '') {
                return null;
            }

            /** @var list<string> $args */
            $args = is_array($server['args'] ?? null) ? array_values($server['args']) : [];

            return fn (): Client => Client::local($command, $args);
        }

        $url = $server['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        $token = $server['token'] ?? null;

        return function () use ($url, $token): Client {
            $client = Client::web($url);

            return is_string($token) && $token !== ''
                ? $client->withToken($token)
                : $client;
        };
    }
}
