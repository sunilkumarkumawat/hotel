<?php

namespace App\Http\Middleware;

use App\Helpers\Helper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: ->middleware('permission:room-list,add')
 *
 * The first argument is a submodule url, the second the action
 * (view | add | edit | delete). Administrators bypass the check.
 */
class CheckUserPermission
{
    public function handle(Request $request, Closure $next, string $submodule, string $action = 'view'): Response
    {
        if (! Helper::can($submodule, $action)) {
            abort(403, 'You do not have permission to ' . $action . ' here.');
        }

        return $next($request);
    }
}
