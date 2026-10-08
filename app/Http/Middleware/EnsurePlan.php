<?php

namespace App\Http\Middleware;

use App\Billing\Plan;
use App\Billing\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route unless the signed-in user's tier includes a feature.
 *
 * Used as `->middleware('plan:audio')`. This is the single place a tier is
 * enforced, so the pricing page, the API and the UI cannot disagree about what
 * someone has paid for.
 *
 * A feature nobody is on yet is not silently open: an unknown feature key is a
 * bug, and it is reported as one rather than granting access.
 */
class EnsurePlan
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Sign in to use this feature.');
        }

        // Staff operate the application rather than buy access to it.
        if ($user->isAdmin() || $user->hasRole(Role::Staff)) {
            return $next($request);
        }

        $plan = $user->plan();

        if (! in_array($feature, $plan->features(), true)) {
            abort(403, sprintf(
                'The %s plan does not include %s. %s',
                $plan->label(),
                $feature,
                $plan->upgraded() === null
                    ? 'Contact us to enable it.'
                    : 'Upgrade to '.$plan->upgraded()->label().' to use it.',
            ));
        }

        return $next($request);
    }
}
