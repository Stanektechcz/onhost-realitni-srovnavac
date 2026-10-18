@extends('layouts.app', ['title' => 'Stránka nenalezena', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-xl px-4 py-20 text-center">
    <p class="font-display text-7xl font-extrabold text-pink">404</p>
    <h1 class="mt-2 font-display text-2xl font-bold">Tady nic není</h1>
    <p class="mt-2 text-gray-600">Inzerát mohl být stažen nebo vypršel.</p>
    <a href="{{ route('search') }}" class="btn-primary mt-6">Hledat nemovitosti</a>
</div>
@endsection
