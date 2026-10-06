<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            Notification::make()
                ->title('الحساب غير مغعل يرجى التواصل مع مدير لوحة التحكم')
                ->danger()
                ->send();
            return redirect()->route('login')
                ->withErrors([
                    'email' => 'Your account is inactive. Please contact the administrator.',
                ]);
        }

        return $next($request);
    }
}
