@extends('layouts.app', ['title' => 'Pro realitní kanceláře', 'canonical' => route('pages.agencies'), 'description' => 'Import nabídek realitních kanceláří přes JSON feed API nebo XML-RPC rozhraní RPC2. Profil v katalogu kanceláří a přehled stavu nabídek.'])
@section('content')
@include('pages._header', ['eyebrow' => 'Pro realitní kanceláře', 'h1' => 'Nabídky z vašeho softwaru, automaticky', 'lead' => 'Napojte svůj realitní software a posílejte nabídky přes feed API nebo XML-RPC rozhraní RPC2. Nabídky se zobrazují pod vaším účtem.'])

<section class="container-page py-16 md:py-20">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-8">
            <p class="eyebrow">Možnost 1</p>
            <h2 class="text-2xl font-bold">JSON feed API (v1)</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm">
                <li>REST rozhraní: vložení nebo aktualizace 1–200 nabídek podle vašeho <code>external_id</code>.</li>
                <li>Přehled stavu nabídek (čeká na kontrolu, zveřejněno, zamítnuto s důvodem, expirováno).</li>
                <li>Stažení nabídky z webu jedním požadavkem.</li>
                <li>Autorizace tokenem v hlavičce <code>Authorization: Bearer</code>; ukládáme jen jeho otisk SHA-256.</li>
            </ul>
        </div>
        <div class="card p-8">
            <p class="eyebrow">Možnost 2</p>
            <h2 class="text-2xl font-bold">XML-RPC import (RPC2)</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm">
                <li>Adresa <code>{{ route('import.rpc2') }}</code>, protokol XML-RPC, UTF-8.</li>
                <li>Rozhraní, které podporuje řada realitních CRM – stačí v něm doplnit adresu a přihlašovací údaje.</li>
                <li>Přístup přiděluje správce webu na vyžádání.</li>
            </ul>
        </div>
    </div>
    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('pages.agencies.docs') }}" class="btn-dark"><x-icon name="doc" class="h-4 w-4" /> Technická dokumentace</a>
        <a href="{{ route('pages.contact', ['tema' => 'rk']) }}" class="btn-outline">Požádat o přístup</a>
    </div>
</section>

<section class="bg-secondary/50 py-16 md:py-20">
    <div class="container-page grid gap-6 md:grid-cols-3">
        @foreach ([
            ['Obsah patří vám', 'Nabídky posíláte se souhlasem klientů a zodpovídáte za jejich obsah. Zobrazujeme je pod profilem vaší kanceláře.'],
            ['Profil v katalogu', 'Katalog kanceláří vychází z veřejného rejstříku ARES. Profil si můžete převzít a doplnit o makléře.'],
            ['Moderace', 'Nové nabídky mohou projít ruční kontrolou. U zamítnuté nabídky uvidíte důvod.'],
        ] as [$t, $d])
            <div class="card p-6"><h2 class="text-xl font-bold">{{ $t }}</h2><p class="mt-2 text-sm text-muted-foreground">{{ $d }}</p></div>
        @endforeach
    </div>
    <div class="container-page mt-8"><a href="{{ route('agencies.index') }}" class="btn-outline">Katalog realitních kanceláří</a></div>
</section>
@endsection
