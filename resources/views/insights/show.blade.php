@php
    $fmt = fn ($v) => number_format($v, 0, ',', ' ');
    $place = $loc['place'];
    $offerLabels = ['rent' => 'Pronájem', 'sale' => 'Prodej'];
    $h1 = match ($offer) { 'rent' => 'Byty k pronájmu – '.$place, 'sale' => 'Byty na prodej – '.$place, default => 'Byty k pronájmu a prodeji – '.$place };
    $canonical = match ($offer) { 'rent' => route('localities.rent', $loc['slug']), 'sale' => route('localities.sale', $loc['slug']), default => route('insights.show', $loc['slug']) };
    $pub = $agg['public'];
    $official = $benchmarks->first(fn ($b) => $b->area === $loc['area'] && $b->unit === 'Kč/m²' && str_contains($b->indicator, 'byty'))
        ?? $benchmarks->first(fn ($b) => $b->area === 'Česko' && $b->unit === 'Kč/m²' && str_contains($b->indicator, 'byty'));
    $ratio = $agg['rent']['enough'] && $agg['sale']['enough'] && $agg['rent']['median'] > 0 ? $agg['sale']['median'] / ($agg['rent']['median'] * 12) : null;
    $searchUrl = fn (?string $o) => route('search', array_filter(['city' => $loc['search'], 'offer_type' => $o]));

    $descParts = [];
    if ($offer !== 'sale' && $agg['rent']['enough']) $descParts[] = 'nájem medián '.$fmt($agg['rent']['median']).' Kč/m²';
    if ($offer !== 'rent' && $agg['sale']['enough']) $descParts[] = 'prodej medián '.$fmt($agg['sale']['median']).' Kč/m²';
    if ($official) $descParts[] = 'ČSÚ '.$official->period.': '.$fmt($official->value).' Kč/m²';
    $description = $h1.': '.($offer ? $pub[$offer] : array_sum($pub)).' aktuálních nabídek bez realitky'.($descParts ? ', '.implode(', ', $descParts) : '').'. Ceny za m², vývoj a oficiální statistiky.';

    $faq = [];
    if ($offer !== 'sale') {
        $faq[] = ['Kolik stojí pronájem bytu – '.$place.'?', $agg['rent']['enough']
            ? 'Medián inzerovaného nájemného je '.$fmt($agg['rent']['median']).' Kč/m² měsíčně (P25–P75: '.$fmt($agg['rent']['p25']).'–'.$fmt($agg['rent']['p75']).' Kč/m²)'.($agg['rent_price']['enough'] ? ', typický nájem bytu vychází na '.$fmt($agg['rent_price']['median']).' Kč měsíčně' : '').'. Vychází z '.$agg['rent']['n'].' nabídek za posledních 24 měsíců.'
            : 'Pro spolehlivý odhad nájemného zatím nemáme dost nabídek. Podívejte se na sousední lokality nebo aktuální nabídky.'];
    }
    if ($offer !== 'rent') {
        $faq[] = ['Kolik stojí byt na prodej – '.$place.'?', $agg['sale']['enough']
            ? 'Medián inzerované prodejní ceny je '.$fmt($agg['sale']['median']).' Kč/m² (P25–P75: '.$fmt($agg['sale']['p25']).'–'.$fmt($agg['sale']['p75']).' Kč/m²), z '.$agg['sale']['n'].' nabídek za posledních 24 měsíců.'
            : 'Pro spolehlivý odhad prodejní ceny zatím nemáme dost nabídek.'];
    }
    if ($official) {
        $faq[] = ['Jaká je skutečná kupní cena bytů podle ČSÚ?', 'Podle Českého statistického úřadu (z dat katastru) činila průměrná kupní cena bytů v oblasti '.$official->area.' v roce '.$official->period.' '.$fmt($official->value).' Kč/m²'.($official->change !== null ? ' ('.($official->change > 0 ? '+' : '').number_format($official->change, 1, ',', ' ').' % meziročně)' : '').'.'];
    }
    if ($ratio) {
        $faq[] = ['Vyplatí se byt koupit, nebo pronajmout – '.$place.'?', 'Cena bytu odpovídá zhruba '.number_format($ratio, 1, ',', ' ').' ročním nájmům (hrubý výnos přibližně '.number_format(100 / $ratio, 1, ',', ' ').' % ročně). Spočítejte si vlastní variantu v naší kalkulačce.'];
    }
    $faq[] = ['Kolik bytů je teď v nabídce – '.$place.'?', 'Aktuálně máme '.$pub['rent'].' bytů k pronájmu a '.$pub['sale'].' bytů na prodej přímo od majitelů, bez realitní provize.'];

    $crumbs = [['Domů', route('home')], ['Ceny a lokality', route('insights.index')]];
    if ($parent) $crumbs[] = [$parent['name'], route('insights.show', $parent['slug'])];
    $crumbs[] = [$loc['name'], route('insights.show', $loc['slug'])];
    if ($offer) $crumbs[] = [$offerLabels[$offer], $canonical];

    $ld = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($crumbs)->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]])->all()],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)],
    ];
