@extends('layouts.app', ['title' => 'Hledání nemovitostí', 'canonical' => route('search', array_intersect_key($filters, array_flip(['offer_type', 'property_type'])))])
@php($f = $filters)
@php($area = isset($f['bbox']) || isset($f['poly']))
@section('content')
<div x-data="{ more: false, view: 'list' }">
    <form method="GET" action="{{ route('search') }}" id="filters" class="glass sticky top-16 z-30 border-b">
        @foreach (['bbox', 'poly'] as $k)@isset($f[$k])<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endisset @endforeach
        <div class="container-page flex flex-wrap items-center gap-2 py-3">
            <div class="pill-nav" role="radiogroup" aria-label="Nabídka">
                @foreach (['' => 'Vše'] + config('reality.offer_types') as $k => $v)
                    <label class="pill cursor-pointer has-[:checked]:bg-card has-[:checked]:text-foreground has-[:checked]:shadow"><input type="radio" name="offer_type" value="{{ $k }}" class="sr-only" @checked(($f['offer_type'] ?? '') === $k) onchange="this.form.submit()">{{ $v }}</label>
                @endforeach
            </div>
            <div class="relative min-w-48 flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <label for="s-city" class="sr-only">Lokalita</label>
                <input id="s-city" name="city" value="{{ $f['city'] ?? '' }}" class="input rounded-full pl-9" placeholder="Město nebo čtvrť">
            </div>
            <label for="s-prop" class="sr-only">Typ</label>
            <select id="s-prop" name="property_type" class="input w-auto rounded-full"><option value="">Všechny typy</option>@foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}" @selected(($f['property_type'] ?? '') === $k)>{{ $v }}</option>@endforeach</select>
            <label for="s-pmax" class="sr-only">Cena do</label>
            <input id="s-pmax" name="price_max" type="number" min="0" value="{{ $f['price_max'] ?? '' }}" class="input w-32 rounded-full" placeholder="Cena do">
            <button type="button" class="btn-outline btn-sm" @click="more = !more" :aria-expanded="more">Další filtry @php($n = count(array_diff_key($f, array_flip(['offer_type', 'city', 'property_type', 'price_max', 'sort', 'bbox', 'poly']))))@if ($n)<span class="badge-neutral">{{ $n }}</span>@endif</button>
            <button class="btn-primary btn-sm">Hledat</button>
        </div>
        <div x-cloak x-show="more" x-transition class="container-page grid gap-5 border-t py-5 md:grid-cols-2 lg:grid-cols-4">
            <fieldset>
                <legend class="label">Dispozice</legend>
                <div class="flex flex-wrap gap-1">
                    @foreach (config('reality.dispositions') as $d)
                        <label class="chip-toggle"><input type="checkbox" name="disposition[]" value="{{ $d }}" class="sr-only" @checked(in_array($d, $f['disposition'] ?? []))>{{ $d }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid grid-cols-2 gap-2">
                <x-field name="price_min" label="Cena od (Kč)" type="number" min="0" :value="$f['price_min'] ?? ''" />
                <x-field name="floor_min" label="Patro od" type="number" min="-2" :value="$f['floor_min'] ?? ''" />
                <x-field name="area_min" label="Plocha od m²" type="number" min="0" :value="$f['area_min'] ?? ''" />
                <x-field name="area_max" label="Plocha do m²" type="number" min="0" :value="$f['area_max'] ?? ''" />
            </div>
            <div class="space-y-3">
                <fieldset>
                    <legend class="label">PENB</legend>
                    <div class="flex flex-wrap gap-1">
                        @foreach (config('reality.penb') as $p)
                            <label class="chip-toggle"><input type="checkbox" name="penb[]" value="{{ $p }}" class="sr-only" @checked(in_array($p, $f['penb'] ?? []))>{{ $p === 'none' ? 'nevydán' : $p }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label class="label" for="s-furn">Vybavení</label>
                    <select id="s-furn" name="furnishing" class="input"><option value="">Nerozhoduje</option>@foreach (config('reality.furnishing') as $k => $v)<option value="{{ $k }}" @selected(($f['furnishing'] ?? '') === $k)>{{ $v }}</option>@endforeach</select>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex flex-wrap gap-1">
                    @foreach (['has_balcony' => 'Balkon / terasa', 'has_elevator' => 'Výtah', 'has_parking' => 'Parkování', 'pets_allowed' => 'Mazlíčci'] as $k => $v)
                        <label class="chip-toggle"><input type="checkbox" name="{{ $k }}" value="1" class="sr-only" @checked(! empty($f[$k]))>{{ $v }}</label>
                    @endforeach
                </div>
                <x-field name="available_by" label="Volné nejpozději" type="date" :value="$f['available_by'] ?? ''" />
                <x-field name="q" label="Klíčové slovo" :value="$f['q'] ?? ''" placeholder="např. terasa, sklep" />
            </div>
        </div>
    </form>

    <div class="container-page grid gap-6 py-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
        <section :class="view === 'map' && 'hidden lg:block'">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <h1 class="font-display text-2xl font-bold md:text-3xl">{{ isset($f['offer_type']) ? config('reality.offer_types')[$f['offer_type']] : 'Všechny nabídky' }} <span class="text-base font-medium text-muted-foreground">{{ $listings->total() }} {{ $listings->total() === 1 ? 'nabídka' : ($listings->total() < 5 && $listings->total() > 1 ? 'nabídky' : 'nabídek') }}</span></h1>
                @if ($area)<a href="{{ route('search', \Illuminate\Support\Arr::except(request()->query(), ['bbox', 'poly', 'page'])) }}" class="badge-neutral py-1">V oblasti mapy <x-icon name="x" class="h-3 w-3" /></a>@endif
                <div class="ml-auto flex items-center gap-2">
                    <label for="s-sort" class="sr-only">Řazení</label>
                    <select id="s-sort" name="sort" form="filters" class="input w-auto rounded-full py-1.5 text-sm" onchange="this.form.submit()">
                        @foreach (['newest' => 'Nejnovější', 'price_asc' => 'Nejlevnější', 'price_desc' => 'Nejdražší', 'ppm2_asc' => 'Nejnižší cena/m²', 'area_desc' => 'Největší plocha'] as $k => $v)<option value="{{ $k }}" @selected(($f['sort'] ?? 'newest') === $k)>{{ $v }}</option>@endforeach
                    </select>
                    @auth
                        <button class="btn-outline btn-sm" form="filters" formmethod="POST" formaction="{{ route('account.searches.store') }}" name="_token" value="{{ csrf_token() }}"><x-icon name="bell" class="h-4 w-4" /> Hlídat</button>
                    @else
                        <a class="btn-outline btn-sm" href="{{ route('login') }}"><x-icon name="bell" class="h-4 w-4" /> Hlídat</a>
                    @endauth
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                @forelse ($listings as $l) @include('partials.listing-card') @empty
                    <div class="card col-span-full p-10 text-center">
                        <x-icon name="search" class="mx-auto h-10 w-10 text-muted-foreground" />
                        <p class="mt-3 font-display text-xl font-bold">Žádná nabídka neodpovídá filtrům</p>
                        <p class="mt-1 text-sm text-muted-foreground">Zkuste filtry uvolnit, nebo si nastavte hlídacího psa – pošleme vám nové nabídky e-mailem.</p>
                        <a href="{{ route('search') }}" class="btn-outline mt-5">Zrušit filtry</a>
                    </div>
                @endforelse
            </div>
            <div class="mt-6">{{ $listings->links() }}</div>
        </section>

        <aside class="lg:sticky lg:top-36 lg:block lg:h-[calc(100vh-10rem)]" :class="view === 'list' && 'hidden'" x-data="searchMap(@js(route('search.map', request()->query())))">
            <div class="card relative h-[calc(100vh-12rem)] overflow-hidden lg:h-full">
                <div x-ref="map" class="h-full w-full placeholder-map"></div>
                <div x-show="!ready && !error" class="absolute inset-0 grid place-items-center"><div class="skeleton h-10 w-40"></div></div>
                <p x-cloak x-show="error" class="absolute inset-0 grid place-items-center text-sm text-muted-foreground">Mapu se nepodařilo načíst.</p>
                <div class="absolute left-3 top-3 flex flex-wrap gap-2" x-show="ready">
                    <button type="button" class="btn-dark btn-sm shadow-pop" @click="searchArea()"><x-icon name="search" class="h-4 w-4" /> Hledat v této oblasti</button>
                    <button type="button" class="btn-outline btn-sm shadow-pop" @click="toggleDraw()" :class="drawing && '!border-accent !text-accent'"><x-icon name="draw" class="h-4 w-4" /> <span x-text="drawing ? 'Zrušit kreslení' : 'Nakreslit oblast'">Nakreslit oblast</span></button>
                    <button type="button" x-cloak x-show="drawing && poly.length > 2" class="btn-primary btn-sm shadow-pop" @click="applyPoly()">Hledat v oblasti</button>
                </div>
                <p x-cloak x-show="drawing" class="glass absolute bottom-3 left-3 rounded-full px-3 py-1 text-xs">Klikáním do mapy zadejte body oblasti (min. 3).</p>
            </div>
        </aside>
    </div>

    <div class="fixed bottom-20 left-1/2 z-30 -translate-x-1/2 lg:hidden">
        <button type="button" class="btn-dark shadow-pop" @click="view = view === 'list' ? 'map' : 'list'; $nextTick(() => window.dispatchEvent(new Event('resize')))">
            <x-icon name="map" class="h-4 w-4" /> <span x-text="view === 'list' ? 'Mapa' : 'Seznam'">Mapa</span>
        </button>
    </div>
</div>
@endsection
