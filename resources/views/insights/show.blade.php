@extends('layouts.app', ['title' => 'Ceny bydlení – '.$district['name'], 'description' => 'Medián a rozpětí cen nájmů a prodejů v lokalitě '.$district['name'].', vývoj cen, dostupnost MHD, škol a obchodů.', 'canonical' => route('insights.show', $district['slug'])])
@php($fmt = fn ($v) => number_format($v, 0, ',', ' '))
@section('content')
<section class="bg-space">
    <div class="container-page py-12 md:py-16">
        <nav class="text-sm opacity-75"><a href="{{ route('insights.index') }}" class="hover:underline">Ceny a lokality</a> › {{ $district['city'] }}</nav>
        <h1 class="mt-2 text-4xl font-extrabold md:text-6xl">{{ $district['name'] }}</h1>
        <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['Nájem – medián', $district['rent'], 'Kč/m²'], ['Prodej – medián', $district['sale'], 'Kč/m²'], ['Typický nájem', $district['rent_price'], 'Kč/měs.']] as [$lbl, $s, $u])
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
            <div class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15"><p class="text-sm opacity-75">Nabídek v analýze</p><p class="kpi mt-1">{{ $district['count'] }}</p><p class="mt-1 text-xs opacity-75">za 24 měsíců</p></div>
        </div>
    </div>
</section>

<div class="container-page mt-8 grid gap-6 lg:grid-cols-2">
    @foreach (['rent' => 'Nájem', 'sale' => 'Prodej'] as $offer => $lbl)
        <section class="card p-6">
            <h2 class="font-display text-xl font-bold">{{ $lbl }}: rozložení ceny za m²</h2>
            @if ($detail['histogram'][$offer])
                <x-chart.bars class="mt-4" :data="array_column($detail['histogram'][$offer], 2)" :labels="array_map(fn ($b) => $fmt($b[0]).'–'.$fmt($b[1]).' Kč/m²', $detail['histogram'][$offer])" unit="nabídek" label="Histogram ceny za m² – {{ $lbl }}" />
            @else
                <p class="mt-4 grid h-40 place-items-center rounded-xl bg-muted text-sm text-muted-foreground">Málo dat</p>
            @endif
            <h3 class="mt-6 text-sm font-semibold text-muted-foreground">Vývoj mediánu za 12 měsíců</h3>
            <x-chart.line class="mt-2" :series="$detail['trend'][$offer]" label="Vývoj ceny – {{ $lbl }}" />
        </section>
    @endforeach
</div>

<div class="container-page mt-6 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
    <section class="card p-6">
        <h2 class="font-display text-xl font-bold">Ceny podle dispozice</h2>
        @forelse ($detail['byDisposition'] as $offer => $rows)
            <h3 class="mt-5 text-sm font-semibold uppercase tracking-wide text-muted-foreground">{{ $offer === 'rent' ? 'Nájem' : 'Prodej' }}</h3>
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
    <section class="card p-6" @if ($district['lat']) x-data="amenities({{ $district['lat'] }}, {{ $district['lng'] }})" @endif>
        <h2 class="font-display text-xl font-bold">Dostupnost do 1 km</h2>
        @if ($district['lat'])
            <p class="mt-1 text-xs text-muted-foreground">Ze středu nabídek v lokalitě, data © OpenStreetMap (Overpass API).</p>
            <ul class="mt-4 space-y-3">
                <template x-if="items === null"><li class="space-y-2">@for ($i = 0; $i < 4; $i++)<div class="skeleton h-10"></div>@endfor</li></template>
                <template x-if="items === false"><li class="text-sm text-muted-foreground">Data o okolí se nepodařilo načíst.</li></template>
                <template x-for="a in items || []" :key="a.key">
                    <li class="flex items-center justify-between rounded-xl bg-muted px-4 py-3 text-sm"><span x-text="a.label"></span><span class="font-semibold"><span x-text="a.count"></span>× <span class="font-normal text-muted-foreground" x-show="a.walk" x-text="'· nejbližší ' + a.walk + ' min pěšky'"></span></span></li>
                </template>
            </ul>
        @else
            <p class="mt-3 text-sm text-muted-foreground">Poloha lokality není známa.</p>
        @endif
    </section>
</div>

<section class="container-page mt-10">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h2 class="font-display text-2xl font-bold">Aktuální nabídky – {{ $district['name'] }}</h2>
        <a href="{{ route('search', ['city' => $district['name']]) }}" class="btn-outline btn-sm">Všechny na mapě</a>
    </div>
    <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($listings as $l) @include('partials.listing-card') @empty <p class="text-sm text-muted-foreground">Momentálně žádné aktivní nabídky.</p> @endforelse
    </div>
</section>
@endsection