@endphp
@extends('layouts.app', ['title' => $h1, 'description' => $description, 'canonical' => $canonical, 'noindex' => $noindex])
@push('head')
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="cs_CZ">
    @foreach ($ld as $block)
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
@endpush
@section('content')
<section class="bg-space">
    <div class="container-page py-12 md:py-16">
        <nav class="text-sm opacity-75" aria-label="Drobečková navigace">
            @foreach (array_slice($crumbs, 1) as $c)
                @if (! $loop->last)<a href="{{ $c[1] }}" class="hover:underline">{{ $c[0] }}</a> › @else <span>{{ $c[0] }}</span>@endif
            @endforeach
        </nav>
        <h1 class="mt-2 text-4xl font-extrabold md:text-6xl">{{ $h1 }}</h1>
        <p class="mt-3 max-w-2xl opacity-80">{{ $description }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($offer !== 'sale')<a href="{{ $searchUrl('rent') }}" class="btn-primary btn-sm">Hledat pronájem ({{ $pub['rent'] }})</a>@endif
            @if ($offer !== 'rent')<a href="{{ $searchUrl('sale') }}" class="btn-primary btn-sm">Hledat prodej ({{ $pub['sale'] }})</a>@endif
            @if ($offer)<a href="{{ route('insights.show', $loc['slug']) }}" class="btn-outline btn-sm">Pronájem i prodej</a>@endif
        </div>
        <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @php($cards = array_values(array_filter([
                $offer !== 'sale' ? ['Nájem – medián', $agg['rent'], 'Kč/m²'] : null,
                $offer !== 'rent' ? ['Prodej – medián', $agg['sale'], 'Kč/m²'] : null,
                $offer !== 'sale' ? ['Typický nájem', $agg['rent_price'], 'Kč/měs.'] : ['Typická cena', $agg['sale_price'], 'Kč'],
            ])))
            @foreach ($cards as [$lbl, $s, $u])
                <div class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15">
                    <p class="text-sm opacity-75">{{ $lbl }}</p>
                    @if ($s['enough'])
                        <p class="kpi mt-1">{{ $fmt($s['median']) }} <span class="text-sm font-medium opacity-75">{{ $u }}</span></p>
                        <p class="mt-1 text-xs opacity-75">P25–P75: {{ $fmt($s['p25']) }}–{{ $fmt($s['p75']) }}</p>
                    @else
                        <p class="kpi mt-1">–</p><p class="mt-1 text-xs opacity-75">málo dat ({{ $s['n'] }})</p>
                    @endif
                </div>
            @endforeach
            <div class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15"><p class="text-sm opacity-75">Aktuálních nabídek</p><p class="kpi mt-1">{{ $offer ? $pub[$offer] : array_sum($pub) }}</p><p class="mt-1 text-xs opacity-75">bez realitky · analýza z {{ $agg['n'] }} nabídek / 24 měs.</p></div>
        </div>
    </div>
</section>

@if (! $offer)
<div class="container-page mt-8">
    <section class="card p-6">
        <h2 class="font-display text-xl font-bold">Nájem, nebo koupě?</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="table-clean min-w-[30rem]">
                <thead><tr><th></th><th>Pronájem</th><th>Prodej</th></tr></thead>
                <tbody>
                    <tr><td class="font-semibold">Aktuální nabídky</td><td><a class="underline" href="{{ route('localities.rent', $loc['slug']) }}">{{ $pub['rent'] }}</a></td><td><a class="underline" href="{{ route('localities.sale', $loc['slug']) }}">{{ $pub['sale'] }}</a></td></tr>
                    <tr><td class="font-semibold">Medián Kč/m²</td><td>{{ $agg['rent']['enough'] ? $fmt($agg['rent']['median']).' Kč/m²/měs.' : 'málo dat' }}</td><td>{{ $agg['sale']['enough'] ? $fmt($agg['sale']['median']).' Kč/m²' : 'málo dat' }}</td></tr>
                    <tr><td class="font-semibold">Typická cena bytu</td><td>{{ $agg['rent_price']['enough'] ? $fmt($agg['rent_price']['median']).' Kč/měs.' : '–' }}</td><td>{{ $agg['sale_price']['enough'] ? $fmt($agg['sale_price']['median']).' Kč' : '–' }}</td></tr>
                </tbody>
            </table>
        </div>
        @if ($ratio)
            <p class="mt-3 text-sm">Cena bytu ≈ <strong>{{ number_format($ratio, 1, ',', ' ') }} ročních nájmů</strong> (hrubý výnos {{ number_format(100 / $ratio, 1, ',', ' ') }} % p. a.). <a class="underline" href="{{ route('tools.calculators', ['tab' => 'vynos']) }}">Spočítat výnos</a></p>
        @endif
    </section>
</div>
@endif

@if ($detail)
<div class="container-page mt-6 grid gap-6 lg:grid-cols-2">
    @foreach (array_filter(['rent' => 'Nájem', 'sale' => 'Prodej'], fn ($l, $o) => ! $offer || $offer === $o, ARRAY_FILTER_USE_BOTH) as $o => $lbl)
        <section class="card p-6">
            <h2 class="font-display text-xl font-bold">{{ $lbl }}: rozložení ceny za m²</h2>
            @if ($detail['histogram'][$o])
                <x-chart.bars class="mt-4" :data="array_column($detail['histogram'][$o], 2)" :labels="array_map(fn ($b) => $fmt($b[0]).'–'.$fmt($b[1]).' Kč/m²', $detail['histogram'][$o])" unit="nabídek" label="Histogram ceny za m² – {{ $lbl }}" />
            @else
                <p class="mt-4 grid h-40 place-items-center rounded-xl bg-muted text-sm text-muted-foreground">Málo dat</p>
            @endif
            <h3 class="mt-6 text-sm font-semibold text-muted-foreground">Vývoj mediánu za 12 měsíců</h3>
            <x-chart.line class="mt-2" :series="$detail['trend'][$o]" label="Vývoj ceny – {{ $lbl }}" />
        </section>
    @endforeach
</div>
<div class="container-page mt-6">
    <section class="card p-6">
        <h2 class="font-display text-xl font-bold">Ceny podle dispozice</h2>
        @forelse (array_filter($detail['byDisposition'], fn ($k) => ! $offer || $offer === $k, ARRAY_FILTER_USE_KEY) as $o => $rows)
            <h3 class="mt-5 text-sm font-semibold uppercase tracking-wide text-muted-foreground">{{ $o === 'rent' ? 'Nájem' : 'Prodej' }}</h3>
            <div class="mt-2 overflow-x-auto">
                <table class="table-clean min-w-[30rem]">
                    <thead><tr><th>Dispozice</th><th>Medián ceny</th><th>Rozpětí</th><th>Kč/m²</th><th>Ø plocha</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $disp => $r)
                            <tr><td class="font-semibold">{{ $disp }}</td>
                                <td>{{ $r['price']['enough'] ? $fmt($r['price']['median']).' Kč' : 'málo dat' }}</td>
                                <td class="text-muted-foreground">{{ $r['price']['enough'] ? $fmt($r['price']['p25']).'–'.$fmt($r['price']['p75']) : '–' }}</td>
                                <td>{{ $r['ppm2']['enough'] ? $fmt($r['ppm2']['median']) : '–' }}</td>
                                <td>{{ $r['area'] }} m²</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="mt-3 text-sm text-muted-foreground">Málo dat.</p>
        @endforelse
    </section>
