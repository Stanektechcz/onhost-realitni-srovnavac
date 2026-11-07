@extends('layouts.account', ['title' => 'Předávací protokol'])
@section('account')
<a href="{{ route('owner.tools', $listing) }}" class="text-sm text-muted-foreground hover:text-foreground">← {{ $listing->displayTitle() }}</a>
<h1 class="mt-1 text-3xl font-bold md:text-4xl">Předávací protokol</h1>
<p class="mt-1 text-sm text-muted-foreground">Vyplňte při předání bytu. Po uložení se vygeneruje verze k tisku a podpisu oběma stranami.</p>
@if ($protocols->isNotEmpty())
    <div class="mt-4 flex flex-wrap gap-2">@foreach ($protocols as $p)<a href="{{ route('owner.handover.print', [$listing, $p]) }}" class="chip">{{ $p->created_at->format('j. n. Y') }} – {{ $p->data['tenant_name'] ?? '' }}</a>@endforeach</div>
@endif
<form method="POST" action="{{ route('owner.handover.store', $listing) }}" class="mt-6 space-y-6">
    @csrf
    <section class="card grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
        <x-field name="landlord_name" label="Pronajímatel" :value="auth()->user()->name" required />
        <x-field name="tenant_name" label="Nájemce" required />
        <x-field name="date" label="Datum předání" type="date" :value="now()->toDateString()" required />
        <x-field name="keys" label="Počet klíčů" type="number" min="0" value="2" />
    </section>
    <section class="card p-6">
        <h2 class="font-display text-xl font-bold">Stavy měřičů</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">@foreach (config('reality_content.handover_meters') as $k => $v)<div><label class="label" for="m-{{ $k }}">{{ $v }}</label><input id="m-{{ $k }}" name="meters[{{ $k }}]" class="input" maxlength="40"></div>@endforeach</div>
    </section>
    <section class="card p-6">
        <h2 class="font-display text-xl font-bold">Stav místností</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach (config('reality_content.handover_rooms') as $i => $r)<div><label class="label" for="r-{{ $i }}">{{ $r }}</label><textarea id="r-{{ $i }}" name="rooms[{{ $r }}]" rows="2" class="input" placeholder="bez vad / popis"></textarea></div>@endforeach</div>
    </section>
    <section class="card grid gap-4 p-6 sm:grid-cols-2">
        <div><label class="label" for="h-inv">Soupis vybavení</label><textarea id="h-inv" name="inventory" rows="5" class="input" placeholder="lednice Bosch, pračka…"></textarea></div>
        <div><label class="label" for="h-def">Zjištěné vady</label><textarea id="h-def" name="defects" rows="5" class="input"></textarea></div>
    </section>
    <section class="card p-6">
        <h2 class="font-display text-xl font-bold">Checklist předání</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach (['Předány klíče a čipy', 'Předány návody ke spotřebičům', 'Pořízena fotodokumentace', 'Ověřena funkčnost spotřebičů', 'Předán kontakt na správce / SVJ', 'Domluven převod energií', 'Uhrazena kauce', 'Uhrazen první nájem'] as $c)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="checklist[]" value="{{ $c }}" class="h-4 w-4 accent-[var(--accent)]"> {{ $c }}</label>
            @endforeach
        </div>
    </section>
    <button class="btn-primary">Uložit a zobrazit k tisku</button>
</form>
@endsection
