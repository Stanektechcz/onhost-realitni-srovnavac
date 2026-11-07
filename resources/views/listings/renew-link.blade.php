@extends('layouts.app', ['title' => 'Prodloužit inzerát', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-lg px-4 py-10">
    <div class="card p-6">
        <h1 class="font-display text-2xl font-bold">Prodloužit inzerát</h1>
        <p class="mt-2">„{{ $listing->displayTitle() }}“</p>
        @if ($usable)
            <p class="mt-2 text-sm">Inzerát bude prodloužen o {{ config('reality.listing_lifetime_days') }} dní.</p>
            <form method="POST" action="{{ request()->fullUrl() }}" class="mt-4">@csrf
                <button class="btn-primary">Potvrdit prodloužení</button>
            </form>
        @else
            <p class="mt-2 text-sm">Tento odkaz už byl použit nebo inzerát nelze prodloužit. Prodloužení najdete ve svém účtu.</p>
        @endif
    </div>
</div>
@endsection
