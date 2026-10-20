<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OnhostIdClient;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OnhostIdController extends Controller
{
    public function __construct(private OnhostIdClient $client) {}

    public function redirect(Request $request)
    {
        abort_unless($this->client->enabled(), 404);
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return $this->start($request, null);
    }

    /** Opt-in linking from the account page; requires the local password. */
    public function linkFromAccount(Request $request)
    {
        abort_unless($this->client->enabled(), 404);
        $request->validate(['password' => ['required', 'current_password']]);
        if ($request->user()->onhost_sub) {
            return back()->with('status', 'Účet už je propojen s ONhost ID.');
        }

        return $this->start($request, $request->user()->id);
    }

    private function start(Request $request, ?int $linkUserId)
    {
        try {
            [$url, $state, $nonce, $verifier] = $this->client->authorizationRequest();
        } catch (RuntimeException) {
            return $this->fail();
        }
        $request->session()->put('oidc', ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'link_user' => $linkUserId]);

        return redirect()->away($url);
    }

    public function callback(Request $request)
    {
        abort_unless($this->client->enabled(), 404);
        $flow = $request->session()->pull('oidc');
        $state = $request->query('state');
        if (! is_array($flow) || ! is_string($state) || ! hash_equals($flow['state'], $state) || ! is_string($request->query('code'))) {
            return $this->fail();
        }
        try {
            $claims = $this->client->exchange($request->query('code'), $flow['verifier'], $flow['nonce']);
        } catch (RuntimeException) {
            return $this->fail();
        }
        $sub = $claims['sub'];

        if ($flow['link_user']) {
            $user = $request->user();
            if (! $user || $user->id !== $flow['link_user'] || $user->onhost_sub) {
                return $this->fail();
            }
            if (User::where('onhost_sub', $sub)->exists()) {
                return redirect()->route('dashboard')->with('status', 'Tento ONhost ID účet je už propojen s jiným účtem.');
            }
            $user->forceFill(['onhost_sub' => $sub])->save();
            AuditLog::record('user.onhost_linked', $user);

            return redirect()->route('dashboard')->with('status', 'Účet byl propojen s ONhost ID.');
        }

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        if ($user = User::where('onhost_sub', $sub)->first()) {
            return $this->login($request, $user);
        }

        $email = is_string($claims['email'] ?? null) ? Str::lower(trim($claims['email'])) : null;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('ONhost ID neposkytl platný e-mail.');
        }
        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            // Never auto-link by e-mail (D4): offer opt-in linking proved by the local password.
            $request->session()->put('oidc_pending', ['sub' => $sub, 'email' => $email, 'at' => time()]);

            return redirect()->route('onhost.link');
        }

        $user = new User;
        $user->forceFill([
            'name' => Str::limit(trim((string) ($claims['name'] ?? '')) ?: Str::before($email, '@'), 120, ''),
            'email' => $email,
            'password' => Str::random(64), // random, never disclosed – unusable for password login
            'role' => config('oidc.default_role', 'seeker'),
            'onhost_sub' => $sub,
            'email_verified_at' => ($claims['email_verified'] ?? false) === true ? now() : null,
        ])->save();
        AuditLog::record('user.registered', $user, ['via' => 'onhost_id']);

        return $this->login($request, $user);
    }

    public function showLink(Request $request)
    {
        abort_unless($this->client->enabled(), 404);
        $pending = $this->pending($request);
        abort_unless($pending, 404);

        return view('auth.onhost-link', ['email' => $pending['email']]);
    }

    public function link(Request $request)
    {
        abort_unless($this->client->enabled(), 404);
        $pending = $this->pending($request);
        abort_unless($pending, 404);
        $request->validate(['password' => ['required', 'string']]);
        $user = User::whereRaw('lower(email) = ?', [$pending['email']])->first();
        if (! $user || $user->is_banned || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Nesprávné heslo.']);
        }
        if ($user->onhost_sub || User::where('onhost_sub', $pending['sub'])->exists()) {
            $request->session()->forget('oidc_pending');

            return $this->fail('Účet už je propojen s jiným ONhost ID.');
        }
        $request->session()->forget('oidc_pending');
        $user->forceFill(['onhost_sub' => $pending['sub']])->save();
        AuditLog::record('user.onhost_linked', $user);

        return $this->login($request, $user);
    }

    public function backchannelLogout()
    {
        abort_unless($this->client->enabled(), 404);

        return response()->json(['error' => 'not_implemented'], 501); // etapa I-3
    }

    private function pending(Request $request): ?array
    {
        $p = $request->session()->get('oidc_pending');

        return is_array($p) && time() - $p['at'] <= 600 ? $p : null;
    }

    private function login(Request $request, User $user)
    {
        if ($user->is_banned) {
            return $this->fail();
        }
        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('user.login', $user, ['via' => 'onhost_id']);

        return redirect()->intended(route('dashboard'));
    }

    private function fail(string $msg = 'Přihlášení přes ONhost ID se nezdařilo. Zkuste to prosím znovu.')
    {
        return redirect()->route(Auth::check() ? 'dashboard' : 'login')->withErrors(['onhost' => $msg]);
    }
}
