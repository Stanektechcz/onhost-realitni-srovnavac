@extends('layouts.app', ['title' => 'Uživatel '.$user->email, 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <a href="{{ route('admin.users.index') }}" class="text-sm underline">← Uživatelé</a>
    <h1 class="mt-2 font-display text-2xl font-bold">{{ $user->name }}</h1>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="card p-4 text-sm">
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                <dt class="text-gray-500">E-mail</dt><dd>{{ $user->email }}</dd>
                <dt class="text-gray-500">Telefon</dt><dd>{{ $user->phone ?: '—' }}</dd>
                <dt class="text-gray-500">Role</dt><dd>{{ $user->role }}</dd>
                <dt class="text-gray-500">Registrace</dt><dd>{{ $user->created_at?->format('j. n. Y H:i') }}</dd>
                <dt class="text-gray-500">E-mail ověřen</dt><dd>{{ $user->email_verified_at?->format('j. n. Y') ?? 'ne' }}</dd>
                <dt class="text-gray-500">{{ config('oidc.brand_name') }}</dt><dd>{{ $user->onhost_sub ? 'propojeno' : 'ne' }}</dd>
                <dt class="text-gray-500">Inzeráty</dt><dd><a class="underline" href="{{ route('admin.listings.index', ['user' => $user->id]) }}">{{ $user->listings_count }}</a></dd>
                <dt class="text-gray-500">Předplatné</dt><dd>{{ $user->subscriptions_count }}</dd>
                <dt class="text-gray-500">Stav</dt><dd>@if ($user->isBlocked())<span class="chip">zablokován{{ $user->blocked_at ? ' '.$user->blocked_at->format('j. n. Y H:i') : ' (starší blokace)' }}</span>@else<span class="chip">aktivní</span>@endif</dd>
            </dl>
        </div>
        <div class="space-y-4">
            @if ($user->is(auth()->user()))
                <p class="card p-4 text-sm text-gray-600">Vlastní roli ani blokaci nelze měnit.</p>
            @else
                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="card flex flex-wrap items-end gap-2 p-4 text-sm">
                    @csrf
                    <label class="grow">Změnit roli
                        <select name="role" class="input">
                            @foreach (\App\Models\User::ROLES as $r)<option value="{{ $r }}" @selected($user->role === $r)>{{ $r }}</option>@endforeach
                        </select>
                    </label>
                    <button class="btn-primary">Uložit roli</button>
                </form>
                <form method="POST" action="{{ route('admin.users.block', $user) }}" class="card grid gap-2 p-4 text-sm">
                    @csrf
                    @if ($user->isBlocked())
                        <input type="hidden" name="action" value="unblock">
                        <textarea name="reason" rows="2" class="input" placeholder="Poznámka (nepovinné)"></textarea>
                        <button class="btn-primary">Odblokovat účet</button>
                    @else
                        <input type="hidden" name="action" value="block">
                        <textarea name="reason" rows="2" class="input" placeholder="Důvod blokace" required></textarea>
                        <button class="btn-primary">Zablokovat účet</button>
                        <p class="text-xs text-gray-500">Zablokovaný uživatel se nepřihlásí a jeho otevřené relace se ukončí.</p>
                    @endif
                </form>
            @endif
        </div>
    </div>
    <h2 class="mt-8 font-display text-xl font-bold">Audit log</h2>
    @include('admin._audit-table', ['logs' => $audit])
</div>
@endsection
