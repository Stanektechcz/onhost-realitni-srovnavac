@extends('layouts.app', ['title' => 'Inzerát – '.\App\Support\ListingSteps::STEPS[$step], 'noindex' => true])
@php($l = $listing)
@section('content')
<div class="mx-auto max-w-3xl px-4 py-6">
    <h1 class="font-display text-3xl font-bold">{{ $l->exists ? 'Úprava inzerátu' : 'Nový inzerát' }}</h1>
    <ol class="mt-4 grid grid-cols-5 gap-1 text-center text-xs">
        @foreach (\App\Support\ListingSteps::STEPS as $n => $name)
            <li>
                @if ($l->exists)<a href="{{ route('listings.step', [$l, $n]) }}" @class(['block rounded-lg px-1 py-2', 'bg-pink text-white' => $n === $step, 'bg-plum-50 text-plum' => $n !== $step])>@else<span @class(['block rounded-lg px-1 py-2', 'bg-pink text-white' => $n === $step, 'bg-plum-50 text-gray-400' => $n !== $step])>@endif
                <span class="font-bold">{{ $n }}</span><span class="hidden sm:block">{{ $name }}</span>
                {!! $l->exists ? '</a>' : '</span>' !!}
            </li>
        @endforeach
    </ol>
    @error('submit')<div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>@enderror

    @if ($step <= 3)
    <form method="POST" action="{{ $l->exists ? route('listings.step.save', [$l, $step]) : route('listings.store') }}" class="card mt-6 space-y-4 p-5" novalidate>
        @csrf
        @if ($l->exists) @method('PUT') @endif

        @if ($step === 1)
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="f-offer_type">Typ nabídky</label>
                    <select id="f-offer_type" name="offer_type" class="input">@foreach (config('reality.offer_types') as $k => $v)<option value="{{ $k }}" @selected(old('offer_type', $l->offer_type) === $k)>{{ $v }}</option>@endforeach</select>
                    @error('offer_type')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="f-property_type">Typ nemovitosti</label>
                    <select id="f-property_type" name="property_type" class="input">@foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}" @selected(old('property_type', $l->property_type) === $k)>{{ $v }}</option>@endforeach</select>
                    @error('property_type')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="f-disposition">Dispozice</label>
                    <select id="f-disposition" name="disposition" class="input"><option value="">—</option>@foreach (config('reality.dispositions') as $d)<option @selected(old('disposition', $l->disposition) === $d)>{{ $d }}</option>@endforeach</select>
                    @error('disposition')<p class="err">{{ $message }}</p>@enderror
                </div>
                <x-field name="area_m2" label="Plocha (m²)" type="number" step="0.1" min="1" :value="$l->area_m2" required />
                <x-field name="price_czk" label="Cena (Kč, u nájmu za měsíc)" type="number" min="1" :value="$l->priceCzk()" required />
                <x-field name="fees_czk" label="Poplatky / služby (Kč)" type="number" min="0" :value="$l->fees_halere ? intdiv($l->fees_halere, 100) : null" />
                <x-field name="deposit_czk" label="Kauce (Kč)" type="number" min="0" :value="$l->deposit_halere ? intdiv($l->deposit_halere, 100) : null" />
            </div>
        @elseif ($step === 2)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="city" label="Město" :value="$l->city" required />
                <x-field name="district" label="Čtvrť / část obce" :value="$l->district" />
                <div class="sm:col-span-2"><x-field name="street" label="Ulice (zobrazí se veřejně)" :value="$l->street" /></div>
                <x-field name="lat" label="Zeměpisná šířka" type="number" step="0.0000001" :value="$l->lat" hint="Volitelné, např. 50.0755" />
                <x-field name="lng" label="Zeměpisná délka" type="number" step="0.0000001" :value="$l->lng" hint="Volitelné, např. 14.4378" />
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2" x-data="{ penb: @js(old('penb', $l->penb)) }">
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
                    <label class="label" for="f-description">Popis <span class="text-pink">*</span></label>
                    <textarea id="f-description" name="description" rows="7" class="input" minlength="30" maxlength="5000">{{ old('description', $l->description) }}</textarea>
                    @error('description')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>
        @endif
        <div class="flex justify-between">
            @if ($step > 1)<a class="btn-ghost" href="{{ route('listings.step', [$l, $step - 1]) }}">← Zpět</a>@else<span></span>@endif
            <button class="btn-primary">Uložit a pokračovat →</button>
        </div>
    </form>
    @elseif ($step === 4)
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
            <div class="flex justify-between"><a class="btn-ghost" href="{{ route('listings.step', [$l, 3]) }}">← Zpět</a><a class="btn-primary" href="{{ route('listings.step', [$l, 5]) }}">Pokračovat →</a></div>
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
