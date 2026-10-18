@extends('layouts.app', ['title' => 'Hledání nemovitostí', 'canonical' => route('search', array_intersect_key($filters, array_flip(['offer_type', 'property_type'])))])
@php($f = $filters)
@push('head')
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css" crossorigin>
@endpush
@section('content')
<div class="mx-auto max-w-7xl px-4 py-6" x-data="{ filters: false, view: 'list' }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-display text-3xl font-bold">{{ isset($f['offer_type']) ? config('reality.offer_types')[$f['offer_type']] : 'Všechny nabídky' }} <span class="text-lg font-normal text-gray-500">({{ $listings->total() }})</span></h1>
        <div class="flex gap-2">
            <button type="button" class="btn-ghost lg:hidden" @click="filters = !filters">Filtry</button>
            <button type="button" class="btn-ghost" @click="view = view === 'list' ? 'map' : 'list'; if (view === 'map') $nextTick(() => window.initMap && window.initMap())" x-text="view === 'list' ? 'Mapa' : 'Seznam'">Mapa</button>
        </div>
    </div>
    <div class="mt-4 grid gap-6 lg:grid-cols-[280px_1fr]">
        <form method="GET" action="{{ route('search') }}" class="card space-y-4 p-4 lg:block" :class="filters ? 'block' : 'hidden'" id="filters">
            <div>
                <label class="label" for="s-offer">Nabídka</label>
                <select id="s-offer" name="offer_type" class="input"><option value="">Vše</option>@foreach (config('reality.offer_types') as $k => $v)<option value="{{ $k }}" @selected(($f['offer_type'] ?? '') === $k)>{{ $v }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label" for="s-prop">Typ</label>
                <select id="s-prop" name="property_type" class="input"><option value="">Vše</option>@foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}" @selected(($f['property_type'] ?? '') === $k)>{{ $v }}</option>@endforeach</select>
            </div>
            <x-field name="city" label="Lokalita" :value="$f['city'] ?? ''" placeholder="Praha, Karlín…" />
            <fieldset>
                <legend class="label">Dispozice</legend>
                <div class="flex flex-wrap gap-1">
                    @foreach (config('reality.dispositions') as $d)
                        <label class="cursor-pointer"><input type="checkbox" name="disposition[]" value="{{ $d }}" class="peer sr-only" @checked(in_array($d, $f['disposition'] ?? []))><span class="chip peer-checked:bg-pink peer-checked:text-white">{{ $d }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid grid-cols-2 gap-2">
                <x-field name="price_min" label="Cena od (Kč)" type="number" min="0" :value="$f['price_min'] ?? ''" />
                <x-field name="price_max" label="Cena do (Kč)" type="number" min="0" :value="$f['price_max'] ?? ''" />
                <x-field name="area_min" label="Plocha od m²" type="number" min="0" :value="$f['area_min'] ?? ''" />
                <x-field name="area_max" label="Plocha do m²" type="number" min="0" :value="$f['area_max'] ?? ''" />
            </div>
            <fieldset>
                <legend class="label">PENB</legend>
                <div class="flex flex-wrap gap-1">
                    @foreach (config('reality.penb') as $p)
                        <label class="cursor-pointer"><input type="checkbox" name="penb[]" value="{{ $p }}" class="peer sr-only" @checked(in_array($p, $f['penb'] ?? []))><span class="chip peer-checked:bg-pink peer-checked:text-white">{{ $p === 'none' ? 'nevydán' : $p }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <div class="space-y-1 text-sm">
                @foreach (['has_balcony' => 'Balkon / terasa', 'has_elevator' => 'Výtah', 'has_parking' => 'Parkování', 'pets_allowed' => 'Mazlíčci povoleni'] as $k => $v)
                    <label class="flex items-center gap-2"><input type="checkbox" name="{{ $k }}" value="1" class="rounded text-pink" @checked(! empty($f[$k]))> {{ $v }}</label>
                @endforeach
            </div>
            <div>
                <label class="label" for="s-sort">Řazení</label>
                <select id="s-sort" name="sort" class="input">
                    @foreach (['newest' => 'Nejnovější', 'price_asc' => 'Nejlevnější', 'price_desc' => 'Nejdražší', 'area_desc' => 'Největší plocha'] as $k => $v)<option value="{{ $k }}" @selected(($f['sort'] ?? 'newest') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <button class="btn-primary w-full">Použít filtry</button>
            @auth
                <button class="btn-ghost w-full" formmethod="POST" formaction="{{ route('account.searches.store') }}" name="_token" value="{{ csrf_token() }}">🔔 Uložit jako hlídacího psa</button>
            @else
                <p class="text-xs text-gray-500"><a class="text-pink underline" href="{{ route('login') }}">Přihlaste se</a> a uložte si hlídacího psa.</p>
            @endauth
        </form>

        <div>
            <div x-show="view === 'list'">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($listings as $l) @include('partials.listing-card') @empty
                        <div class="card col-span-full p-8 text-center text-gray-600">Žádný inzerát neodpovídá filtrům. Zkuste je upravit nebo si uložte hlídacího psa.</div>
                    @endforelse
                </div>
                {{ $listings->links() }}
            </div>
            <div x-cloak x-show="view === 'map'" class="card overflow-hidden">
                <div id="map" class="h-[70vh] w-full" data-src="{{ route('search.map', request()->query()) }}"></div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
window.initMap = function () {
    if (window._map) { window._map.resize(); return; }
    const s = document.createElement('script');
    s.src = 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js';
    s.crossOrigin = 'anonymous';
    s.onload = async () => {
        const el = document.getElementById('map');
        const map = window._map = new maplibregl.Map({
            container: el,
            style: { version: 8, sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: '© OpenStreetMap contributors' } }, layers: [{ id: 'osm', type: 'raster', source: 'osm' }] },
            center: [14.43, 50.08], zoom: 11,
        });
        map.addControl(new maplibregl.NavigationControl());
        const data = await (await fetch(el.dataset.src, { headers: { Accept: 'application/json' } })).json();
        const bounds = new maplibregl.LngLatBounds();
        data.features.forEach(f => {
            const a = document.createElement('a');
            a.href = f.properties.url; a.textContent = f.properties.title;
            const wrap = document.createElement('div');
            const b = document.createElement('strong'); b.textContent = f.properties.price;
            wrap.append(b, document.createElement('br'), a);
            new maplibregl.Marker({ color: '#F72585' }).setLngLat(f.geometry.coordinates).setPopup(new maplibregl.Popup({ offset: 24 }).setDOMContent(wrap)).addTo(map);
            bounds.extend(f.geometry.coordinates);
        });
        if (data.features.length) map.fitBounds(bounds, { padding: 40, maxZoom: 14 });
    };
    document.head.appendChild(s);
};
</script>
@endpush
