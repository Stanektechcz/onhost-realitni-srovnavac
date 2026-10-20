@extends('layouts.account', ['title' => 'Nástroje inzerátu'])
@php($fmt = fn ($v) => number_format($v, 0, ',', ' '))
@php($l = $listing)
@section('account')
<div class="flex flex-wrap items-start gap-4">
    <div class="min-w-0 flex-1">
        <a href="{{ route('listings.mine') }}" class="text-sm text-muted-foreground hover:text-foreground">← Moje inzeráty</a>
        <h1 class="mt-1 text-3xl font-bold">{{ $l->displayTitle() }}</h1>
        <p class="text-sm text-muted-foreground">{{ $l->locationLabel() }} · {{ \App\Models\Listing::formatCzk($l->price_halere) }} · stav <strong>{{ ['draft' => 'koncept', 'pending' => 'čeká na schválení', 'active' => 'aktivní', 'expired' => 'vypršelo', 'rejected' => 'zamítnuto'][$l->status] }}</strong>@if ($l->expires_at) · platí do {{ $l->expires_at->format('j. n. Y') }}@endif</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('listings.show', $l) }}" class="btn-outline btn-sm">Zobrazit</a>
        <a href="{{ route('listings.step', [$l, 1]) }}" class="btn-outline btn-sm">Upravit</a>
        @if (in_array($l->status, ['active', 'expired'], true))
            <form method="POST" action="{{ route('owner.renew', $l) }}">@csrf<button class="btn-dark btn-sm"><x-icon name="refresh" class="h-4 w-4" /> Obnovit na {{ config('reality.listing_lifetime_days') }} dní</button></form>
        @endif
    </div>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([['views', 'Zobrazení', 'chart'], ['saves', 'Uložení do oblíbených', 'heart'], ['inquiries', 'Dotazy', 'mail'], ['bookings', 'Rezervace prohlídek', 'calendar']] as [$k, $t, $i])
        <div class="stat"><x-icon :name="$i" class="h-5 w-5 text-accent" /><p class="kpi mt-3">{{ $fmt($totals[$k]) }}</p><p class="text-sm text-muted-foreground">{{ $t }}</p><p class="mt-1 text-xs text-muted-foreground">30 dní: {{ array_sum(array_column($series, $k)) }}</p></div>
    @endforeach
</div>

<section class="card mt-6 p-6">
    <h2 class="font-display text-xl font-bold">Zobrazení za posledních 30 dní</h2>
    <x-chart.bars class="mt-4" :data="array_column($series, 'views')" :labels="array_map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('j. n.'), array_keys($series))" unit="zobrazení" label="Zobrazení za 30 dní" />
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-6">
        <div class="flex items-center justify-between"><h2 class="font-display text-xl font-bold">Kvalita inzerátu</h2><span class="kpi {{ $quality['score'] >= 80 ? 'text-success' : ($quality['score'] >= 50 ? '' : 'text-destructive') }}">{{ $quality['score'] }} %</span></div>
        <div class="mt-3 h-2 rounded-full bg-muted"><div class="bar-fill h-2 rounded-full" style="width: {{ $quality['score'] }}%"></div></div>
        <ul class="mt-4 space-y-1.5 text-sm">
            @foreach ($quality['checks'] as [$label, $pts, $ok])
                <li class="flex items-center gap-2 {{ $ok ? 'text-muted-foreground' : '' }}"><span class="grid h-5 w-5 place-items-center rounded-full {{ $ok ? 'bg-success/15 text-success' : 'bg-muted' }}">@if ($ok)<x-icon name="check" class="h-3 w-3" />@endif</span>{{ $label }} <span class="ml-auto text-xs text-muted-foreground">+{{ $pts }}</span></li>
            @endforeach
        </ul>
        @if ($quality['tips'])<a href="{{ route('listings.step', [$l, 1]) }}" class="btn-primary btn-sm mt-4">Doplnit údaje</a>@endif
    </section>

    <section class="card p-6">
        <h2 class="font-display text-xl font-bold">Odhad ceny z trhu</h2>
        @if ($estimate['enough'])
            <p class="mt-1 text-sm text-muted-foreground">Podle {{ $estimate['n'] }} porovnatelných nabídek ({{ $estimate['scope'] }}), medián {{ $fmt($estimate['ppm2']) }} Kč/m².</p>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-muted p-3"><p class="text-xs text-muted-foreground">Nižší</p><p class="font-display text-lg font-bold">{{ $fmt($estimate['low']) }}</p></div>
                <div class="rounded-xl bg-primary p-3 text-primary-foreground"><p class="text-xs opacity-80">Doporučeno</p><p class="font-display text-lg font-bold">{{ $fmt($estimate['mid']) }}</p></div>
                <div class="rounded-xl bg-muted p-3"><p class="text-xs text-muted-foreground">Vyšší</p><p class="font-display text-lg font-bold">{{ $fmt($estimate['high']) }}</p></div>
            </div>
            @if ($market && ($market['enough'] ?? false))<p class="mt-4 text-sm">Vaše cena je <strong>{{ $market['diff_pct'] > 0 ? '+' : '' }}{{ $market['diff_pct'] }} %</strong> oproti mediánu.</p>@endif
        @else
            <p class="mt-3 text-sm text-muted-foreground">Málo porovnatelných nabídek pro odhad. Zkuste <a href="{{ route('owner.estimate') }}" class="underline">obecný odhad ceny</a>.</p>
        @endif
        <a href="{{ route('tools.calculators', ['tab' => 'vynos', 'rent' => $l->offer_type === 'rent' ? $l->priceCzk() : null, 'price' => $l->offer_type === 'sale' ? $l->priceCzk() : null]) }}" class="mt-4 inline-block text-sm text-accent underline">Kalkulačka výnosu pronájmu</a>
    </section>
