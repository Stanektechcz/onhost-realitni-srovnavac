<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Objednávková cesta z katalogu ONhost (`?offer=…&source=onhost-id`). Jen si zapamatuje původ v session;
 * tok aplikace je běžný a ONhost ID je volitelné. Neplatné hodnoty se tiše ignorují.
 */
class RememberEcosystemReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        $offer = $request->query('offer');
        if ($request->hasSession() && $request->query('source') === 'onhost-id'
            && is_string($offer) && preg_match('/^[a-z0-9_-]{1,64}$/D', $offer) === 1) {
            $request->session()->put('ecosystem.referral', ['offer' => $offer, 'source' => 'onhost-id']);
        }

        return $next($request);
    }
}
