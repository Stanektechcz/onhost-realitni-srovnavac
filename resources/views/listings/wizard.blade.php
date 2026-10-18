@extends('layouts.app', ['title' => 'Inzerát – '.\App\Support\ListingSteps::STEPS[$step], 'noindex' => true])
@php($l = $listing)
@section('content')
<div class="mx-auto max-w-3xl px-4 py-6">
    <h1 class="font-display text-3xl font-bold">{{ $l->exists ? 'Úprava inzerátu' : 'Nový inzerát' }}</h1>
    <ol class="mt-4 grid grid-cols-4 gap-1 text-center text-xs">
        @foreach (\App\Support\ListingSteps::STEPS as $n => $name)
            <li>
                @if ($l->exists)<a href="{{ route('listings.step', [$l, $n]) }}" @class(['block rounded-lg px-1 py-2', 'bg-accent text-accent-foreground' => $n === $step, 'bg-plum-50 text-plum' => $n !== $step])>@else<span @class(['block rounded-lg px-1 py-2', 'bg-accent text-accent-foreground' => $n === $step, 'bg-plum-50 text-gray-400' => $n !== $step])>@endif
                <span class="font-bold">{{ $n }}</span><span class="hidden sm:block">{{ $name }}</span>
                {!! $l->exists ? '</a>' : '</span>' !!}
            </li>
        @endforeach
    </ol>
    @error('submit')<div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>@enderror

    @if (in_array($step, \App\Support\ListingSteps::FORM_STEPS, true))
    <form method="POST" action="{{ $l->exists ? route('listings.step.save', [$l, $step]) : route('listings.store') }}" class="card mt-6 space-y-4 p-5" novalidate>
        @csrf
        @if ($l->exists) @method('PUT') @endif

        @if ($step === 1)
            <div class="grid gap-4 sm:grid-cols-2" x-data="listingStep1(@js([
                'offer_type' => old('offer_type', $l->offer_type), 'property_type' => old('property_type', $l->property_type),
                'disposition' => old('disposition', $l->disposition), 'area_m2' => old('area_m2', $l->area_m2),
                'city' => old('city', $l->city), 'district' => old('district', $l->district), 'street' => old('street', $l->street),
                'lat' => old('lat', $l->lat), 'lng' => old('lng', $l->lng),
                'suggestUrl' => route('assist.address'), 'resolveUrl' => route('assist.address.resolve'), 'priceUrl' => route('assist.price'),
            ]))">
                <div>
                    <label class="label" for="f-offer_type">Typ nabídky</label>
                    <select id="f-offer_type" name="offer_type" class="input" x-model="offer_type" @change="price()">@foreach (config('reality.offer_types') as $k => $v)<option value="{{ $k }}" @selected(old('offer_type', $l->offer_type) === $k)>{{ $v }}</option>@endforeach</select>
                    @error('offer_type')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="f-property_type">Typ nemovitosti</label>
                    <select id="f-property_type" name="property_type" class="input" x-model="property_type" @change="price()">@foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}" @selected(old('property_type', $l->property_type) === $k)>{{ $v }}</option>@endforeach</select>
                    @error('property_type')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="f-disposition">Dispozice</label>
                    <select id="f-disposition" name="disposition" class="input" x-model="disposition" @change="price()"><option value="">—</option>@foreach (config('reality.dispositions') as $d)<option @selected(old('disposition', $l->disposition) === $d)>{{ $d }}</option>@endforeach</select>
                    @error('disposition')<p class="err">{{ $message }}</p>@enderror
                </div>
                <x-field name="area_m2" label="Plocha (m²)" type="number" step="0.1" min="1" inputmode="decimal" :value="$l->area_m2" required x-model="area_m2" @change="price()" />

                <div class="relative sm:col-span-2" @click.outside="items = []">
                    <label class="label" for="f-address">Adresa – začněte psát ulici a číslo</label>
                    <input id="f-address" type="search" class="input" autocomplete="off" placeholder="např. Sokolovská 150, Praha" x-model="q" @input.debounce.350ms="search()" @keydown.escape="items = []" role="combobox" aria-autocomplete="list" :aria-expanded="items.length > 0" aria-controls="address-list">
                    <ul id="address-list" x-cloak x-show="items.length" role="listbox" class="absolute z-10 mt-1 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white text-sm shadow" style="max-height:16rem">
                        <template x-for="it in items" :key="it.key"><li><button type="button" role="option" class="block w-full px-3 py-3 text-left" @click="pick(it)" x-text="it.label"></button></li></template>
                    </ul>
                    <p class="mt-1 text-xs text-gray-500">Adresu dohledáme v registru RÚIAN a doplníme město, čtvrť i polohu na mapě. <a class="underline" href="{{ \App\Services\Geo\RuianGeocoder::TERMS_URL }}" target="_blank" rel="noopener">{{ \App\Services\Geo\RuianGeocoder::ATTRIBUTION }}</a></p>
                    <p x-cloak x-show="msg" class="mt-1 text-xs text-amber-900" x-text="msg"></p>
                </div>
                <x-field name="city" label="Město" :value="$l->city" required x-model="city" @change="price()" />
                <x-field name="district" label="Čtvrť / část obce" :value="$l->district" x-model="district" @change="price()" />
                <div class="sm:col-span-2"><x-field name="street" label="Ulice (zobrazí se veřejně)" :value="$l->street" x-model="street" /></div>
                <details class="sm:col-span-2 text-sm" @if($errors->has('lat') || $errors->has('lng')) open @endif>
                    <summary class="cursor-pointer text-gray-600">Poloha na mapě <span x-show="lat" x-text="'(' + lat + ', ' + lng + ')'"></span></summary>
                    <div class="mt-2 grid gap-4 sm:grid-cols-2">
                        <x-field name="lat" label="Zeměpisná šířka" type="number" step="0.0000001" :value="$l->lat" x-model="lat" />
                        <x-field name="lng" label="Zeměpisná délka" type="number" step="0.0000001" :value="$l->lng" x-model="lng" />
                    </div>
                </details>

                <div class="sm:col-span-2">
                    <x-field name="price_czk" label="Cena (Kč, u nájmu za měsíc)" type="number" min="1" inputmode="numeric" :value="old('price_czk', $l->priceCzk())" required x-ref="price" />
                    <div x-cloak x-show="est" class="mt-2 rounded-xl bg-plum-50 px-3 py-2 text-sm" aria-live="polite">
                        <template x-if="est && est.estimate.enough">
                            <div>
                                <p><strong>Orientační odhad:</strong> <span x-text="fmt(est.estimate.low)"></span> – <span x-text="fmt(est.estimate.high)"></span> Kč (medián <button type="button" class="font-semibold underline" @click="$refs.price.value = est.estimate.mid" x-text="fmt(est.estimate.mid) + ' Kč'"></button>)</p>
                                <p class="mt-1 text-xs text-gray-600" x-text="est.source"></p>
                            </div>
                        </template>
                        <p x-show="est && !est.estimate.enough" class="text-gray-600">Pro tuto lokalitu zatím nemáme dost srovnatelných nabídek pro odhad.</p>
                        <p x-show="est && est.benchmark" class="mt-1 text-xs text-gray-600"><span x-text="est?.benchmark?.text"></span> – zdroj: <a class="underline" :href="est?.benchmark?.url" target="_blank" rel="noopener" x-text="est?.benchmark?.source"></a></p>
                    </div>
                </div>
                <x-field name="fees_czk" label="Poplatky / služby (Kč)" type="number" min="0" inputmode="numeric" :value="$l->fees_halere ? intdiv($l->fees_halere, 100) : null" />
                <x-field name="deposit_czk" label="Kauce (Kč)" type="number" min="0" inputmode="numeric" :value="$l->deposit_halere ? intdiv($l->deposit_halere, 100) : null" />
            </div>
            <script>
                function listingStep1(init) {
                    return Object.assign({ q: '', items: [], msg: '', est: null }, init, {
                        init() { this.price(); },
                        async search() {
                            this.msg = '';
                            if (this.q.trim().length < 3) { this.items = []; return; }
                            try {
                                const r = await fetch(this.suggestUrl + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                                this.items = r.ok ? (await r.json()).items : [];
                                if (!r.ok) this.msg = 'Našeptávač adres je teď nedostupný, vyplňte prosím adresu ručně.';
                            } catch (e) { this.items = []; }
                        },
                        async pick(it) {
                            this.items = []; this.q = it.label;
                            try {
                                const r = await fetch(this.resolveUrl + '?label=' + encodeURIComponent(it.label) + '&key=' + encodeURIComponent(it.key), { headers: { Accept: 'application/json' } });
                                if (!r.ok) { this.msg = 'Adresu se nepodařilo dohledat, doplňte ji prosím ručně.'; return; }
                                const a = await r.json();
                                this.city = a.city || this.city; this.district = a.district || ''; this.street = a.street || '';
                                this.lat = a.lat; this.lng = a.lng; this.price();
                            } catch (e) { this.msg = 'Adresu se nepodařilo dohledat, doplňte ji prosím ručně.'; }
                        },
                        async price() {
                            if (!this.area_m2 || !(this.city || this.district)) { this.est = null; return; }
                            const p = new URLSearchParams({ offer_type: this.offer_type, property_type: this.property_type || '', area_m2: this.area_m2, city: this.city || '', district: this.district || '', disposition: this.disposition || '' });
                            try {
                                const r = await fetch(this.priceUrl + '?' + p, { headers: { Accept: 'application/json' } });
                                this.est = r.ok ? await r.json() : null;
                            } catch (e) { this.est = null; }
                        },
                        fmt(n) { return Number(n).toLocaleString('cs-CZ'); },
                    });
                }
            </script>
        @else
            <div class="grid gap-4 sm:grid-cols-2" x-data="{ penb: @js(old('penb', $l->penb)), busy: false, async draft() {
                const form = $el.closest('form'); const fd = new FormData(form); fd.delete('_method'); fd.set('locality', this.$refs.locality.value);
                if (this.$refs.desc.value.trim().length > 0 && !confirm('Přepsat stávající popis návrhem?')) return;
                this.busy = true;
                try { const r = await fetch(@js(route('assist.describe', $l)), { method: 'POST', body: fd, headers: { Accept: 'application/json' } }); if (r.ok) this.$refs.desc.value = (await r.json()).text; } finally { this.busy = false; }
            } }">
                <div class="sm:col-span-2">
                    <label class="label" for="f-penb">Energetická třída PENB <span class="text-pink">*</span></label>
                    <select id="f-penb" name="penb" class="input" x-model="penb" required>
                        <option value="">Vyberte…</option>
                        @foreach (config('reality.penb') as $p)<option value="{{ $p }}">{{ $p === 'none' ? 'Průkaz nevydán' : 'Třída '.$p }}</option>@endforeach
                    </select>
                    @error('penb')<p class="err">{{ $message }}</p>@enderror
                    <p x-cloak x-show="penb === 'none'" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Upozornění: majitel je ze zákona povinen PENB při prodeji/pronájmu doložit. U inzerátu se zobrazí varování „PENB nevydán“.</p>
                </div>
                <div>
                    <label class="label" for="f-furnishing">Vybavenost</label>
                    <select id="f-furnishing" name="furnishing" class="input"><option value="">—</option>@foreach (config('reality.furnishing') as $k => $v)<option value="{{ $k }}" @selected(old('furnishing', $l->furnishing) === $k)>{{ $v }}</option>@endforeach</select>
                </div>
                <x-field name="floor" label="Patro" type="number" min="-3" max="100" :value="$l->floor" />
                <x-field name="available_from" label="Dostupné od" type="date" :value="$l->available_from?->toDateString()" />
                <div class="grid grid-cols-2 gap-2 self-end text-sm">
                    @foreach (['has_elevator' => 'Výtah', 'has_balcony' => 'Balkon / terasa', 'has_parking' => 'Parkování', 'pets_allowed' => 'Mazlíčci povoleni'] as $k => $v)
                        <label class="flex items-center gap-2"><input type="hidden" name="{{ $k }}" value="0"><input type="checkbox" name="{{ $k }}" value="1" @checked(old($k, $l->$k))> {{ $v }}</label>
                    @endforeach
                </div>
                <div class="sm:col-span-2"><x-field name="title" label="Vlastní titulek (nepovinné)" :value="$l->title" maxlength="150" /></div>
                <div class="sm:col-span-2">
                    <label class="label" for="f-locality">Okolí a doprava (nepovinné, použije se do návrhu textu)</label>
                    <input id="f-locality" type="text" class="input" x-ref="locality" maxlength="500" placeholder="např. 3 minuty pěšky na tramvaj, obchody a park za rohem">
                </div>
                <div class="sm:col-span-2">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <label class="label" for="f-description">Popis <span class="text-pink">*</span></label>
                        <button type="button" class="btn-outline btn-sm" @click="draft()" :disabled="busy">Navrhnout text</button>
                    </div>
                    <p class="mb-1 text-xs text-gray-500">Návrh sestavíme z vyplněných údajů, poté ho můžete libovolně upravit.</p>
                    <textarea id="f-description" name="description" rows="8" class="input" x-ref="desc" minlength="30" maxlength="5000">{{ old('description', $l->description) }}</textarea>
                    @error('description')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>
        @endif
        <div class="flex justify-between gap-2">
            @if ($step > 1)<a class="btn-ghost" href="{{ route('listings.step', [$l, $step - 1]) }}">← Zpět</a>@else<span></span>@endif
            <button class="btn-primary">Uložit a pokračovat →</button>
        </div>
    </form>
    @elseif ($step === \App\Support\ListingSteps::PHOTOS)
        <div class="card mt-6 space-y-4 p-5">
            <form method="POST" action="{{ route('listings.photos', $l) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label class="label" for="f-photos">Fotografie (JPG/PNG/WebP, max {{ config('reality.max_photos') }} ks, min. 400×300 px)</label>
                <input id="f-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="input">
                @error('photos')<p class="err">{{ $message }}</p>@enderror
                @error('photos.*')<p class="err">{{ $message }}</p>@enderror
                <button class="btn-dark">Nahrát</button>
                <p class="text-xs text-gray-500">Fotky automaticky zmenšíme, převedeme do WebP{{ function_exists('imageavif') ? '/AVIF' : '' }} a odstraníme metadata (EXIF, GPS).</p>
            </form>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($l->photos as $p)
                    <div class="relative">
                        <img src="{{ $p->thumbUrl() }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-lg object-cover">
                        <form method="POST" action="{{ route('listings.photos.destroy', [$l, $p]) }}" class="absolute right-1 top-1">@csrf @method('DELETE')<button class="rounded-full bg-white/90 px-2 text-sm text-red-600" aria-label="Smazat fotku">✕</button></form>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between"><a class="btn-ghost" href="{{ route('listings.step', [$l, 2]) }}">← Zpět</a><a class="btn-primary" href="{{ route('listings.step', [$l, 4]) }}">Pokračovat →</a></div>
        </div>
    @else
        <div class="card mt-6 space-y-3 p-5">
            <h2 class="font-display text-xl font-bold">{{ $l->displayTitle() }}</h2>
            <p class="text-sm text-gray-600">{{ $l->locationLabel() ?: 'Lokalita nevyplněna' }} · {{ \App\Models\Listing::formatCzk($l->price_halere) }} · PENB {{ $l->penb === 'none' ? 'nevydán' : ($l->penb ?? '—') }} · {{ $l->photos->count() }} fotek</p>
            @if ($l->penb === 'none')<p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Inzerát bude označen „PENB nevydán“.</p>@endif
            @if (! empty($missing))
                <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Chybí povinné údaje v krocích: {{ implode(', ', $missing) }}.</p>
            @endif
            @if ($l->moderation_note)<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Poslední zamítnutí: {{ $l->moderation_note }}</p>@endif
            <p class="text-sm">Stav: <span class="chip">{{ $l->status }}</span></p>
            <div class="flex flex-wrap justify-between gap-2">
                <a class="btn-ghost" href="{{ route('listings.show', $l) }}">Náhled</a>
                <form method="POST" action="{{ route('listings.submit', $l) }}">@csrf<button class="btn-primary" @disabled(! empty($missing))>Odeslat ke schválení</button></form>
            </div>
        </div>
    @endif
</div>
@endsection
