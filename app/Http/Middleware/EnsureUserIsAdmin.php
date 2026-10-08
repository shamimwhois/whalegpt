<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse anyone who is not a configured administrator.
 *
 * Admins are named by email in config/admin.php rather than by a column on the
 * users table: there is no role system to hook into, and an env allowlist keeps
 * the decision somewhere an operator can actually see. A request with no user
 * is sent to the sign-in page; a signed-in non-admin gets a plain 403.
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                abort(401, 'Authentication is required.');
            }

            return redirect()->guest(route('login'));
        }

        abort_unless($user->isAdmin(), 403, 'This account is not an administrator.');

        return $next($request);
    }
}
