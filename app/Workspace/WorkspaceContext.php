<?php

namespace App\Workspace;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves which workspace a request is acting on.
 *
 * The identifier is supplied by the client and kept in localStorage, so the
 * API stays stateless — no session table or cookie is required, and the same
 * id works for the chat UI, the IDE and the MCP tools. The value is only ever
 * used to name a directory, and App\Workspace sanitises it, so an arbitrary
 * string cannot escape the workspaces root.
 */
class WorkspaceContext
{
    /**
     * The request header a client may use to name its workspace.
     */
    public const HEADER = 'X-Whale-Workspace';

    /**
     * Resolve the workspace id for a request.
     *
     * Precedence: an explicit request input, then the header, then the
     * session (when one exists), then a fresh random id so an unconfigured
     * caller still gets an isolated, working workspace.
     */
    public static function idFrom(Request $request): string
    {
        $candidate = $request->input('workspace')
            ?? $request->header(self::HEADER);

        if (is_string($candidate) && self::isValid($candidate)) {
            return $candidate;
        }

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return 'ws-'.Str::random(32);
    }

    /**
     * Resolve the workspace for a request.
     */
    public static function for(Request $request): Workspace
    {
        return Workspace::forSession(self::idFrom($request));
    }

    /**
     * Whether a client-supplied id is safe to use as a directory name.
     */
    public static function isValid(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id) === 1;
    }
}
