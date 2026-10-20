@extends('layouts.account', ['title' => 'Podpis nájemní smlouvy'])
@section('account')
<a href="{{ route('contracts.show', $c) }}" class="text-sm text-muted-foreground hover:text-foreground">← Zpět na smlouvu</a>
<h1 class="mt-1 text-3xl font-bold md:text-4xl">Podpis smlouvy ({{ $role === 'landlord' ? 'pronajímatel' : 'nájemce' }})</h1>
@if (session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-sm">{{ session('status') }}</p>@endif
<p class="mt-2 text-sm text-muted-foreground">Přečtěte si finální znění. Podepisujete verzi {{ $c->version }}, otisk SHA-256: <code class="break-all">{{ $sha }}</code></p>
<article class="card mt-4 max-h-[60vh] overflow-y-auto p-8 text-sm">{!! $document !!}</article>

<form method="POST" action="{{ route('contracts.code', $c) }}" class="mt-6">@csrf<button class="btn-ghost btn-sm">Poslat ověřovací kód na {{ auth()->user()->email }}</button></form>

<form method="POST" action="{{ route('contracts.sign.store', $c) }}" class="card mt-4 space-y-4 p-6">
    @csrf
    <input type="hidden" name="text_sha256" value="{{ $sha }}">
    @error('text_sha256')<p class="err">{{ $message }}</p>@enderror
    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" required> Přečetl(a) jsem si celé znění smlouvy, souhlasím s ním a chci ji podepsat elektronicky.</label>
    @error('consent')<p class="err">{{ $message }}</p>@enderror
    <div><label class="label" for="s-name">Vepište celé jméno ({{ $c->d("$role.name") }})</label><input id="s-name" name="typed_name" class="input" required autocomplete="off" value="{{ old('typed_name') }}">@error('typed_name')<p class="err">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="s-code">Ověřovací kód z e-mailu</label><input id="s-code" name="code" class="input" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code">@error('code')<p class="err">{{ $message }}</p>@enderror</div>
    <button class="btn-primary">Podepsat smlouvu</button>
</form>
@endsection
