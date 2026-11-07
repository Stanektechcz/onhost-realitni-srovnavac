<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFeed
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $agency = $token === '' ? null : User::where('feed_token_hash', hash('sha256', $token))->first();
        if (! $agency || $agency->is_banned || ! in_array($agency->role, ['agency', 'developer'], true)) {
            return response()->json(['error' => 'Neplatný nebo chybějící token.'], 401);
        }
        $request->attributes->set('feed_agency', $agency);

        return $next($request);
    }
}