</div>

<section class="card mt-6 p-6" id="terminy">
    <h2 class="font-display text-xl font-bold">Plánovač prohlídek</h2>
    <p class="text-sm text-muted-foreground">Vypište časové okno, rozdělíme ho na sloty. Zájemci si je rezervují přímo v detailu inzerátu.</p>
    <form method="POST" action="{{ route('owner.slots.store', $l) }}" class="mt-4 grid gap-3 sm:grid-cols-5 sm:items-end">
        @csrf
        <x-field name="date" label="Den" type="date" :value="now()->addDay()->toDateString()" required />
        <x-field name="from" label="Od" type="time" value="17:00" required />
        <x-field name="to" label="Do" type="time" value="19:00" required />
        <div><label class="label" for="f-duration">Délka slotu</label><select id="f-duration" name="duration" class="input">@foreach ([15, 20, 30, 45, 60] as $d)<option value="{{ $d }}" @selected($d === 30)>{{ $d }} min</option>@endforeach</select></div>
        <button class="btn-primary">Přidat termíny</button>
    </form>
    <div class="mt-5 flex flex-wrap gap-2">
        @forelse ($slots as $s)
            <div class="flex items-center gap-2 rounded-full border py-1 pl-3 pr-1 text-sm {{ $s->booked_by ? 'border-success bg-success/10' : '' }}">
                {{ $s->starts_at->isoFormat('dd D. M. H:mm') }}@if ($s->booked_by) · <strong>{{ $s->booked_name }}</strong>@endif
                <form method="POST" action="{{ route('owner.slots.destroy', [$l, $s]) }}">@csrf @method('DELETE')<button class="grid h-6 w-6 place-items-center rounded-full hover:bg-muted" aria-label="Smazat termín"><x-icon name="x" class="h-3 w-3" /></button></form>
            </div>
        @empty
            <p class="text-sm text-muted-foreground">Zatím žádné termíny.</p>
        @endforelse
    </div>
</section>

<div class="mt-6 grid gap-4 md:grid-cols-3">
    @if (config('reality.features.contracts') && $l->offer_type === 'rent')
    <a href="{{ route('contracts.create', $l) }}" class="card flex items-start gap-3 p-5 transition hover:shadow-pop"><x-icon name="clipboard" class="h-6 w-6 text-accent" /><span><span class="block font-semibold">Nájemní smlouva online</span><span class="text-sm text-muted-foreground">Vyplnit, elektronicky podepsat a poslat nájemci</span></span></a>
    @endif
    <a href="{{ route('owner.handover', $l) }}" class="card flex items-start gap-3 p-5 transition hover:shadow-pop"><x-icon name="clipboard" class="h-6 w-6 text-accent" /><span><span class="block font-semibold">Předávací protokol</span><span class="text-sm text-muted-foreground">Checklist, měřiče, vybavení – k tisku</span></span></a>
    <a href="{{ route('owner.contracts') }}" class="card flex items-start gap-3 p-5 transition hover:shadow-pop"><x-icon name="doc" class="h-6 w-6 text-accent" /><span><span class="block font-semibold">Šablony smluv</span><span class="text-sm text-muted-foreground">Návrhy předvyplněné údaji inzerátu</span></span></a>
    <a href="{{ route('account.messages', ['listing' => $l->slug]) }}" class="card flex items-start gap-3 p-5 transition hover:shadow-pop"><x-icon name="mail" class="h-6 w-6 text-accent" /><span><span class="block font-semibold">Dotazy k inzerátu</span><span class="text-sm text-muted-foreground">{{ $totals['inquiries'] }} zpráv</span></span></a>
</div>

<section class="card mt-6 p-6">
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1"><h2 class="font-display text-xl font-bold">Topování</h2><p class="text-sm text-muted-foreground">Inzerát se zobrazí na předních pozicích výsledků po dobu {{ config('reality.boost.days') }} dní.@if ($l->isBoosted()) <strong>Topováno do {{ $l->boosted_until->format('j. n. Y') }}.</strong>@endif</p></div>
        @if (config('reality.features.boost') && config('reality.features.payments'))
            <form method="POST" action="{{ route('owner.boost', $l) }}">@csrf<button class="btn-primary" @disabled(! $l->isPublic())><x-icon name="bolt" class="h-4 w-4" /> Topovat za {{ \App\Models\Listing::formatCzk(config('reality.boost.price_halere')) }}</button></form>
        @else
            <span class="badge-neutral">Připravujeme</span>
        @endif
    </div>
</section>
@endsection
