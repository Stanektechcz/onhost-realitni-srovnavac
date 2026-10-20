@extends('layouts.app', ['title' => 'Propojení s ONhost ID', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <form method="POST" action="{{ route('onhost.link') }}" class="card space-y-4 p-6">
        @csrf
        <h1 class="font-display text-3xl font-bold">Propojit s ONhost ID?</h1>
        <p class="text-sm">U nás už existuje účet s e-mailem <strong>{{ $email }}</strong>. Účty automaticky nespojujeme. Pokud je účet váš, potvrďte propojení svým heslem k tomuto portálu.</p>
        <x-field name="password" label="Heslo k účtu na portálu" type="password" required autocomplete="current-password" />
        <button class="btn-primary w-full">Propojit a přihlásit</button>
        <p class="text-center text-sm"><a href="{{ route('login') }}" class="text-pink">Zpět na přihlášení</a></p>
    </form>
</div>
@endsection
