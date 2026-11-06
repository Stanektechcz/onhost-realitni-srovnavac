<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OnhostIdClient;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            // Nový účet vzniká až po souhlasu s podmínkami portálu (stejně jako u registrace heslem).
            $request->session()->put('oidc_signup', ['sub' => $identity['sub'], 'email' => $identity['email'], 'name' => $identity['name'], 'at' => time()]);

            return redirect()->route('oidc.signup');
        }

        if ($linked->is_banned) {
            return $fail('Účet je zablokován.');
        }

        Auth::login($linked);
        $request->session()->regenerate();
        AuditLog::record('auth.oidc_login', $linked);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Back-channel logout z id.onhost.cz: ukončí všechny relace uživatele s daným sub. Za vlastním příznakem
     * ONHOST_OIDC_BACKCHANNEL (etapa I-3). Odpověď bez těla, chyby jen 400 bez detailu.
     */
    public function backchannelLogout(Request $request, OnhostIdClient $client)
    {
        abort_unless($client->enabled() && config('oidc.backchannel_logout'), 404);
        $token = $request->input('logout_token');
        if (! is_string($token) || strlen($token) > 8192) {
            return response()->noContent(400)->header('Cache-Control', 'no-store');
        }
        try {
            $sub = $client->verifyLogoutToken($token);
        } catch (Throwable $e) {
            AuditLog::record('auth.oidc_logout_rejected', null, ['reason' => Str::limit($e->getMessage(), 40, '')]);

            return response()->noContent(400)->header('Cache-Control', 'no-store');
        }
        $user = User::where('onhost_sub', $sub)->first();
        if ($user) {
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            AuditLog::record('auth.oidc_backchannel_logout', $user);
        }

        return response()->noContent(200)->header('Cache-Control', 'no-store');
    }

    /** Souhlas s podmínkami pro nový účet z ONhost ID (ověřená identita čeká v session nejvýš 15 minut). */
    public function signup(Request $request, OnhostIdClient $client)
    {
        abort_unless($client->enabled(), 404);
        $pending = $this->pendingSignup($request);
        if (! $pending) {
            return redirect()->route('login')->withErrors(['email' => 'Přihlášení přes ONhost ID vypršelo. Zkuste to prosím znovu.']);
        }

        return view('auth.onhost-signup', ['email' => $pending['email']]);
    }

    public function completeSignup(Request $request, OnhostIdClient $client)
    {
        abort_unless($client->enabled(), 404);
        $pending = $this->pendingSignup($request);
        if (! $pending) {
            return redirect()->route('login')->withErrors(['email' => 'Přihlášení přes ONhost ID vypršelo. Zkuste to prosím znovu.']);
        }
        $request->validate(['terms' => ['accepted']]);
        $request->session()->forget('oidc_signup');
        if (User::where('onhost_sub', $pending['sub'])->orWhere('email', $pending['email'])->exists()) {
            return redirect()->route('login')->withErrors(['email' => 'Účet s tímto e-mailem už existuje. Přihlaste se heslem a propojte ONhost ID v sekci Můj účet.']);
        }
        $user = User::create([
            'name' => $pending['name'] ?: Str::before($pending['email'], '@'),
            'email' => $pending['email'],
            'password' => Str::password(40),
            'role' => 'seeker',
        ]);
        $user->forceFill(['onhost_sub' => $pending['sub'], 'email_verified_at' => now()])->save();
        AuditLog::record('auth.oidc_registered', $user, ['terms_accepted' => true]);
        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('auth.oidc_login', $user);

        return redirect()->intended(route('dashboard'));
    }

    /** @return array{sub: string, email: string, name: ?string, at: int}|null */
    private function pendingSignup(Request $request): ?array
    {
        $p = $request->session()->get('oidc_signup');
        if (! is_array($p) || ! is_string($p['sub'] ?? null) || ! is_string($p['email'] ?? null) || time() - (int) ($p['at'] ?? 0) > 900) {
            $request->session()->forget('oidc_signup');

            return null;
        }

        return $p;
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
