<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showRegister() { return view('auth.register'); }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', 'in:owner,seeker,agency,developer'], // admin is never self-assignable
            'password' => ['required', 'confirmed', Password::min(10)],
            'terms' => ['accepted'],
        ]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('user.registered', $user);

        return redirect()->route('dashboard')->with('status', 'Vítejte! Účet byl vytvořen.');
    }

    public function showLogin() { return view('auth.login'); }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($cred + ['is_banned' => false], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Nesprávný e-mail nebo heslo.']);
        }
        $request->session()->regenerate();
        AuditLog::record('user.login', Auth::user());

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showConfirm() { return view('auth.confirm-password'); }

    public function confirm(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->session()->passwordConfirmed();

        return redirect()->intended(route('dashboard'));
    }

    public function destroyAccount(Request $request)
    {
        $user = $request->user();
        AuditLog::record('user.deleted', null, ['user_id' => $user->id]);
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Účet byl smazán.');
    }
}
