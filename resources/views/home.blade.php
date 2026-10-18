@extends('layouts.app', ['title' => 'Nemovitosti bez realitky', 'canonical' => route('home')])
@section('content')
<section class="bg-gradient-to-b from-ink via-plum to-[#FBF8FE] px-4 pb-16 pt-10 text-white sm:pt-16">
    <div class="mx-auto max-w-4xl text-center">
        <h1 class="font-display text-4xl font-extrabold leading-tight sm:text-6xl">Bydlení <span class="text-pink">bez realitky</span>.</h1>
        <p class="mx-auto mt-4 max-w-2xl text-plum-100">{{ $count }} aktivních inzerátů přímo od majitelů. Žádná provize z ceny nemovitosti.</p>
        <form action="{{ route('search') }}" method="GET" class="mx-auto mt-8 grid max-w-3xl gap-2 rounded-2xl bg-white p-3 text-ink shadow-xl sm:grid-cols-[auto_auto_1fr_auto]">
            <label class="sr-only" for="h-offer">Typ nabídky</label>
            <select id="h-offer" name="offer_type" class="input sm:w-36">
                @foreach (config('reality.offer_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <label class="sr-only" for="h-prop">Typ nemovitosti</label>
            <select id="h-prop" name="property_type" class="input sm:w-36">
                <option value="">Vše</option>
                @foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <label class="sr-only" for="h-city">Lokalita</label>
            <input id="h-city" name="city" class="input" placeholder="Město nebo čtvrť, např. Praha, Vinohrady">
            <button class="btn-primary">Hledat</button>
        </form>
    </div>
</section>
<section class="mx-auto max-w-7xl px-4">
    <div class="flex items-end justify-between">
        <h2 class="font-display text-2xl font-bold">Nejnovější inzeráty</h2>
        <a href="{{ route('search') }}" class="text-sm font-semibold text-pink hover:underline">Zobrazit vše →</a>
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($latest as $l) @include('partials.listing-card') @empty <p class="text-gray-600">Zatím žádné inzeráty.</p> @endforelse
    </div>
</section>
<section class="mx-auto mt-16 grid max-w-7xl gap-4 px-4 sm:grid-cols-3">
    @foreach ([['Hlídací pes', 'Uložte si hledání a nové inzeráty vám pošleme e-mailem.'], ['Bezpečný kontakt', 'Komunikace přes portál s maskovanými údaji, ochrana proti spamu.'], ['Povinný PENB', 'U každého inzerátu vidíte energetickou třídu budovy.']] as [$h, $p])
        <div class="card p-6"><h3 class="font-display text-xl font-bold text-plum">{{ $h }}</h3><p class="mt-2 text-sm text-gray-600">{{ $p }}</p></div>
    @endforeach
</section>
@endsection
