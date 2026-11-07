@extends('layouts.app', ['title' => 'Pro majitele', 'canonical' => route('pages.owners'), 'description' => 'Pronajměte nebo prodejte nemovitost bez provize z ceny. Odhad ceny z dat, chráněné dotazy, online prohlídky a šablony dokumentů.'])
@section('content')
@include('pages._header', ['eyebrow' => 'Pro majitele', 'h1' => 'Pronajímejte a prodávejte sami', 'lead' => 'Přidejte inzerát zdarma a mějte přehled o zájemcích. Žádná provize z ceny nemovitosti ani z nájemného.'])

<section class="container-page py-16 md:py-20">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['tag', 'Cena podle dat', 'Odhad ceny z porovnatelných nabídek zveřejněných na tomto webu – jako orientační vodítko.', route('owner.estimate')],
            ['mail', 'Chráněné dotazy', 'Zájemci vám píšou přes formulář u inzerátu, váš e-mail se jim nezobrazí.', null],
            ['calendar', 'Prohlídky online', 'Vypište termíny a zájemci si je sami rezervují.', null],
            ['calc', 'Výnos a daně', 'Kalkulačka hrubého a čistého výnosu z pronájmu.', route('tools.calculators', ['tab' => 'vynos'])],
            ['doc', 'Šablony a protokoly', 'Šablony smluv a předávací protokol k tisku.', route('guides.show', 'predani-bytu')],
            ['star', 'Skóre kvality inzerátu', 'Doporučení, co v inzerátu doplnit, aby byl úplný.', null],
        ] as [$i, $t, $d, $href])
            <div class="card p-6">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-secondary text-primary"><x-icon :name="$i" class="h-5 w-5" /></span>
                <h2 class="mt-4 text-lg font-bold">{{ $t }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ $d }}</p>
                @if ($href)<a href="{{ $href }}" class="mt-3 inline-block text-sm font-semibold underline">Otevřít</a>@endif
            </div>
        @endforeach
    </div>
</section>

<section class="bg-secondary/50 py-16 md:py-20">
    <div class="container-page max-w-3xl">
        <h2 class="text-3xl font-bold">Jak přidat inzerát</h2>
        <ol class="mt-6 list-decimal space-y-3 pl-5">
            <li>Zaregistrujte se e-mailem (nebo volitelně přes {{ config('oidc.brand_name') }}).</li>
            <li>Vyplňte adresu, parametry, cenu a energetickou třídu (PENB).</li>
            <li>Nahrajte fotky a odešlete inzerát ke zveřejnění.</li>
        </ol>
        <p class="mt-6 text-sm text-muted-foreground">Inzerát se zobrazuje po dobu {{ config('reality.listing_lifetime_days') }} dní a můžete ho jedním klikem obnovit.</p>
        <a href="{{ route('listings.create') }}" class="btn-primary mt-6">Přidat inzerát zdarma</a>
    </div>
</section>

@include('pages._cta')
@endsection
