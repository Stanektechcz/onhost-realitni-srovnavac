@extends('layouts.app', ['title' => 'Ceny nemovitostí podle lokalit', 'description' => 'Medián a rozpětí cen za m² nájmů a prodejů podle čtvrtí, heatmapa cen, vývoj v čase a srovnání lokalit.', 'canonical' => route('insights.index')])
@php($fmt = fn ($v) => number_format($v, 0, ',', ' '))
@php($rentAll = $districts->filter(fn ($d) => $d['rent']['enough']))
@php($scaleMin = $rentAll->min(fn ($d) => $d['rent']['p25']) ?? 0)
@php($scaleMax = $rentAll->max(fn ($d) => $d['rent']['p75']) ?? 1)
@section('content')
<section class="bg-space">
    <div class="container-page py-14 md:py-20">
        <p class="eyebrow">Analýzy trhu</p>
        <h1 class="max-w-3xl text-4xl font-extrabold md:text-6xl">Ceny a lokality</h1>
        <p class="mt-4 max-w-2xl opacity-85">Medián a rozpětí (P25–P75) inzerovaných cen za m² podle čtvrtí. Vychází z {{ $total }} nabídek za posledních 24 měsíců; jednotlivé nabídky z partnerských zdrojů se nikde nezobrazují, používají se jen v souhrnech.</p>
    </div>
</section>

<div class="container-page -mt-10 space-y-8">
    <div class="card overflow-hidden" x-data="heatMap(@js(route('insights.heat')))">
        <div class="flex flex-wrap items-center gap-3 border-b p-4">
            <h2 class="font-display text-xl font-bold">Heatmapa ceny za m²</h2>
            <div class="pill-nav ml-auto">
                <button type="button" class="pill" :class="offer === 'rent' && 'is-active'" @click="setOffer('rent')">Nájem</button>
                <button type="button" class="pill" :class="offer === 'sale' && 'is-active'" @click="setOffer('sale')">Prodej</button>
            </div>
        </div>
        <div class="relative h-[28rem]"><div x-ref="map" class="placeholder-map h-full w-full"></div>
            <p x-cloak x-show="error" class="absolute inset-0 grid place-items-center text-sm text-muted-foreground">Mapu se nepodařilo načíst.</p></div>
        <p class="border-t px-4 py-2 text-xs text-muted-foreground">Body jsou zaokrouhleny na cca 100 m. Kliknutím na kruh otevřete detail čtvrti.</p>
    </div>

    @if ($districts->isEmpty())
        <div class="card p-10 text-center">
            <x-icon name="chart" class="mx-auto h-10 w-10 text-muted-foreground" />
            <p class="mt-3 font-display text-xl font-bold">Málo dat</p>
            <p class="mt-1 text-sm text-muted-foreground">Zatím nemáme dostatek nabídek pro spolehlivé statistiky. Údaje se zobrazí automaticky, jakmile bude v lokalitě alespoň {{ \App\Services\MarketStats::MIN_SAMPLE }} nabídek.</p>
        </div>
    @else
        @if ($compare->count() > 1)
            <section class="card p-6">
                <h2 class="font-display text-2xl font-bold">Srovnání čtvrtí</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="table-clean min-w-[36rem]">
                        <thead><tr><th>Ukazatel</th>@foreach ($compare as $d)<th>{{ $d['name'] }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach (['Počet nabídek' => fn ($d) => $d['count'], 'Nájem – medián Kč/m²' => fn ($d) => $d['rent']['enough'] ? $fmt($d['rent']['median']) : 'málo dat', 'Nájem – rozpětí' => fn ($d) => $d['rent']['enough'] ? $fmt($d['rent']['p25']).'–'.$fmt($d['rent']['p75']) : '—', 'Nájem – medián měsíčně' => fn ($d) => $d['rent_price']['enough'] ? $fmt($d['rent_price']['median']).' Kč' : '—', 'Prodej – medián Kč/m²' => fn ($d) => $d['sale']['enough'] ? $fmt($d['sale']['median']) : 'málo dat', 'Prodej – rozpětí' => fn ($d) => $d['sale']['enough'] ? $fmt($d['sale']['p25']).'–'.$fmt($d['sale']['p75']) : '—'] as $label => $fn)
                                <tr><td class="font-medium">{{ $label }}</td>@foreach ($compare as $d)<td>{{ $fn($d) }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <form method="GET" class="card p-6" x-data="{ sel: @js($compare->pluck('slug')) }">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-display text-2xl font-bold">Všechny lokality</h2>
                <span class="text-sm text-muted-foreground">Zaškrtněte 2–4 čtvrti ke srovnání</span>
                <button class="btn-primary btn-sm ml-auto" :disabled="sel.length < 2">Porovnat vybrané</button>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="table-clean min-w-[44rem]">
                    <thead><tr><th class="w-8"></th><th>Čtvrť</th><th>Nabídek</th><th>Nájem Kč/m² (medián)</th><th class="w-1/3">Rozpětí P25–P75</th><th>Prodej Kč/m²</th></tr></thead>
                    <tbody>
                        @foreach ($districts as $d)
                            <tr>
                                <td><input type="checkbox" name="c[]" value="{{ $d['slug'] }}" x-model="sel" :disabled="sel.length >= 4 && !sel.includes('{{ $d['slug'] }}')" aria-label="Porovnat {{ $d['name'] }}" class="accent-[var(--accent)]"></td>
                                <td><a href="{{ route('insights.show', $d['slug']) }}" class="font-semibold hover:text-accent">{{ $d['name'] }}</a><div class="text-xs text-muted-foreground">{{ $d['city'] }}</div></td>
                                <td>{{ $d['count'] }}</td>
                                <td class="font-semibold">{{ $d['rent']['enough'] ? $fmt($d['rent']['median']) : '–' }}</td>
                                <td>@if ($d['rent']['enough'])<x-chart.range :s="$d['rent']" :min="$scaleMin" :max="$scaleMax" />@else<span class="text-xs text-muted-foreground">málo dat ({{ $d['rent']['n'] }})</span>@endif</td>
                                <td>{{ $d['sale']['enough'] ? $fmt($d['sale']['median']) : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    @endif
    @include('insights._benchmarks', ['benchmarks' => $benchmarks, 'ours' => null])
    <p class="text-xs text-muted-foreground">Metodika: cena za m² = inzerovaná cena / užitná plocha. Medián a kvartily se počítají jen při alespoň {{ \App\Services\MarketStats::MIN_SAMPLE }} nabídkách. Inzerované ceny se mohou lišit od skutečných transakčních cen.</p>
</div>
@endsection
