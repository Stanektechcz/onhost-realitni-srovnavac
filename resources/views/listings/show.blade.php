@php
    $l = $listing;
    $first = $l->photos->first();
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateListing',
        'name' => $l->displayTitle(),
        'url' => route('listings.show', $l),
        'datePosted' => $l->published_at?->toIso8601String(),
        'description' => \Illuminate\Support\Str::limit($l->description, 300),
        'image' => $l->photos->map->url()->values()->all(),
        'offers' => ['@type' => 'Offer', 'price' => $l->priceCzk(), 'priceCurrency' => 'CZK',
            'businessFunction' => $l->offer_type === 'rent' ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
            'availabilityStarts' => $l->available_from?->toDateString()],
        'about' => ['@type' => $l->property_type === 'house' ? 'SingleFamilyResidence' : 'Apartment',
            'floorSize' => ['@type' => 'QuantitativeValue', 'value' => $l->area_m2, 'unitCode' => 'MTK'],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $l->city, 'streetAddress' => $l->street, 'addressCountry' => 'CZ'],
            'geo' => $l->lat ? ['@type' => 'GeoCoordinates', 'latitude' => $l->lat, 'longitude' => $l->lng] : null,
            'petsAllowed' => $l->pets_allowed],
    ];
@endphp
@extends('layouts.app', [
    'title' => $l->displayTitle().', '.$l->city,
    'description' => \App\Models\Listing::formatCzk($l->price_halere).' – '.$l->displayTitle().', '.$l->locationLabel().'. PENB '.($l->penb === 'none' ? 'nevydán' : $l->penb).'.',
    'canonical' => route('listings.show', $l),
    'ogImage' => $first?->url(),
    'noindex' => ! $l->isPublic(),
])
@push('head')
<script type="application/ld+json">{!! json_encode(array_filter($schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@php
    $fmt = fn ($v) => number_format($v, 0, ',', ' ');
    $ppm2 = $l->pricePerM2();
    $energy = \App\Support\Calculators::energyMonthly($l->penb, (float) $l->area_m2);
    $monthly = $l->monthlyCostHalere();
    $photos = $l->photos;
@endphp
@section('content')
<div class="container-page py-6" x-data="{ gallery: false, i: 0, n: {{ max($photos->count(), 1) }} }" @keydown.escape.window="gallery = false" @keydown.arrow-right.window="gallery && (i = (i + 1) % n)" @keydown.arrow-left.window="gallery && (i = (i - 1 + n) % n)">
    @unless ($l->isPublic())
        <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Náhled — inzerát není veřejný (stav: {{ $l->status }}).@if ($l->moderation_note) Odůvodnění moderace: {{ $l->moderation_note }}@endif</div>
    @endunless
    <nav class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"><a href="{{ route('search', ['offer_type' => $l->offer_type]) }}" class="hover:text-foreground">{{ config('reality.offer_types')[$l->offer_type] }}</a> › <a class="hover:text-foreground" href="{{ route('search', ['city' => $l->city]) }}">{{ $l->city }}</a>@if ($l->district) › <a class="hover:text-foreground" href="{{ route('insights.show', \App\Services\MarketStats::slug($l->district)) }}">{{ $l->district }}</a>@endif</nav>

    <div class="mt-2 flex flex-wrap items-end gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-3xl font-bold md:text-4xl">{{ $l->displayTitle() }}</h1>
            <p class="mt-1 flex items-center gap-1 text-muted-foreground"><x-icon name="pin" class="h-4 w-4" /> {{ $l->locationLabel() }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn-outline btn-sm" x-data @click="$store.compare.toggle('{{ $l->slug }}')" :class="$store.compare.has('{{ $l->slug }}') && '!border-accent !text-accent'"><x-icon name="compare" class="h-4 w-4" /> <span x-text="$store.compare.has('{{ $l->slug }}') ? 'V porovnání' : 'Porovnat'">Porovnat</span></button>
            <button type="button" class="btn-outline btn-sm" x-data="{ ok: false }" @click="navigator.share ? navigator.share({ title: document.title, url: location.href }) : (navigator.clipboard.writeText(location.href), ok = true)"><x-icon name="arrow" class="h-4 w-4" /> <span x-text="ok ? 'Odkaz zkopírován' : 'Sdílet'">Sdílet</span></button>
        </div>
    </div>

    {{-- Gallery mosaic --}}
    <div class="mt-5 grid h-[22rem] grid-cols-4 grid-rows-2 gap-2 overflow-hidden rounded-3xl md:h-[30rem]">
        @forelse ($photos->take(5) as $idx => $p)
            <button type="button" @click="gallery = true; i = {{ $idx }}" class="group relative overflow-hidden bg-muted {{ $idx === 0 ? 'col-span-4 row-span-2 md:col-span-2' : 'hidden md:block' }}">
                <picture>
                    @if ($p->avifUrl())<source srcset="{{ $p->avifUrl() }}" type="image/avif">@endif
                    <img src="{{ $idx ? $p->thumbUrl() : $p->url() }}" alt="Fotografie {{ $idx + 1 }} – {{ $l->displayTitle() }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="{{ $idx ? 'lazy' : 'eager' }}" decoding="async" width="{{ $p->width }}" height="{{ $p->height }}">
                </picture>
                @if ($idx === 4 && $photos->count() > 5)<span class="absolute inset-0 grid place-items-center bg-black/50 font-semibold text-white">+{{ $photos->count() - 5 }} fotek</span>@endif
            </button>
        @empty
            <div class="bg-space col-span-4 row-span-2 flex items-center justify-center font-display text-6xl font-bold">{{ $l->disposition ?? config('reality.property_types')[$l->property_type] }}</div>
        @endforelse
    </div>
    @if ($photos->isNotEmpty())
        <div x-cloak x-show="gallery" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Galerie">
            <button type="button" class="absolute right-4 top-4 rounded-full bg-white/10 p-3 text-white" @click="gallery = false" aria-label="Zavřít"><x-icon name="x" /></button>
            @foreach ($photos as $idx => $p)
                <img x-show="i === {{ $idx }}" src="{{ $p->url() }}" alt="Fotografie {{ $idx + 1 }}" class="max-h-[85vh] max-w-full rounded-xl object-contain" loading="lazy">
            @endforeach
            @if ($photos->count() > 1)
                <button class="absolute left-4 top-1/2 rounded-full bg-white/10 px-4 py-3 text-2xl text-white" @click="i = (i - 1 + n) % n" aria-label="Předchozí">‹</button>
                <button class="absolute right-4 top-1/2 rounded-full bg-white/10 px-4 py-3 text-2xl text-white" @click="i = (i + 1) % n" aria-label="Další">›</button>
            @endif
            <span class="absolute bottom-4 rounded-full bg-white/10 px-3 py-1 text-sm text-white" x-text="(i + 1) + ' / ' + n"></span>
        </div>
    @endif

    <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0">
            <div class="flex flex-wrap gap-x-8 gap-y-3 border-b pb-6">
                @foreach (array_filter(['Dispozice' => $l->disposition, 'Plocha' => $l->area_m2 ? rtrim(rtrim(number_format($l->area_m2, 1, ',', ' '), '0'), ',').' m²' : null, 'Patro' => $l->floor !== null ? $l->floor.'.' : null, 'Cena/m²' => $ppm2 ? $fmt($ppm2).' Kč' : null, 'Dostupné' => $l->available_from?->isFuture() ? $l->available_from->format('j. n. Y') : 'ihned']) as $k => $v)
                    <div><p class="text-xs uppercase tracking-wide text-muted-foreground">{{ $k }}</p><p class="font-display text-xl font-bold">{{ $v }}</p></div>
                @endforeach
                <div><p class="text-xs uppercase tracking-wide text-muted-foreground">PENB</p><x-penb :value="$l->penb" /></div>
            </div>

            @if ($l->penb === 'none')
                <div class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
                    <strong>Upozornění:</strong> Průkaz energetické náročnosti budovy (PENB) nebyl vydán. Při prodeji či pronájmu je majitel ze zákona (č. 406/2000 Sb.) povinen PENB doložit.
                </div>
            @endif

            {{-- Price vs market --}}
            <section class="card mt-6 p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-display text-xl font-bold">Cena vs. trh</h2>
                    @if ($market && ($market['enough'] ?? false))
                        @php($d = $market['diff_pct'])
                        <span class="{{ $d > 10 ? 'badge-bad' : ($d < -10 ? 'badge-good' : 'badge-neutral') }} ml-auto">{{ $d > 0 ? '+' : '' }}{{ $d }} % {{ $d > 0 ? 'nad' : ($d < 0 ? 'pod' : 'na') }} mediánem</span>
                    @endif
                </div>
                @if ($market && ($market['enough'] ?? false))
                    <p class="mt-2 text-sm text-muted-foreground">Srovnání ceny za m² s {{ $market['n'] }} nabídkami ({{ $market['scope'] }}). Medián {{ $fmt($market['median']) }} Kč/m², běžné rozpětí {{ $fmt($market['p25']) }}–{{ $fmt($market['p75']) }} Kč/m².</p>
                    <x-chart.range class="mt-6" :s="$market" :min="min($market['min'], $ppm2)" :max="max($market['max'], $ppm2)" :mark="$ppm2" />
                    <div class="mt-2 flex justify-between text-xs text-muted-foreground"><span>{{ $fmt(min($market['min'], $ppm2)) }}</span><span class="font-semibold text-accent">tato nabídka {{ $fmt($ppm2) }} Kč/m²</span><span>{{ $fmt(max($market['max'], $ppm2)) }}</span></div>
                    @if ($d < -35)<p class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900">Cena je výrazně pod trhem. Buďte obezřetní a neplaťte nic před prohlídkou – <a class="underline" href="{{ route('guides.show', 'jak-poznat-podvod') }}">jak poznat podvod</a>.</p>@endif
                @else
                    <p class="mt-2 text-sm text-muted-foreground">Málo dat pro srovnání v této lokalitě{{ $market ? ' ('.$market['n'].' nabídek)' : '' }}.</p>
                @endif
            </section>

            {{-- Costs --}}
            <section class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="card p-6">
                    <h2 class="font-display text-xl font-bold">{{ $l->offer_type === 'rent' ? 'Měsíční náklady' : 'Orientační splátka' }}</h2>
                    @if ($l->offer_type === 'rent')
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-muted-foreground">Nájem</dt><dd>{{ \App\Models\Listing::formatCzk($l->price_halere) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-muted-foreground">Poplatky a zálohy</dt><dd>{{ \App\Models\Listing::formatCzk($l->fees_halere) }}</dd></div>
                            @if ($energy)<div class="flex justify-between"><dt class="text-muted-foreground">Energie (odhad dle PENB)</dt><dd>~ {{ $fmt($energy) }} Kč</dd></div>@endif
                            <div class="flex justify-between border-t pt-2 font-semibold"><dt>Celkem měsíčně</dt><dd>{{ $fmt(intdiv($monthly, 100) + ($energy ?? 0)) }} Kč</dd></div>
                            @if ($l->deposit_halere)<div class="flex justify-between text-muted-foreground"><dt>Vratná kauce</dt><dd>{{ \App\Models\Listing::formatCzk($l->deposit_halere) }}</dd></div>@endif
                        </dl>
                    @else
                        <div x-data="mortgageCalc({{ $l->priceCzk() ?? 0 }})" class="mt-3 space-y-3 text-sm">
                            <label class="block">Vlastní zdroje <span class="font-semibold" x-text="ownPct + ' %'"></span><input type="range" min="10" max="90" step="5" x-model.number="ownPct" class="range"></label>
                            <label class="block">Úrok <span class="font-semibold" x-text="rate + ' %'"></span><input type="range" min="1" max="9" step="0.1" x-model.number="rate" class="range"></label>
                            <p class="kpi text-2xl"><span x-text="fmt(payment)"></span> <span class="text-sm font-medium text-muted-foreground">Kč / měs. na 30 let</span></p>
                            <p class="text-xs text-muted-foreground">Jen orientační výpočet, nejde o nabídku ani doporučení úvěru (zákon č. 257/2016 Sb.). <a class="underline" href="{{ route('tools.calculators', ['price' => $l->priceCzk()]) }}">Podrobná kalkulačka</a></p>
                        </div>
                    @endif
                </div>
                <div class="card p-6">
                    <h2 class="font-display text-xl font-bold">Energie podle PENB</h2>
                    @if ($energy)
                        <p class="mt-3 kpi text-2xl">~ {{ $fmt($energy) }} <span class="text-sm font-medium text-muted-foreground">Kč / měs.</span></p>
                        <p class="mt-2 text-sm text-muted-foreground">Třída {{ $l->penb }} ≈ {{ \App\Support\Calculators::PENB_KWH[$l->penb] }} kWh/m²/rok × {{ $fmt($l->area_m2) }} m² × 4 Kč/kWh. Skutečnost závisí na tarifu a chování.</p>
                        <a class="mt-3 inline-block text-sm text-accent underline" href="{{ route('tools.calculators', ['tab' => 'energie', 'area' => $l->area_m2, 'penb' => $l->penb]) }}">Upravit výpočet</a>
                    @else
                        <p class="mt-3 text-sm text-muted-foreground">Bez PENB nebo plochy nelze náklady odhadnout.</p>
                    @endif
                </div>
            </section>

            <dl class="card mt-6 grid grid-cols-2 gap-x-6 gap-y-4 p-6 text-sm sm:grid-cols-3">
                @foreach ([
                    'Cena' => \App\Models\Listing::formatCzk($l->price_halere).($l->offer_type === 'rent' ? ' / měs.' : ''),
                    'Poplatky' => \App\Models\Listing::formatCzk($l->fees_halere),
                    'Kauce' => \App\Models\Listing::formatCzk($l->deposit_halere),
                    'Dispozice' => $l->disposition ?? '—',
                    'Plocha' => $l->area_m2 ? rtrim(rtrim(number_format($l->area_m2, 1, ',', ' '), '0'), ',').' m²' : '—',
                    'Patro' => $l->floor ?? '—',
                    'PENB' => $l->penb === 'none' ? 'nevydán' : $l->penb,
                    'Vybavenost' => config('reality.furnishing')[$l->furnishing] ?? '—',
                    'Dostupné od' => $l->available_from?->format('j. n. Y') ?? 'ihned',
                    'Výtah' => $l->has_elevator ? 'ano' : 'ne',
                    'Balkon' => $l->has_balcony ? 'ano' : 'ne',
                    'Parkování' => $l->has_parking ? 'ano' : 'ne',
                    'Mazlíčci' => $l->pets_allowed ? 'povoleni' : 'ne',
                ] as $k => $v)
                    <div><dt class="text-muted-foreground">{{ $k }}</dt><dd class="font-semibold">{{ $v }}</dd></div>
                @endforeach
            </dl>

            <section class="mt-8">
                <h2 class="font-display text-2xl font-bold">Popis</h2>
                <div class="mt-3 max-w-none whitespace-pre-line leading-relaxed text-foreground/90">{{ $l->description }}</div>
            </section>

            @if ($l->lat)
                <section class="mt-8" x-data="detailMap({{ $l->lat }}, {{ $l->lng }})">
                    <h2 class="font-display text-2xl font-bold">Poloha a okolí</h2>
                    <div x-ref="map" class="placeholder-map card mt-3 h-80 overflow-hidden"></div>
                    <p class="mt-1 text-xs text-muted-foreground">Poloha je orientační. Data o okolí © OpenStreetMap (Overpass API).</p>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-if="amenities === null">@for ($i = 0; $i < 3; $i++)<li class="skeleton h-16"></li>@endfor</template>
                        <template x-for="a in amenities || []" :key="a.key">
                            <li class="card p-4 text-sm"><p class="font-semibold" x-text="a.label"></p><p class="text-muted-foreground"><span x-text="a.count"></span> do 1 km<span x-show="a.walk" x-text="' · nejbližší ' + a.walk + ' min pěšky'"></span></p><p class="truncate text-xs text-muted-foreground" x-text="a.nearest?.name || ''"></p></li>
                        </template>
                    </ul>
                    <p x-cloak x-show="amenities === false" class="mt-3 text-sm text-muted-foreground">Data o okolí se nepodařilo načíst.</p>
                </section>
            @endif
        </div>

        <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
            <div class="card p-6 shadow-pop">
                <p class="font-display text-3xl font-extrabold">{{ \App\Models\Listing::formatCzk($l->price_halere) }}@if ($l->offer_type === 'rent')<span class="text-base font-normal text-muted-foreground"> / měsíc</span>@endif</p>
                @if ($l->fees_halere)<p class="text-sm text-muted-foreground">+ poplatky {{ \App\Models\Listing::formatCzk($l->fees_halere) }}</p>@endif
                <div class="mt-4 grid gap-2">
                    @if ($l->isGov())<a href="{{ $l->source_url }}" rel="noopener nofollow" target="_blank" class="btn-primary w-full"><x-icon name="mail" class="h-4 w-4" /> Kontakt u nabízejícího úřadu</a><p class="text-xs text-muted-foreground">Nabídka státu z Rejstříku nepotřebného nemovitého majetku. Podmínky prodeje a kontakt najdete u zdroje.</p>@elseif ($l->isPublic())<a href="#kontakt" class="btn-primary w-full"><x-icon name="mail" class="h-4 w-4" /> Napsat inzerentovi</a>@endif
                    @if ($slots->isNotEmpty() || $myBooking)<a href="#prohlidka" class="btn-outline w-full"><x-icon name="calendar" class="h-4 w-4" /> Rezervovat prohlídku</a>@endif
                    @auth
                        @if ($l->isPublic())
                        <form method="POST" action="{{ route('listings.favorite', $l) }}">@csrf
                            <button class="btn-ghost w-full"><x-icon name="heart" class="h-4 w-4 {{ $isFavorite ? 'fill-accent text-accent' : '' }}" /> {{ $isFavorite ? 'V oblíbených' : 'Přidat do oblíbených' }}</button>
                        </form>
                        @endif
                        @can('update', $l)
                            <a href="{{ route('owner.tools', $l) }}" class="btn-dark w-full">Statistiky a nástroje</a>
                            <a href="{{ route('listings.step', [$l, 1]) }}" class="btn-ghost w-full">Upravit inzerát</a>
                        @endcan
                    @endauth
                </div>
                <p class="mt-4 flex items-center gap-2 text-xs text-muted-foreground"><x-icon name="shield" class="h-4 w-4" /> Nikdy neplaťte zálohu před prohlídkou a ověřením vlastníka.</p>
            </div>

            @if ($agency ?? null)
                <a href="{{ route('agencies.show', $agency) }}" class="card flex items-center gap-3 p-4 text-sm transition hover:shadow-pop" data-agency-link>
                    <x-icon name="shield" class="h-5 w-5 text-accent" />
                    <span><span class="block text-xs text-muted-foreground">Nabízí ověřená realitní kancelář</span><span class="font-semibold">{{ $agency->name }}</span>@if ($agency->reviews_count) <span class="text-muted-foreground">· ★ {{ number_format($agency->rating_avg, 1, ',', '') }}</span>@endif</span>
                    <x-icon name="arrow" class="ml-auto h-4 w-4" />
                </a>
            @endif

            @if ($l->isPublic() && ($slots->isNotEmpty() || $myBooking))
                <section id="prohlidka" class="card p-6" x-data="{ pick: null }">
                    <h2 class="font-display text-xl font-bold">Prohlídka</h2>
                    @if ($myBooking)
                        <p class="mt-2 text-sm">Máte rezervováno: <strong>{{ $myBooking->starts_at->format('j. n. Y H:i') }}</strong></p>
                        <form method="POST" action="{{ route('viewings.cancel', $myBooking) }}" class="mt-3">@csrf @method('DELETE')<button class="btn-ghost btn-sm">Zrušit rezervaci</button></form>
                    @else
                        @error('slot')<p class="err">{{ $message }}</p>@enderror
                        <div class="mt-3 flex max-h-60 flex-wrap gap-1.5 overflow-y-auto">
                            @foreach ($slots as $s)
                                <button type="button" class="chip-toggle" :class="pick === {{ $s->id }} && '!bg-primary !text-primary-foreground'" @click="pick = {{ $s->id }}">{{ $s->starts_at->isoFormat('dd D. M. H:mm') }}</button>
                            @endforeach
                        </div>
                        @auth
                            <form method="POST" :action="'{{ url('/prohlidka') }}/' + pick" class="mt-4 space-y-2" x-show="pick" x-cloak>
                                @csrf
                                <x-field name="name" label="Jméno" :value="auth()->user()->name" required maxlength="100" />
                                <x-field name="phone" label="Telefon" type="tel" />
                                <x-field name="note" label="Poznámka (nepovinné)" maxlength="500" />
                                <button class="btn-primary w-full">Rezervovat termín</button>
                            </form>
                        @else
                            <p class="mt-3 text-sm"><a class="text-accent underline" href="{{ route('login') }}">Přihlaste se</a> a rezervujte si termín.</p>
                        @endauth
                    @endif
                </section>
            @endif

            @if ($l->isPublic() && $l->user_id)
            <form method="POST" action="{{ route('listings.message', $l) }}" class="card space-y-3 p-6" id="kontakt">
                @csrf
                <h2 class="font-display text-xl font-bold">Napsat inzerentovi</h2>
                <p class="text-xs text-muted-foreground">Zpráva se doručí přes portál. Inzerent neuvidí váš skutečný e-mail, dokud mu sami neodpovíte.</p>
                <div class="hidden" aria-hidden="true"><label>Web <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <x-field name="name" label="Jméno" :value="auth()->user()?->name" required maxlength="100" autocomplete="name" />
                <x-field name="email" label="E-mail" type="email" :value="auth()->user()?->email" required autocomplete="email" />
                <x-field name="phone" label="Telefon (nepovinné)" type="tel" autocomplete="tel" />
                <div>
                    <label class="label" for="f-body">Zpráva</label>
                    <textarea id="f-body" name="body" rows="4" class="input" required minlength="10" maxlength="3000">{{ old('body', 'Dobrý den, mám zájem o prohlídku. Kdy by se dalo?') }}</textarea>
                    @error('body')<p class="err">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="gdpr" value="1" required class="mt-0.5"> Souhlasím se zpracováním údajů dle <a class="text-accent underline" href="{{ route('legal', 'ochrana-osobnich-udaju') }}">zásad</a>.</label>
                @error('gdpr')<p class="err">{{ $message }}</p>@enderror
                <button class="btn-primary w-full">Odeslat zprávu</button>
            </form>

            <details class="card p-6" @if ($errors->has('reason') || $errors->has('description')) open @endif>
                <summary class="flex cursor-pointer items-center gap-2 text-sm font-semibold"><x-icon name="flag" class="h-4 w-4" /> Nahlásit inzerát / podvod</summary>
                <form method="POST" action="{{ route('listings.report', $l) }}" class="mt-3 space-y-3">
                    @csrf
                    <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                    <div>
                        <label class="label" for="r-reason">Důvod</label>
                        <select id="r-reason" name="reason" class="input" required>@foreach (config('reality.report_reasons') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="label" for="r-desc">Odůvodnění</label>
                        <textarea id="r-desc" name="description" rows="3" class="input" required minlength="10">{{ old('description') }}</textarea>
                        @error('description')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    @guest <x-field name="email" label="Váš e-mail" type="email" required /> @endguest
                    <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="good_faith" value="1" required class="mt-0.5"> Prohlašuji, že informace uvádím v dobré víře a jsou podle mého vědomí přesné a úplné.</label>
                    <button class="btn-ghost w-full">Odeslat nahlášení</button>
                    <p class="text-xs text-muted-foreground">Postup viz <a class="underline" href="{{ route('legal', 'pravidla-moderace') }}">pravidla moderace</a>.</p>
                </form>
            </details>
            @endif

            <a href="{{ route('guides.show', 'checklist-prohlidky') }}" class="card flex items-center gap-3 p-4 text-sm transition hover:shadow-pop"><x-icon name="clipboard" class="h-5 w-5 text-accent" /> Checklist na prohlídku <x-icon name="arrow" class="ml-auto h-4 w-4" /></a>
        </aside>
    </div>

    @if ($similar->isNotEmpty())
        <section class="mt-14">
            <h2 class="font-display text-2xl font-bold">Podobné v okolí</h2>
            <div class="mt-4 grid gap-5 sm:grid-cols-3">@foreach ($similar as $l) @include('partials.listing-card') @endforeach</div>
        </section>
    @endif
</div>
@endsection
