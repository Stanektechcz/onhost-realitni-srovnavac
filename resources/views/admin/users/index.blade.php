@extends('layouts.app', ['title' => 'Uživatelé', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Uživatelé</h1>
    <form method="GET" class="mt-4 flex flex-wrap items-end gap-2 text-sm">
        <label class="grow">Hledat (e-mail nebo jméno)<input name="q" class="input" value="{{ $f['q'] ?? '' }}"></label>
        <label>Role
            <select name="role" class="input">
                <option value="">vše</option>
                @foreach (\App\Models\User::ROLES as $r)<option value="{{ $r }}" @selected(($f['role'] ?? '') === $r)>{{ $r }}</option>@endforeach
            </select>
        </label>
        <label>Stav
            <select name="stav" class="input">
                <option value="">vše</option>
                <option value="aktivni" @selected(($f['stav'] ?? '') === 'aktivni')>aktivní</option>
                <option value="blokovany" @selected(($f['stav'] ?? '') === 'blokovany')>zablokovaný</option>
            </select>
        </label>
        <button class="btn-primary">Hledat</button>
    </form>
    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-plum-50"><tr><th class="p-2">#</th><th class="p-2">Jméno</th><th class="p-2">E-mail</th><th class="p-2">Role</th><th class="p-2">Inzeráty</th><th class="p-2">Registrace</th><th class="p-2">Stav</th></tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr class="border-t">
                        <td class="p-2">{{ $u->id }}</td>
                        <td class="p-2"><a class="underline" href="{{ route('admin.users.show', $u) }}">{{ $u->name }}</a></td>
                        <td class="p-2">{{ $u->email }}</td>
                        <td class="p-2">{{ $u->role }}</td>
                        <td class="p-2">{{ $u->listings_count }}</td>
                        <td class="p-2">{{ $u->created_at?->format('j. n. Y') }}</td>
                        <td class="p-2"><span class="chip">{{ $u->isBlocked() ? 'zablokovaný' : 'aktivní' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-gray-600">Žádní uživatelé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection
