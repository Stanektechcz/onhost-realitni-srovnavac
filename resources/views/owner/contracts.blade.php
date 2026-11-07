@extends('layouts.account', ['title' => 'Smlouvy a protokoly'])
@section('account')
<h1 class="text-3xl font-bold md:text-4xl">Šablony smluv a protokoly</h1>
<div class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note"><strong>NÁVRH – čeká na advokáta.</strong> Šablony jsou pracovní návrhy, zatím neprošly právní revizí. Před podpisem je nechte zkontrolovat advokátem.</div>
<div class="mt-6 grid gap-4 md:grid-cols-3">
    @foreach ($contracts as $slug => $c)
        <div class="card flex flex-col p-6">
            <x-icon name="doc" class="h-7 w-7 text-accent" />
            <h2 class="mt-3 text-xl font-bold">{{ $c['title'] }}</h2>
            <p class="text-sm text-muted-foreground">{{ $c['for'] === 'rent' ? 'Pronájem' : 'Prodej' }}</p>
            <form method="GET" action="{{ route('owner.contract', $slug) }}" class="mt-auto space-y-2 pt-4">
                <label class="label" for="c-{{ $slug }}">Předvyplnit z inzerátu</label>
                <select id="c-{{ $slug }}" name="l" class="input"><option value="">— prázdná šablona —</option>@foreach ($listings as $li)<option value="{{ $li->slug }}">{{ \Illuminate\Support\Str::limit($li->displayTitle(), 40) }}</option>@endforeach</select>
                <button class="btn-outline w-full">Otevřít</button>
            </form>
        </div>
    @endforeach
</div>
<section class="card mt-6 p-6">
    <h2 class="font-display text-xl font-bold">Předávací protokol</h2>
    <p class="text-sm text-muted-foreground">Vytvoříte ho u konkrétního inzerátu.</p>
    <div class="mt-3 flex flex-wrap gap-2">@forelse ($listings as $li)<a href="{{ route('owner.handover', $li) }}" class="chip">{{ \Illuminate\Support\Str::limit($li->displayTitle(), 40) }}</a>@empty<span class="text-sm text-muted-foreground">Nemáte žádný inzerát.</span>@endforelse</div>
</section>
@endsection
