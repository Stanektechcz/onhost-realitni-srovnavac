@extends('layouts.app', ['title' => 'Nemovitosti bez realitky', 'canonical' => route('home')])
@section('content')
<section class="bg-space relative overflow-hidden" x-data="{ offer: 'rent' }">
    <div class="pointer-events-none absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full bg-accent/30 blur-3xl"></div>
    <div class="container-page relative py-16 md:py-28">
        <p class="badge mb-5 bg-white/10 px-3 py-1 text-xs text-on-space ring-1 ring-white/15">Mapa · analýzy cen · nástroje pro majitele</p>
        <h1 class="max-w-3xl text-4xl font-extrabold md:text-7xl">Najděte domov.<br><span class="text-gradient">Přímo od majitelů.</span></h1>
        <p class="mt-5 max-w-xl text-lg opacity-85">{{ $count ? $count.' aktivních nabídek' : 'Nabídky' }} bez provize z ceny nemovitosti – s cenou vs. trh, okolím na mapě a rezervací prohlídky online.</p>
        <form action="{{ route('search') }}" method="GET" role="search" class="mt-10 max-w-4xl rounded-3xl bg-card p-3 text-foreground shadow-pop md:p-4">
            <input type="hidden" name="offer_type" :value="offer" value="rent">
            <div class="mb-3 pill-nav" role="tablist" aria-label="Typ nabídky">
                @foreach (config('reality.offer_types') as $k => $v)
                    <button type="button" role="tab" @click="offer = '{{ $k }}'" :aria-selected="offer === '{{ $k }}'" :class="offer === '{{ $k }}' && 'is-active'" class="pill">{{ $v }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto]">
                <div><label for="h-city" class="label">Lokalita</label><input id="h-city" name="city" class="input" placeholder="Praha, Vinohrady…"></div>
                <div><label for="h-prop" class="label">Typ</label>
                    <select id="h-prop" name="property_type" class="input"><option value="">Vše</option>@foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div><label for="h-max" class="label">Cena do</label><input id="h-max" name="price_max" type="number" min="0" class="input" placeholder="Kč"></div>
                <button class="btn-primary self-end py-3 md:px-8"><x-icon name="search" class="h-4 w-4" /> Hledat</button>
            </div>
        </form>
        <div class="mt-6 flex flex-wrap gap-2 text-sm">
            @foreach ([[route('insights.index'), 'chart', 'Ceny v lokalitách'], [route('tools.calculators'), 'calc', 'Hypoteční kalkulačka'], [route('owner.estimate'), 'tag', 'Odhad ceny nájmu'], [route('guides.index'), 'book', 'Průvodce']] as [$h, $i, $t])
                <a href="{{ $h }}" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 ring-1 ring-white/15 transition hover:bg-white/20"><x-icon :name="$i" class="h-4 w-4" /> {{ $t }}</a>
            @endforeach
        </div>
    </div>
</section>

<section class="container-page py-16 md:py-20">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <x-section-title eyebrow="Čerstvě přidané" title="Nejnovější nabídky" />
        <a href="{{ route('search') }}" class="btn-outline mb-8">Zobrazit vše na mapě <x-icon name="arrow" class="h-4 w-4" /></a>
    </div>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($latest as $l) @include('partials.listing-card') @empty
            <div class="card col-span-full p-10 text-center"><p class="font-display text-xl font-bold">Zatím tu nejsou žádné nabídky</p><p class="mt-1 text-sm text-muted-foreground">Buďte první – přidání inzerátu je zdarma.</p><a href="{{ route('listings.create') }}" class="btn-primary mt-5">Přidat inzerát</a></div>
        @endforelse
    </div>
</section>