</div>
@endif

<div class="container-page mt-6">
    @include('insights._benchmarks', ['benchmarks' => $benchmarks, 'ours' => $agg['sale']['enough'] ? $agg['sale']['median'] : null])
</div>

<section class="container-page mt-10">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h2 class="font-display text-2xl font-bold">Nejnovější nabídky – {{ $place }}</h2>
        <a href="{{ $searchUrl($offer) }}" class="btn-outline btn-sm">Všechny nabídky</a>
    </div>
    <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($listings as $l) @include('partials.listing-card') @empty <p class="text-sm text-muted-foreground">Momentálně žádné aktivní nabídky.</p> @endforelse
    </div>
</section>

@if ($children->isNotEmpty() || $neighbours->isNotEmpty())
<section class="container-page mt-10 grid gap-6 lg:grid-cols-2">
    @foreach ([['Lokality v oblasti', $children], ['Okolní lokality', $neighbours]] as [$lbl, $items])
        @if ($items->isNotEmpty())
        <div class="card p-6">
            <h2 class="font-display text-xl font-bold">{{ $lbl }}</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($items as $n)
                    <li><a class="chip" href="{{ match ($offer) { 'rent' => route('localities.rent', $n['slug']), 'sale' => route('localities.sale', $n['slug']), default => route('insights.show', $n['slug']) } }}">{{ $n['name'] }}</a></li>
                @endforeach
            </ul>
        </div>
        @endif
    @endforeach
</section>
@endif

<section class="container-page mt-10 mb-6" id="faq">
    <h2 class="font-display text-2xl font-bold">Časté otázky</h2>
    <div class="mt-4 space-y-3">
        @foreach ($faq as [$q, $a])
            <details class="card p-5" @if ($loop->first) open @endif><summary class="cursor-pointer font-semibold">{{ $q }}</summary><p class="mt-2 text-sm text-muted-foreground">{{ $a }}</p></details>
        @endforeach
    </div>
</section>
@endsection
