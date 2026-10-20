@extends('layouts.app', ['title' => 'Registrace', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <form method="POST" action="{{ route('register') }}" class="card space-y-4 p-6">
        @csrf
        <h1 class="font-display text-3xl font-bold">Registrace</h1>
        <x-field name="name" label="Jméno" required autocomplete="name" />
        <x-field name="email" label="E-mail" type="email" required autocomplete="email" />
        <div>
            <label class="label" for="f-role">Jsem</label>
            <select id="f-role" name="role" class="input">
                @foreach (['seeker' => 'Hledám bydlení', 'owner' => 'Majitel – chci inzerovat', 'agency' => 'Realitní kancelář', 'developer' => 'Developer'] as $k => $v)<option value="{{ $k }}" @selected(old('role') === $k)>{{ $v }}</option>@endforeach
            </select>
            @error('role')<p class="err">{{ $message }}</p>@enderror
        </div>
        <x-field name="password" label="Heslo (min. 10 znaků)" type="password" required autocomplete="new-password" />
        <x-field name="password_confirmation" label="Heslo znovu" type="password" required autocomplete="new-password" />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="mt-1"> Souhlasím s <a class="text-pink underline" href="{{ route('legal', 'obchodni-podminky') }}">obchodními podmínkami</a> a beru na vědomí <a class="text-pink underline" href="{{ route('legal', 'ochrana-osobnich-udaju') }}">zásady ochrany osobních údajů</a>.</label>
        @error('terms')<p class="err">{{ $message }}</p>@enderror
        <button class="btn-primary w-full">Vytvořit účet</button>
        @include('auth.partials.onhost-button')
    </form>
</div>
@endsection
