@extends('layouts.app', ['title' => 'Jak to funguje', 'canonical' => route('pages.how'), 'description' => 'Jak funguje realitní srovnávač ONhost pro majitele, hledající a realitní kanceláře – ve třech krocích.'])
@section('content')
@include('pages._header', ['eyebrow' => 'Jak to funguje', 'h1' => 'Jak to funguje', 'lead' => 'Inzerce, hledání a srovnání nabídek na jednom místě. Bez provize z ceny nemovitosti a bez povinné registrace pro hledající.'])

<section class="container-page py-16 md:py-20">
    @include('pages._steps')
</section>

<section class="bg-secondary/50 py-16 md:py-20">
    <div class="container-page grid gap-6 md:grid-cols-3">
        @foreach ([
            ['Odkud jsou nabídky', 'Všechny inzeráty zadávají přímo majitelé nebo realitní kanceláře na tomto webu (ručně nebo importem ze svého softwaru). Nabídky z jiných portálů nepřebíráme.'],
            ['Odkud jsou ceny', 'Mediány ceny za m² počítáme z inzerátů na webu, a to jen tam, kde je dostatek nabídek. Doplňujeme je otevřenými daty ČSÚ s uvedením zdroje.'],
            ['Co platíte', 'Hledání, srovnávač, kalkulačky i základní inzerát jsou zdarma. Provizi z ceny nemovitosti ani z nájemného neúčtujeme.'],
        ] as [$t, $d])
            <div class="card p-6"><h2 class="text-xl font-bold">{{ $t }}</h2><p class="mt-2 text-sm text-muted-foreground">{{ $d }}</p></div>
        @endforeach
    </div>
    <div class="container-page mt-8 text-sm text-muted-foreground">
        Účet {{ config('oidc.brand_name') }} není potřeba. Pokud ho máte, můžete se jím přihlásit – web ale funguje i bez něj. <a href="{{ route('pages.comparator') }}" class="underline">Jak počítá srovnávač</a> · <a href="{{ route('pages.faq') }}" class="underline">Časté dotazy</a>
    </div>
</section>

@include('pages._cta')
@endsection
