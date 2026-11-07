@extends('layouts.app', ['title' => 'Nový účet přes '.config('oidc.brand_name'), 'noindex' => true])
@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <form method="POST" action="{{ route('oidc.signup.store') }}" class="card space-y-4 p-6">
        @csrf
        <h1 class="font-display text-3xl font-bold">Nový účet přes {{ config('oidc.brand_name') }}</h1>
        <p class="text-sm">Založíme účet pro <strong>{{ $email }}</strong>. Před založením potřebujeme váš souhlas s podmínkami portálu.</p>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="mt-1"> Souhlasím s <a class="text-pink underline" href="{{ route('legal', 'obchodni-podminky') }}">obchodními podmínkami</a> a beru na vědomí <a class="text-pink underline" href="{{ route('legal', 'ochrana-osobnich-udaju') }}">zásady ochrany osobních údajů</a>.</label>
        @error('terms')<p class="err">{{ $message }}</p>@enderror
        <button class="btn-primary w-full">Vytvořit účet</button>
        <a class="block text-center text-sm underline" href="{{ route('login') }}">Zpět na přihlášení</a>
    </form>
</div>
@endsection
