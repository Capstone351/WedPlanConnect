<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level RBAC: the route itself is the security boundary, not hidden links.
 * Usage: ->middleware('role:admin,planner')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && $user->hasRole(...$roles), 403, 'You are not authorized to access this page.');

        return $next($request);
    }
}
