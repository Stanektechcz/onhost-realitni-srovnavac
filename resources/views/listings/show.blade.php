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
@if ($l->lat)<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css" crossorigin>@endif
@endpush
@section('content')
<div class="container-page py-6">
    @unless ($l->isPublic())
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Náhled — inzerát není veřejný (stav: {{ $l->status }}).@if ($l->moderation_note) Odůvodnění moderace: {{ $l->moderation_note }}@endif</div>
    @endunless
    <nav class="text-sm text-gray-500"><a href="{{ route('search', ['offer_type' => $l->offer_type]) }}" class="hover:underline">{{ config('reality.offer_types')[$l->offer_type] }}</a> › {{ $l->city }}@if ($l->district) › {{ $l->district }}@endif</nav>
    <div class="mt-2 grid gap-8 lg:grid-cols-[1fr_380px]">
        <div>
            <div x-data="{ i: 0, n: {{ max($l->photos->count(), 1) }} }" class="card overflow-hidden">
                <div class="relative aspect-[16/10] bg-plum-50">
                    @forelse ($l->photos as $idx => $p)
                        <picture x-show="i === {{ $idx }}" @if ($idx) x-cloak @endif>
                            @if ($p->avifUrl())<source srcset="{{ $p->avifUrl() }}" type="image/avif">@endif
                            <img src="{{ $p->url() }}" alt="Fotografie {{ $idx + 1 }} – {{ $l->displayTitle() }}" class="h-full w-full object-cover" loading="{{ $idx ? 'lazy' : 'eager' }}" decoding="async" width="{{ $p->width }}" height="{{ $p->height }}">
                        </picture>
                    @empty
                        <div class="flex h-full items-center justify-center bg-space font-display text-5xl font-bold ">{{ $l->disposition }}</div>
                    @endforelse
                    @if ($l->photos->count() > 1)
                        <button class="absolute left-2 top-1/2 rounded-full bg-white/90 p-2 shadow" @click="i = (i - 1 + n) % n" aria-label="Předchozí">‹</button>
                        <button class="absolute right-2 top-1/2 rounded-full bg-white/90 p-2 shadow" @click="i = (i + 1) % n" aria-label="Další">›</button>
                        <span class="absolute bottom-2 right-2 rounded-full bg-black/70 px-2 py-0.5 text-xs text-white" x-text="(i + 1) + ' / ' + n"></span>
                    @endif
                </div>
            </div>

            <h1 class="mt-6 text-3xl font-bold md:text-4xl sm:text-4xl">{{ $l->displayTitle() }}</h1>
            <p class="text-gray-600">{{ $l->locationLabel() }}</p>

            @if ($l->penb === 'none')
                <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
                    <strong>Upozornění:</strong> Průkaz energetické náročnosti budovy (PENB) nebyl vydán. Při prodeji či pronájmu je majitel ze zákona (č. 406/2000 Sb.) povinen PENB doložit.
                </div>
            @endif

            <dl class="card mt-6 grid grid-cols-2 gap-x-6 gap-y-3 p-5 text-sm sm:grid-cols-3">
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
                    <div><dt class="text-gray-500">{{ $k }}</dt><dd class="font-semibold">{{ $v }}</dd></div>
                @endforeach
            </dl>

            <section class="mt-6">
                <h2 class="font-display text-2xl font-bold">Popis</h2>
                <div class="prose mt-2 max-w-none whitespace-pre-line text-gray-800">{{ $l->description }}</div>
            </section>

            @if ($l->lat)
                <section class="mt-6">
                    <h2 class="font-display text-2xl font-bold">Mapa</h2>
                    <div id="dmap" class="card mt-2 h-72 overflow-hidden" data-lat="{{ $l->lat }}" data-lng="{{ $l->lng }}"></div>
                    <p class="mt-1 text-xs text-gray-500">Poloha je orientační.</p>
                </section>
            @endif
        </div>

        <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
            <div class="card p-5">
                <p class="font-display text-3xl font-extrabold text-plum">{{ \App\Models\Listing::formatCzk($l->price_halere) }}@if ($l->offer_type === 'rent')<span class="text-base font-normal text-gray-500"> / měsíc</span>@endif</p>
                @if ($l->fees_halere)<p class="text-sm text-gray-600">+ poplatky {{ \App\Models\Listing::formatCzk($l->fees_halere) }}</p>@endif
                @auth
                    @if ($l->isPublic())
                    <form method="POST" action="{{ route('listings.favorite', $l) }}" class="mt-3">@csrf
                        <button class="btn-ghost w-full">{{ $isFavorite ? '♥ V oblíbených' : '♡ Přidat do oblíbených' }}</button>
                    </form>
                    @endif
                    @can('update', $l)<a href="{{ route('listings.step', [$l, 1]) }}" class="btn-dark mt-2 w-full">Upravit inzerát</a>@endcan
                @endauth
            </div>

            @if ($l->isPublic())
            <form method="POST" action="{{ route('listings.message', $l) }}" class="card space-y-3 p-5" id="kontakt">
                @csrf
                <h2 class="font-display text-xl font-bold">Napsat inzerentovi</h2>
                <p class="text-xs text-gray-500">Zpráva se doručí přes portál. Inzerent neuvidí váš skutečný e-mail, dokud mu sami neodpovíte.</p>
                <div class="hidden" aria-hidden="true"><label>Web <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <x-field name="name" label="Jméno" :value="auth()->user()?->name" required maxlength="100" autocomplete="name" />
                <x-field name="email" label="E-mail" type="email" :value="auth()->user()?->email" required autocomplete="email" />
                <x-field name="phone" label="Telefon (nepovinné)" type="tel" autocomplete="tel" />
                <div>
                    <label class="label" for="f-body">Zpráva</label>
                    <textarea id="f-body" name="body" rows="4" class="input" required minlength="10" maxlength="3000">{{ old('body', 'Dobrý den, mám zájem o prohlídku. Kdy by se dalo?') }}</textarea>
                    @error('body')<p class="err">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="gdpr" value="1" required class="mt-0.5"> Souhlasím se zpracováním údajů dle <a class="text-pink underline" href="{{ route('legal', 'ochrana-osobnich-udaju') }}">zásad</a>.</label>
                @error('gdpr')<p class="err">{{ $message }}</p>@enderror
                <button class="btn-primary w-full">Odeslat zprávu</button>
            </form>

            <details class="card p-5" @if ($errors->has('reason') || $errors->has('description')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-gray-700">⚑ Nahlásit inzerát</summary>
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
                    <p class="text-xs text-gray-500">Postup viz <a class="underline" href="{{ route('legal', 'pravidla-moderace') }}">pravidla moderace</a>.</p>
                </form>
            </details>
            @endif
        </aside>
    </div>

    @if ($similar->isNotEmpty())
        <section class="mt-12">
            <h2 class="font-display text-2xl font-bold">Podobné v okolí</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">@foreach ($similar as $l) @include('partials.listing-card') @endforeach</div>
        </section>
    @endif
</div>
@endsection
@if ($listing->lat)
@push('scripts')
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js" crossorigin defer></script>
<script>
window.addEventListener('load', () => {
    const el = document.getElementById('dmap');
    if (!el || !window.maplibregl) return;
    const io = new IntersectionObserver((e) => {
        if (!e[0].isIntersecting) return; io.disconnect();
        const c = [parseFloat(el.dataset.lng), parseFloat(el.dataset.lat)];
        const map = new maplibregl.Map({ container: el, center: c, zoom: 14, cooperativeGestures: true,
            style: { version: 8, sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: '© OpenStreetMap contributors' } }, layers: [{ id: 'osm', type: 'raster', source: 'osm' }] } });
        new maplibregl.Marker({ color: '#F72585' }).setLngLat(c).addTo(map);
    });
    io.observe(el);
});
</script>
@endpush
@endif
