@extends('layouts.app', ['title' => 'Oblíbené', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <h1 class="font-display text-3xl font-bold">Oblíbené inzeráty</h1>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($listings as $l) @include('partials.listing-card') @empty <p class="text-gray-600">Zatím nic. Srdíčkem u inzerátu si ho uložíte.</p> @endforelse
    </div>
    {{ $listings->links() }}
</div>
@endsection
