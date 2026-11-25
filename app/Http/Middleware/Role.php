<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow the request only for users having one of the given roles,
 * e.g. ->middleware('role:admin,employee'). Anyone else is sent to
 * their own dashboard instead of seeing an error page.
 */
class Role
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route($user->homeRoute())
                ->with('error', 'You do not have access to that page.');
        }

        return $next($request);
    }
}
