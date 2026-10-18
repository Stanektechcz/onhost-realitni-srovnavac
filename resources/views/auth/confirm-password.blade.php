@extends('layouts.app', ['title' => 'Potvrzení hesla', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <form method="POST" action="{{ route('password.confirm') }}" class="card space-y-4 p-6">
        @csrf
        <h1 class="font-display text-2xl font-bold">Potvrďte heslo</h1>
        <p class="text-sm text-gray-600">Jde o citlivou akci. Pro pokračování zadejte své heslo.</p>
        <x-field name="password" label="Heslo" type="password" required autocomplete="current-password" />
        <button class="btn-primary w-full">Potvrdit</button>
    </form>
</div>
@endsection
