@extends('layouts.app', ['title' => 'Realitní srovnávač – nemovitosti bez realitky', 'canonical' => route('home'), 'description' => 'Pronájmy a prodej nemovitostí přímo od majitelů i kanceláří. Porovnání nabídek, ceny za m² z inzerátů na webu a otevřených dat ČSÚ, kalkulačky a průvodce.'])
@section('content')
<section class="bg-space relative overflow-hidden" x-data="{ offer: 'rent' }">
    <div class="pointer-events-none absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full bg-accent/30 blur-3xl" aria-hidden="true"></div>
    <div class="container-page relative py-16 md:py-28">
        <p class="badge mb-5 bg-white/10 px-3 py-1 text-xs text-on-space ring-1 ring-white/15">Realitní srovnávač · mapa · ceny lokalit · nástroje</p>
        <h1 class="max-w-3xl text-4xl font-extrabold md:text-7xl">Najděte domov.<br><span class="text-gradient">Přímo od majitelů.</span></h1>
        <p class="mt-5 max-w-xl text-lg opacity-85">{{ $count ? $count.' aktivních nabídek' : 'Nabídky' }} bez provize z ceny nemovitosti – s cenou vs. trh, okolím na mapě a rezervací prohlídky online.</p>
        <form action="{{ route('search') }}" method="GET" role="search" aria-label="Vyhledat nemovitost" class="mt-10 max-w-4xl rounded-3xl bg-card p-3 text-foreground shadow-pop md:p-4">
            <input type="hidden" name="offer_type" :value="offer" value="rent">
            <div class="mb-3 pill-nav" role="tablist" aria-label="Typ nabídky">
                @foreach (config('reality.offer_types') as $k => $v)
                    <button type="button" role="tab" @click="offer = '{{ $k }}'" :aria-selected="offer === '{{ $k }}'" :class="offer === '{{ $k }}' && 'is-active'" class="pill">{{ $v }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto]">
                <div><label for="h-city" class="label">Lokalita</label><input id="h-city" name="city" class="input" placeholder="Praha, Vinohrady…" autocomplete="address-level2"></div>
                <div><label for="h-prop" class="label">Typ</label>
                    <select id="h-prop" name="property_type" class="input"><option value="">Vše</option>@foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div><label for="h-max" class="label">Cena do</label><input id="h-max" name="price_max" type="number" min="0" inputmode="numeric" class="input" placeholder="Kč"></div>
                <button class="btn-primary self-end py-3 md:px-8"><x-icon name="search" class="h-4 w-4" /> Hledat</button>
            </div>
        </form>
        <div class="mt-6 flex flex-wrap gap-2 text-sm">
            @foreach ([[route('pages.comparator'), 'compare', 'Jak funguje srovnávač'], [route('insights.index'), 'chart', 'Ceny v lokalitách'], [route('tools.calculators'), 'calc', 'Hypoteční kalkulačka'], [route('owner.estimate'), 'tag', 'Odhad ceny nájmu'], [route('guides.index'), 'book', 'Průvodce']] as [$h, $i, $t])
                <a href="{{ $h }}" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 ring-1 ring-white/15 transition hover:bg-white/20"><x-icon :name="$i" class="h-4 w-4" /> {{ $t }}</a>
            @endforeach
        </div>
    </div>
</section>

{{-- Hodnoty: jen ověřitelná tvrzení --}}
<section class="container-page py-16 md:py-20" aria-labelledby="values-title">
    <h2 id="values-title" class="sr-only">Proč tento srovnávač</h2>
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['tag', 'Bez provize z ceny', 'Za inzerci neúčtujeme procenta z ceny nemovitosti ani z nájemného. Základní inzerát je zdarma.'],
            ['chart', 'Data se zdrojem', 'Mediány cen počítáme z inzerátů na tomto webu a doplňujeme otevřenými daty ČSÚ. Vždy uvádíme, odkud číslo je.'],
            ['shield', 'Jen vlastní nabídky', 'Zobrazujeme pouze inzeráty zadané přímo u nás majiteli a kancelářemi. Obsah jiných portálů nepřebíráme.'],
            ['user', 'Bez povinné registrace', 'Hledání, srovnávač i kalkulačky fungují bez účtu. '.config('oidc.brand_name').' je jen volitelné přihlášení.'],
        ] as [$i, $t, $d])
            <li class="card p-6">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-secondary text-primary"><x-icon :name="$i" class="h-5 w-5" /></span>
                <h3 class="mt-4 text-lg font-bold">{{ $t }}</h3>
                <p class="mt-1 text-sm text-muted-foreground">{{ $d }}</p>
            </li>
        @endforeach
    </ul>
</section>

<section class="container-page pb-16 md:pb-20">
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
            <x-section-title eyebrow="Jak to funguje" title="Tři kroky pro každého">Ať pronajímáte, hledáte, nebo zastupujete klienty jako realitní kancelář.</x-section-title>
            <a href="{{ route('pages.how') }}" class="btn-outline mb-8">Podrobný popis</a>
        </div>
        @include('pages._steps')
    </div>
</section>