<section class="bg-secondary/50 py-16 md:py-20">
    <div class="container-page">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <x-section-title eyebrow="Data z trhu" title="Kolik stojí bydlení v lokalitě">Medián a rozpětí inzerovaných cen za m², vývoj v čase a dostupnost služeb.</x-section-title>
            <a href="{{ route('insights.index') }}" class="btn-outline mb-8">Všechny lokality</a>
        </div>
        @if ($districts->isEmpty())
            <div class="card p-8 text-center text-muted-foreground">Zatím je málo dat pro spolehlivé statistiky. Analýzy se zobrazí, jakmile bude v lokalitě alespoň {{ \App\Services\MarketStats::MIN_SAMPLE }} nabídek.</div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($districts as $d)
                    <a href="{{ route('insights.show', $d['slug']) }}" class="card group p-5 transition hover:-translate-y-0.5 hover:shadow-pop">
                        <div class="flex items-center justify-between"><h3 class="text-xl font-bold">{{ $d['name'] }}</h3><span class="text-xs text-muted-foreground">{{ $d['count'] }} nabídek</span></div>
                        @foreach (['rent' => 'Nájem', 'sale' => 'Prodej'] as $k => $lbl)
                            @if ($d[$k]['enough'])
                                <p class="mt-3 text-sm text-muted-foreground">{{ $lbl }} – medián</p>
                                <p class="kpi text-2xl">{{ number_format($d[$k]['median'], 0, ',', ' ') }} <span class="text-sm font-medium text-muted-foreground">Kč/m²</span></p>
                                <p class="text-xs text-muted-foreground">rozpětí {{ number_format($d[$k]['p25'], 0, ',', ' ') }}–{{ number_format($d[$k]['p75'], 0, ',', ' ') }} Kč/m²</p>
                            @endif
                        @endforeach
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

<section class="container-page py-16 md:py-20">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-8">
            <p class="eyebrow">Pro hledající a kupující</p>
            <h2 class="text-3xl font-bold">Všechny informace na jednom místě</h2>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ([['map', 'Mapa s kreslením oblasti'], ['chart', 'Cena vs. trh u každé nabídky'], ['bell', 'Hlídací pes e-mailem'], ['compare', 'Porovnání až 4 nabídek'], ['calc', 'Hypotéka, náklady, dojezd'], ['leaf', 'Odhad energií podle PENB'], ['calendar', 'Online rezervace prohlídky'], ['shield', 'Nahlášení podvodu']] as [$i, $t])
                    <li class="flex items-center gap-3 text-sm"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-secondary text-primary"><x-icon :name="$i" class="h-4 w-4" /></span>{{ $t }}</li>
                @endforeach
            </ul>
            <a href="{{ route('search') }}" class="btn-dark mt-8">Začít hledat</a>
        </div>
        <div class="bg-space rounded-[calc(var(--radius)+4px)] p-8">
            <p class="eyebrow">Pro pronajímatele a prodejce</p>
            <h2 class="text-3xl font-bold">Profesionální nástroje zdarma</h2>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ([['tag', 'Odhad ceny z porovnatelných nabídek'], ['chart', 'Statistiky zobrazení a dotazů'], ['calc', 'Kalkulačka výnosu a daně'], ['calendar', 'Plánovač prohlídek'], ['doc', 'Šablony smluv'], ['clipboard', 'Předávací protokol k tisku'], ['star', 'Skóre kvality inzerátu'], ['refresh', 'Obnovení jedním klikem']] as [$i, $t])
                    <li class="flex items-center gap-3 text-sm"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10"><x-icon :name="$i" class="h-4 w-4" /></span>{{ $t }}</li>
                @endforeach
            </ul>
            <a href="{{ route('listings.create') }}" class="btn-primary mt-8">Přidat inzerát zdarma</a>
        </div>
    </div>
</section>

<section class="container-page">
    <x-section-title eyebrow="Průvodce" title="Než podepíšete" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (array_slice(config('reality_content.guides'), 0, 3, true) as $slug => $g)
            <a href="{{ route('guides.show', $slug) }}" class="card p-6 transition hover:-translate-y-0.5 hover:shadow-pop">
                <span class="badge-neutral">{{ $g['audience'] }} · {{ $g['minutes'] }} min</span>
                <h3 class="mt-3 text-xl font-bold">{{ $g['title'] }}</h3>
                <p class="mt-2 text-sm text-muted-foreground">{{ $g['lead'] }}</p>
            </a>
        @endforeach
    </div>
</section>
@endsection
