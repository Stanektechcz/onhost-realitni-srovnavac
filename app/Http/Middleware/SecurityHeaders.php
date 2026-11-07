<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

class SecurityHeaders
{
    /** Dlaždice a styl mapy (OpenFreeMap); MapLibre je self-hosted ve Vite buildu a používá blob: workery. */
    private const MAP_ORIGIN = 'https://tiles.openfreemap.org';

    public function handle(Request $request, Closure $next)
    {
        $res = $next($request);
        $res->headers->set('X-Content-Type-Options', 'nosniff');
        $res->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $res->headers->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)');
        if (! config('reality.security_headers')) {
            $res->headers->set('X-Frame-Options', 'SAMEORIGIN');

            return $res;
        }
        $res->headers->set('X-Frame-Options', 'DENY');
        $res->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if (! Vite::isRunningHot() && ! $res->headers->has('Content-Security-Policy')) {
            $res->headers->set('Content-Security-Policy', $this->csp());
        }
        if (app()->environment('production') && $request->isSecure()) {
            $res->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $res;
    }

    private function csp(): string
    {
        $m = self::MAP_ORIGIN;

        // 'unsafe-inline' / 'unsafe-eval' kvůli stávajícím inline obsluhám a Alpine výrazům; zbytek je striktní.
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: {$m}",
            "connect-src 'self' {$m}",
            "worker-src 'self' blob:",
            "child-src 'self' blob:",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
        ]);
    }
}
