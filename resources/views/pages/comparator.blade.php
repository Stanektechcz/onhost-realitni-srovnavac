@extends('layouts.app', ['title' => 'Jak funguje srovnávač', 'canonical' => route('pages.comparator'), 'description' => 'Srovnávač porovná nabídky vedle sebe a ukáže mediány cen za m² z inzerátů na webu a z otevřených dat ČSÚ – vždy se zdrojem.'])
@section('content')
@include('pages._header', ['eyebrow' => 'Srovnávač', 'h1' => 'Jak funguje srovnávač', 'lead' => 'Porovnáte nabídky vedle sebe a uvidíte, jak si stojí proti ceně v lokalitě. Každé číslo má uvedený zdroj.'])

<section class="container-page py-16 md:py-20">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-8">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-secondary text-primary"><x-icon name="compare" class="h-5 w-5" /></span>
            <h2 class="mt-4 text-2xl font-bold">Nabídky vedle sebe</h2>
            <p class="mt-2 text-sm text-muted-foreground">U libovolného inzerátu klikněte na „Porovnat“ a přidejte až 4 nabídky. Uvidíte je v tabulce: cenu, plochu, cenu za m², měsíční náklady, dispozici, odhad energií a další parametry.</p>
            <a href="{{ route('compare') }}" class="btn-dark mt-6">Otevřít porovnání</a>
        </div>
        <div class="card p-8">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-secondary text-primary"><x-icon name="chart" class="h-5 w-5" /></span>
            <h2 class="mt-4 text-2xl font-bold">Ceny za m² v lokalitách</h2>
            <p class="mt-2 text-sm text-muted-foreground">Pro každou lokalitu ukazujeme medián a rozpětí (25.–75. percentil) inzerované ceny za m², zvlášť pro nájem a prodej. Statistiku zobrazíme jen tehdy, když je v lokalitě alespoň {{ \App\Services\MarketStats::MIN_SAMPLE }} nabídek.</p>
            <a href="{{ route('insights.index') }}" class="btn-dark mt-6">Ceny v lokalitách</a>
        </div>
    </div>
</section>

<section class="bg-secondary/50 py-16 md:py-20">
    <div class="container-page max-w-4xl">
        <x-section-title eyebrow="Zdroje dat" title="Odkud čísla pocházejí" />
        <div class="overflow-x-auto">
            <table class="card w-full text-left text-sm">
                <caption class="sr-only">Zdroje dat srovnávače</caption>
                <thead><tr class="border-b"><th scope="col" class="p-4">Zdroj</th><th scope="col" class="p-4">Co z něj počítáme</th><th scope="col" class="p-4">Na co pozor</th></tr></thead>
                <tbody>
                    <tr class="border-b align-top">
                        <th scope="row" class="p-4 font-semibold">Inzeráty na {{ config('app.name') }}</th>
                        <td class="p-4">Mediány a rozpětí ceny za m², porovnání nabídky s lokalitou</td>
                        <td class="p-4 text-muted-foreground">Jde o inzerované (nabídkové) ceny, ne o ceny skutečně realizované.</td>
                    </tr>
                    <tr class="align-top">
                        <th scope="row" class="p-4 font-semibold"><a class="underline" href="https://data.csu.gov.cz/" rel="noopener">Český statistický úřad (ČSÚ)</a></th>
                        <td class="p-4">Průměrné kupní ceny a indexy cen nemovitostí (ČSÚ z dat ČÚZK) pro kraje a celé Česko</td>
                        <td class="p-4 text-muted-foreground">Otevřená data s uvedením zdroje a období; zveřejňují se se zpožděním a za větší území.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card mt-6 border-accent/40 p-6">
            <h2 class="text-lg font-bold">Co nepoužíváme</h2>
            <p class="mt-2 text-sm text-muted-foreground">Nepřebíráme, nestahujeme ani nezobrazujeme inzeráty či ceny z jiných realitních portálů. Srovnávač pracuje jen s nabídkami zadanými přímo na tomto webu a s veřejnými otevřenými daty.</p>
        </div>
        <p class="mt-6 text-xs text-muted-foreground">Všechny údaje jsou orientační a nenahrazují znalecký posudek.</p>
    </div>
</section>

@include('pages._cta')
@endsection
