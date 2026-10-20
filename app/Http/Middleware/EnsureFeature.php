<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** 404 unless config('reality.features.<name>') is on. Usage: EnsureFeature::class.':contracts' */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        abort_unless(config("reality.features.$feature"), 404);

        return $next($request);
    }
}
