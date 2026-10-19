<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OnhostIdClient;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Přihlášení / propojení přes ONhost ID (OIDC). Párování výhradně podle users.onhost_sub.
 * Existující účet se stejným e-mailem se NIKDY nepropojí automaticky (D4): uživatel se přihlásí heslem
 * a propojí účet z „Můj účet“ po čerstvém potvrzení hesla (důkaz na straně portálu).
 */
class OnhostIdController extends Controller
{
    public function redirect(Request $request, OnhostIdClient $client)
    {
        abort_unless($client->enabled(), 404);

        return $this->start($request, $client, false);
    }

    /** Propojení přihlášeného účtu – routa je za middleware password.confirm. */
    public function link(Request $request, OnhostIdClient $client)
    {
        abort_unless($client->enabled(), 404);

        return $this->start($request, $client, true);
    }

    public function unlink(Request $request)
    {
        $request->user()->forceFill(['onhost_sub' => null])->save();
        AuditLog::record('auth.oidc_unlinked', $request->user());

        return redirect()->route('dashboard')->with('status', 'Účet ONhost ID byl odpojen.');
    }

    public function callback(Request $request, OnhostIdClient $client)
    {
        abort_unless($client->enabled(), 404);
        $flow = (array) $request->session()->pull('oidc', []);
        $link = (bool) ($flow['link'] ?? false);
        $fail = fn (string $msg = 'Přihlášení přes ONhost ID se nezdařilo. Zkuste to prosím znovu.') => $link && $request->user()
            ? redirect()->route('dashboard')->withErrors(['onhost' => $msg])
            : redirect()->route('login')->withErrors(['email' => $msg]);

        if (! isset($flow['state']) || ! is_string($request->query('state')) || ! hash_equals($flow['state'], $request->query('state'))) {
            return $fail();
        }
        if ($request->filled('error') || ! is_string($request->query('code'))) {
            return $fail();
        }
        try {
            $identity = $client->authenticate($request->query('code'), $flow['verifier'], $flow['nonce']);
        } catch (Throwable $e) {
            AuditLog::record('auth.oidc_failed', null, ['reason' => Str::limit($e->getMessage(), 40, '')]);

            return $fail();
        }

        $linked = User::where('onhost_sub', $identity['sub'])->first();

        if ($link) {
            $current = $request->user();
            if (! $current) {
                return $fail();
            }
            if ($linked && $linked->id !== $current->id) {
                return $fail('Tento účet ONhost ID je už propojen s jiným účtem portálu.');
            }
            $current->forceFill(['onhost_sub' => $identity['sub']])->save();
            AuditLog::record('auth.oidc_linked', $current);

            return redirect()->route('dashboard')->with('status', 'Účet byl propojen s ONhost ID.');
        }

        if (! $linked) {
            if (! $identity['email'] || ! $identity['email_verified']) {
                return $fail('ONhost ID nepotvrdilo ověřený e-mail, účet nelze založit.');
            }
            if (User::where('email', $identity['email'])->exists()) {
                return $fail('Účet s tímto e-mailem už existuje. Přihlaste se heslem a propojte ONhost ID v sekci Můj účet.');
            }
            $linked = User::create([
                'name' => $identity['name'] ?: Str::before($identity['email'], '@'),
                'email' => $identity['email'],
                'password' => Str::password(40),
                'role' => 'seeker',
            ]);
            $linked->forceFill(['onhost_sub' => $identity['sub'], 'email_verified_at' => now()])->save();
            AuditLog::record('auth.oidc_registered', $linked);
        }

        if ($linked->is_banned) {
            return $fail('Účet je zablokován.');
        }

        Auth::login($linked);
        $request->session()->regenerate();
        AuditLog::record('auth.oidc_login', $linked);

        return redirect()->intended(route('dashboard'));
    }

    private function start(Request $request, OnhostIdClient $client, bool $link)
    {
        try {
            $req = $client->authorizationRequest();
        } catch (Throwable) {
            return $link
                ? redirect()->route('dashboard')->withErrors(['onhost' => 'ONhost ID je teď nedostupné.'])
                : redirect()->route('login')->withErrors(['email' => 'ONhost ID je teď nedostupné.']);
        }
        $request->session()->put('oidc', ['state' => $req['state'], 'nonce' => $req['nonce'], 'verifier' => $req['verifier'], 'link' => $link]);

        return redirect()->away($req['url']);
    }
}
