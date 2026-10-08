<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse a request that arrives without an authenticated user.
 *
 * Laravel's own `auth` middleware sends a browser to the sign-in route, but this
 * application does not have one yet, so there is nowhere to send a guest. Rather
 * than redirect to a route that does not exist, or fall back on the framework's
 * JSON 401, an unauthenticated request is refused with the same 403 the rest of
 * the app uses for a caller that may not have what it asked for.
 */
class EnsureUserIsAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Authentication is opt-in. This is a single-user tool that normally runs
        // on a developer's own machine, and requiring a sign-in with no account
        // to create one locked the whole application behind a 403 that nobody
        // could get past.
        if (! config('whale.auth.required', false)) {
            return $next($request);
        }

        if ($request->user()) {
            return $next($request);
        }

        // A fetch() caller gets JSON explaining itself; a browser is sent to the
        // sign-in page so it can actually get in.
        if ($request->expectsJson()) {
            abort(401, 'Authentication is required.');
        }

        return redirect()->guest(route('login'));
    }
}
