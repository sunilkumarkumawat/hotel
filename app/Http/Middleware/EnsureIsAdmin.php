<?php

namespace App\Http\Middleware;

use App\Helpers\Helper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Administrators only — roles, users, branches and module management. */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Helper::isAdmin()) {
            abort(403, 'Administrators only.');
        }

        return $next($request);
    }
}