{{-- Srovnávač teaser --}}
<section class="container-page py-16 md:py-20">
    <div class="grid items-start gap-8 lg:grid-cols-[1fr_1.3fr]">
        <div>
            <x-section-title eyebrow="Srovnávač" title="Porovnejte nabídky i ceny lokalit">Dejte vedle sebe až 4 inzeráty a podívejte se, jak si stojí proti mediánu ceny za m² v dané lokalitě.</x-section-title>
            <p class="-mt-4 text-sm text-muted-foreground">Mediány počítáme jen z inzerátů zveřejněných na tomto webu (inzerované, ne prodejní ceny) a doplňujeme je otevřenými daty Českého statistického úřadu s uvedením zdroje.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('compare') }}" class="btn-dark"><x-icon name="compare" class="h-4 w-4" /> Porovnat nabídky</a>
                <a href="{{ route('insights.index') }}" class="btn-outline"><x-icon name="chart" class="h-4 w-4" /> Ceny v lokalitách</a>
                <a href="{{ route('pages.comparator') }}" class="btn-ghost">Jak srovnávač počítá</a>
            </div>
        </div>
        @if ($districts->isEmpty())
            <div class="card p-8 text-center text-muted-foreground">Zatím je málo dat pro spolehlivé statistiky. Analýzy se zobrazí, jakmile bude v lokalitě alespoň {{ \App\Services\MarketStats::MIN_SAMPLE }} nabídek.</div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
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
                <p class="text-xs text-muted-foreground sm:col-span-2">Zdroj: inzeráty zveřejněné na {{ config('app.name') }}, inzerované ceny.</p>
            </div>
        @endif
    </div>
</section>

{{-- Nástroje teaser --}}
<section class="container-page pb-16 md:pb-20">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-8">
            <p class="eyebrow">Nástroje pro hledající a kupující</p>
            <h2 class="text-3xl font-bold">Spočítejte si to dřív, než podepíšete</h2>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ([['map', 'Mapa s kreslením oblasti'], ['chart', 'Cena vs. trh u každé nabídky'], ['bell', 'Hlídací pes e-mailem'], ['compare', 'Porovnání až 4 nabídek'], ['calc', 'Hypotéka, náklady, dojezd'], ['leaf', 'Odhad energií podle PENB'], ['calendar', 'Online rezervace prohlídky'], ['shield', 'Nahlášení podvodu']] as [$i, $t])
                    <li class="flex items-center gap-3 text-sm"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-secondary text-primary"><x-icon :name="$i" class="h-4 w-4" /></span>{{ $t }}</li>
                @endforeach
            </ul>
            <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('tools.calculators') }}" class="btn-dark">Kalkulačky</a><a href="{{ route('guides.index') }}" class="btn-outline">Průvodce</a></div>
        </div>
        <div class="bg-space rounded-[calc(var(--radius)+4px)] p-8">
            <p class="eyebrow">Pro pronajímatele a prodejce</p>
            <h2 class="text-3xl font-bold">Nástroje pro majitele zdarma</h2>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ([['tag', 'Odhad ceny z porovnatelných nabídek'], ['chart', 'Statistiky zobrazení a dotazů'], ['calc', 'Kalkulačka výnosu a daně'], ['calendar', 'Plánovač prohlídek'], ['doc', 'Šablony smluv'], ['clipboard', 'Předávací protokol k tisku'], ['star', 'Skóre kvality inzerátu'], ['refresh', 'Obnovení jedním klikem']] as [$i, $t])
                    <li class="flex items-center gap-3 text-sm"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10"><x-icon :name="$i" class="h-4 w-4" /></span>{{ $t }}</li>
                @endforeach
            </ul>
            <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('listings.create') }}" class="btn-primary">Přidat inzerát zdarma</a><a href="{{ route('pages.owners') }}" class="btn inline-flex bg-white/10 ring-1 ring-white/20 hover:bg-white/20">Pro majitele</a></div>
        </div>
    </div>
</section>

{{-- Katalog RK teaser --}}
<section class="bg-secondary/50 py-16 md:py-20">
    <div class="container-page grid items-center gap-8 lg:grid-cols-2">
        <div>
            <x-section-title eyebrow="Katalog realitních kanceláří" title="Kanceláře na jednom místě">Katalog vychází z veřejného rejstříku ARES. Kancelář si může profil převzít, doplnit makléře a nabídky importovat ze svého softwaru.</x-section-title>
            <div class="-mt-2 flex flex-wrap gap-3">
                <a href="{{ route('agencies.index') }}" class="btn-dark">Procházet kanceláře</a>
                <a href="{{ route('pages.agencies') }}" class="btn-outline">Jsem realitní kancelář</a>
            </div>
        </div>
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach ([['pin', 'Vyhledání podle názvu a kraje'], ['star', 'Recenze klientů s moderací'], ['user', 'Profily makléřů'], ['refresh', 'Import přes feed API nebo RPC2']] as [$i, $t])
                <li class="card flex items-center gap-3 p-4 text-sm"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-secondary text-primary"><x-icon :name="$i" class="h-4 w-4" /></span>{{ $t }}</li>
            @endforeach
        </ul>
    </div>
</section>

<section class="container-page py-16 md:py-20">
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

<section class="container-page" aria-labelledby="faq-title">
    <div class="grid gap-8 lg:grid-cols-[1fr_2fr]">
        <div>
            <p class="eyebrow">Časté dotazy</p>
            <h2 id="faq-title" class="text-3xl font-bold md:text-4xl">Na co se ptáte</h2>
            <a href="{{ route('pages.faq') }}" class="btn-outline mt-6">Všechny dotazy</a>
        </div>
        @include('pages._faq', ['items' => array_slice(\App\Http\Controllers\PagesController::FAQ, 0, 5)])
    </div>
</section>

@include('pages._cta')
@endsection
