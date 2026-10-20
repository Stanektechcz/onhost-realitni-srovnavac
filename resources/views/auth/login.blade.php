@extends('layouts.app', ['title' => 'Přihlášení', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <form method="POST" action="{{ route('login') }}" class="card space-y-4 p-6">
        @csrf
        <h1 class="font-display text-3xl font-bold">Přihlášení</h1>
        <x-field name="email" label="E-mail" type="email" required autofocus autocomplete="email" />
        <x-field name="password" label="Heslo" type="password" required autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Zapamatovat si mě</label>
        <button class="btn-primary w-full">Přihlásit</button>
        @if (app(\App\Auth\OnhostIdClient::class)->enabled())
            <a href="{{ route('oidc.redirect') }}" class="btn-ghost block w-full text-center" data-oidc-login>Přihlásit přes ONhost ID</a>
        @endif
        <p class="text-center text-sm">Nemáte účet? <a href="{{ route('register') }}" class="font-semibold text-pink">Registrace</a></p>
    </form>
</div>
@endsection
