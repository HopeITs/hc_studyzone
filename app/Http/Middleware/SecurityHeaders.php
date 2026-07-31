<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bound before the request is handled so Blade views rendered further
        // down the pipeline (e.g. rtl-styles.blade.php) can pull the same nonce
        // via app('csp.nonce') and attach it to the inline tags they own.
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp.nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        // 'unsafe-eval' is required because Livewire's bundled Alpine.js evaluates
        // x-data/x-on expressions via `new Function(...)`; 'unsafe-inline' covers
        // Filament's own inline theme/dark-mode <style>/<script> blocks, which ship
        // without nonce support. Removing either breaks the panel.
        // Inter is self-hosted (see AdminPanelProvider::font()), so fonts.bunny.net
        // is no longer a required origin anywhere in this policy.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        // Stricter nonce-based policy shipped Report-Only: it does not block or
        // alter anything, it just tells us (via /csp-reports) which inline
        // assets are ours (nonced, would pass) vs vendor/Filament-owned (would
        // still fail on 'unsafe-eval') before we ever consider enforcing it.
        $reportOnlyCsp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self' 'nonce-{$nonce}'",
            "font-src 'self' data:",
            "img-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests",
            'report-uri '.url('/csp-reports'),
        ]);

        $response->headers->set('Content-Security-Policy-Report-Only', $reportOnlyCsp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
