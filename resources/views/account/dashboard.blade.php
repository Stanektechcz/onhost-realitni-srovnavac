@extends('layouts.app', ['title' => 'Můj účet', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-5xl px-4 py-6">
    <h1 class="font-display text-3xl font-bold">Dobrý den, {{ auth()->user()->name }}</h1>
    <p class="text-sm text-gray-600">Role: {{ ['owner' => 'majitel', 'seeker' => 'hledající', 'agency' => 'realitní kancelář', 'developer' => 'developer', 'admin' => 'administrátor'][auth()->user()->role] }}</p>
    <div class="mt-6 grid gap-4 sm:grid-cols-4">
        @foreach ([[route('listings.mine'), 'Moje inzeráty', $listingCount], [route('account.favorites'), 'Oblíbené', $favoriteCount], [route('account.searches'), 'Hlídací psi', $searchCount], [route('account.messages'), 'Nové zprávy', $unread]] as [$href, $label, $n])
            <a href="{{ $href }}" class="card p-5 hover:shadow-md"><p class="font-display text-3xl font-bold text-pink">{{ $n }}</p><p class="text-sm font-semibold">{{ $label }}</p></a>
        @endforeach
    </div>
    @if (config('onhost.credit.enabled'))<div class="mt-8">@include('partials.onhost-credit')</div>@endif
    <div class="card mt-8 p-5">
        <h2 class="font-display text-xl font-bold text-red-700">Smazání účtu</h2>
        <p class="text-sm text-gray-600">Nevratně smaže účet, inzeráty, oblíbené a hlídací psy. Vyžaduje potvrzení heslem.</p>
        <form method="POST" action="{{ route('account.destroy') }}" class="mt-3" onsubmit="return confirm('Opravdu smazat účet?')">@csrf @method('DELETE')<button class="btn-ghost text-red-600">Smazat účet</button></form>
    </div>
</div>
@endsection
