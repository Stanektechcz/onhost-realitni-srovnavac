<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'stav' => ['nullable', Rule::in(['aktivni', 'blokovany'])],
        ]);
        $users = User::query()
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('email', 'like', "%$v%")->orWhere('name', 'like', "%$v%")))
            ->when($f['role'] ?? null, fn ($q, $v) => $q->where('role', $v))
            ->when(($f['stav'] ?? null) === 'blokovany', fn ($q) => $q->where(fn ($w) => $w->whereNotNull('blocked_at')->orWhere('is_banned', true)))
            ->when(($f['stav'] ?? null) === 'aktivni', fn ($q) => $q->whereNull('blocked_at')->where('is_banned', false))
            ->withCount('listings')->latest('id')->paginate(30)->withQueryString();

        return view('admin.users.index', compact('users', 'f'));
    }

    public function show(User $user)
    {
        $user->loadCount(['listings', 'subscriptions']);
        $audit = AuditLog::with('user')->where(fn ($q) => $q->where('user_id', $user->id)
            ->orWhere(fn ($w) => $w->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)))
            ->latest('id')->limit(20)->get();

        return view('admin.users.show', compact('user', 'audit'));
    }

    public function role(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)]]);
        if ($user->is($request->user())) {
            return back()->withErrors(['role' => 'Vlastní roli nelze měnit.']);
        }
        $old = $user->role;
        if ($old !== $data['role']) {
            $user->forceFill(['role' => $data['role']])->save();
            AuditLog::record('admin.user.role', $user, ['from' => $old, 'to' => $data['role']]);
        }

        return back()->with('status', 'Role byla změněna.');
    }

    public function block(Request $request, User $user)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['block', 'unblock'])],
            'reason' => ['required_if:action,block', 'nullable', 'string', 'max:1000'],
        ]);
        if ($user->is($request->user())) {
            return back()->withErrors(['action' => 'Vlastní účet nelze zablokovat.']);
        }
        $block = $data['action'] === 'block';
        $user->forceFill($block ? ['blocked_at' => now()] : ['blocked_at' => null, 'is_banned' => false])->save();
        AuditLog::record($block ? 'admin.user.block' : 'admin.user.unblock', $user, ['reason' => $data['reason'] ?? null]);

        return back()->with('status', $block ? 'Účet byl zablokován.' : 'Účet byl odblokován.');
    }
}
