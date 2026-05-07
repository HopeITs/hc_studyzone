<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Check URL parameter first
        if ($request->has('locale') && in_array($request->locale, config('app.available_locales', ['en']))) {
            session(['locale' => $request->locale]);
        }

        // Get locale from session or default
        $locale = session('locale', config('app.locale'));

        if (in_array($locale, config('app.available_locales', ['en']))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
