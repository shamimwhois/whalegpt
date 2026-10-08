<?php

namespace App\Http\Middleware;

use App\Billing\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route unless the signed-in user holds a role, or a stronger one.
 *
 * Used as `->middleware('role:admin')`. Staff outranks User and Admin outranks
 * both, so a route that needs Staff is also open to an Admin.
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Sign in to use this.');
        }

        $required = Role::tryFrom($role);

        // An unknown role name is a wiring mistake, not a silent pass.
        if ($required === null) {
            abort(500, "Unknown role [{$role}].");
        }

        if (! $user->hasRole($required)) {
            abort(403, "This area is for {$required->label()}s.");
        }

        return $next($request);
    }
}
