<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Zablokovaný účet (users.blocked_at) je okamžitě odhlášen, i když má platnou relaci. */
class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->isBlocked()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson()) {
                abort(403, 'Účet je zablokován.');
            }

            return redirect()->route('login')->withErrors(['email' => 'Účet je zablokován.']);
        }

        return $next($request);
    }
}
