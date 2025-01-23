<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['general_admin', 'city_admin', 'center_admin'])) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
