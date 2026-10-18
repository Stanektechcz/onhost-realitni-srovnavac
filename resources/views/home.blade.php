@extends('layouts.app', ['title' => 'Nemovitosti bez realitky', 'canonical' => route('home')])
@section('content')
<section class="bg-space relative overflow-hidden" x-data="{ offer: 'rent' }">
    <div class="container-page relative py-16 md:py-28">
        <h1 class="max-w-3xl text-4xl font-extrabold md:text-6xl">Bydlení přímo od majitelů. <span class="text-accent">Bez realitky.</span></h1>
        <p class="mt-4 max-w-xl text-lg opacity-85">{{ $count }} aktivních inzerátů přímo od majitelů. Žádná provize z ceny nemovitosti.</p>
        <form action="{{ route('search') }}" method="GET" role="search" class="mt-10 rounded-2xl bg-card p-3 text-foreground shadow-pop md:p-4">
            <input type="hidden" name="offer_type" :value="offer" value="rent">
            <div class="mb-3 inline-flex rounded-full bg-muted p-1" role="tablist" aria-label="Typ nabídky">
                @foreach (config('reality.offer_types') as $k => $v)
                    <button type="button" role="tab" @click="offer = '{{ $k }}'" :aria-selected="offer === '{{ $k }}'"
                        :class="offer === '{{ $k }}' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                        class="rounded-full px-5 py-2 text-sm font-semibold transition">{{ $v }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 md:grid-cols-[2fr_1fr_auto]">
                <div><label for="h-city" class="label">Lokalita</label><input id="h-city" name="city" class="input" placeholder="Město nebo čtvrť, např. Praha, Vinohrady"></div>
                <div><label for="h-prop" class="label">Typ nemovitosti</label>
                    <select id="h-prop" name="property_type" class="input">
                        <option value="">Vše</option>
                        @foreach (config('reality.property_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select></div>
                <button class="btn-primary self-end py-3 md:px-7">Hledat</button>
            </div>
        </form>
    </div>
</section>

<section class="container-page py-16 md:py-20">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <x-section-title eyebrow="Čerstvě přidané" title="Nejnovější inzeráty" />
        <a href="{{ route('search') }}" class="btn-outline mb-8">Zobrazit vše</a>
    </div>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($latest as $l) @include('partials.listing-card') @empty <p class="text-muted-foreground">Zatím žádné inzeráty.</p> @endforelse
    </div>
</section>

<section class="bg-secondary/60 py-16 md:py-20">
    <div class="container-page">
        <x-section-title eyebrow="Pro majitele" title="Jak to funguje">Inzerát připravíte za pár minut a se zájemci jednáte napřímo.</x-section-title>
        <ol class="grid gap-5 md:grid-cols-3">
            @foreach ([['Hlídací pes', 'Uložte si hledání a nové inzeráty vám pošleme e-mailem.'], ['Bezpečný kontakt', 'Komunikace přes portál s maskovanými údaji, ochrana proti spamu.'], ['Povinný PENB', 'U každého inzerátu vidíte energetickou třídu budovy.']] as $i => [$h, $p])
                <li class="card p-6">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-accent font-display font-bold text-accent-foreground">{{ $i + 1 }}</span>
                    <h3 class="mt-4 text-xl font-semibold">{{ $h }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $p }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="container-page mt-16">
    <div class="bg-space rounded-3xl px-6 py-14 text-center md:px-12">
        <h2 class="text-3xl font-bold md:text-4xl">Pronajímáte nebo prodáváte?</h2>
        <p class="mx-auto mt-3 max-w-lg opacity-85">Základní inzerát je zdarma. Bez realitky, bez provize.</p>
        <a href="{{ route('listings.create') }}" class="btn-primary mt-8 px-8 py-3.5 text-base">Přidat inzerát zdarma</a>
    </div>
</section>
@endsection
