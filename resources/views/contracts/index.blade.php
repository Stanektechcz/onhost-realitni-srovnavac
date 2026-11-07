@extends('layouts.account', ['title' => 'Nájemní smlouvy'])
@section('account')
<h1 class="text-3xl font-bold md:text-4xl">Nájemní smlouvy</h1>
<p class="mt-1 text-sm text-muted-foreground">Smlouvy, které uzavíráte jako pronajímatel nebo nájemce. Novou smlouvu založíte v nástrojích svého inzerátu.</p>
<div class="mt-6 space-y-3">
@forelse ($contracts as $c)
    <a href="{{ route('contracts.show', $c) }}" class="card flex items-center justify-between p-4 hover:shadow">
        <span><span class="font-semibold">{{ $c->d('property.address') }}</span><span class="block text-xs text-muted-foreground">{{ $c->landlord_id === auth()->id() ? 'Pronajímatel' : 'Nájemce' }} · {{ $c->created_at->format('j. n. Y') }}</span></span>
        <span class="chip">{{ $c->statusLabel() }}</span>
    </a>
@empty
    <p class="text-muted-foreground">Zatím žádné smlouvy.</p>
@endforelse
</div>
@endsection
