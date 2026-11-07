@extends('layouts.app', ['title' => 'Dokumentace importu pro realitní kanceláře', 'canonical' => route('pages.agencies.docs'), 'description' => 'Technická dokumentace JSON feed API a XML-RPC importu (RPC2) pro realitní kanceláře.'])
@section('content')
<div class="container-page max-w-4xl py-12">
    <nav aria-label="Drobečková navigace" class="mb-6 text-sm text-muted-foreground"><a href="{{ route('home') }}" class="underline">Úvod</a> / <a href="{{ route('pages.agencies') }}" class="underline">Pro realitní kanceláře</a> / <span aria-current="page">Dokumentace</span></nav>
    <h1 class="text-4xl font-bold">Dokumentace importu</h1>
    <p class="mt-3 text-muted-foreground">Obsah nabídek patří kanceláři, která je posílá se souhlasem klientů. Nabídky se zobrazují pod jejím účtem.</p>

    <div class="mt-8 space-y-8 text-sm leading-relaxed [&_code]:rounded [&_code]:bg-secondary [&_code]:px-1">
        <section class="card p-6">
            <h2 class="text-2xl font-bold">Přístup</h2>
            <ol class="mt-3 list-decimal space-y-1 pl-5">
                <li>Založte si účet s rolí <em>Realitní kancelář</em>.</li>
                <li><a class="underline" href="{{ route('pages.contact', ['tema' => 'rk']) }}">Požádejte o přístup</a> – token pro feed API nebo údaje pro RPC2 vám předáme jednorázově.</li>
                <li>Každý požadavek feed API nese hlavičku <code>Authorization: Bearer &lt;token&gt;</code>. Limit 120 požadavků za minutu.</li>
            </ol>
        </section>

        <section class="card overflow-x-auto p-6">
            <h2 class="text-2xl font-bold">JSON feed API v1</h2>
            <table class="mt-3 w-full text-left">
                <thead><tr class="border-b"><th scope="col" class="py-2 pr-3">Metoda</th><th scope="col" class="py-2 pr-3">Cesta</th><th scope="col" class="py-2">Co dělá</th></tr></thead>
                <tbody>
                    <tr class="border-b"><td class="py-2 pr-3">POST</td><td class="py-2 pr-3"><code>/api/feed/v1/listings</code></td><td class="py-2">Vloží nebo aktualizuje 1–200 nabídek podle <code>external_id</code></td></tr>
                    <tr class="border-b"><td class="py-2 pr-3">GET</td><td class="py-2 pr-3"><code>/api/feed/v1/listings</code></td><td class="py-2">Stav nabídek kanceláře (pending, active, rejected s důvodem, expired)</td></tr>
                    <tr><td class="py-2 pr-3">DELETE</td><td class="py-2 pr-3"><code>/api/feed/v1/listings/{external_id}</code></td><td class="py-2">Stáhne nabídku z webu</td></tr>
                </tbody>
            </table>
            <h3 class="mt-6 text-lg font-bold">Položka nabídky</h3>
            <p class="mt-2"><strong>Povinné:</strong> <code>external_id</code>, <code>description</code> (min. 30 znaků), <code>offer_type</code> (<code>rent</code>|<code>sale</code>), <code>property_type</code> (<code>flat</code>|<code>house</code>|<code>land</code>|<code>commercial</code>|<code>room</code>), <code>price_czk</code>, <code>city</code>, <code>penb</code> (<code>A</code>–<code>G</code> nebo <code>none</code>).</p>
            <p class="mt-2"><strong>Volitelné:</strong> <code>title</code>, <code>disposition</code>, <code>area_m2</code>, <code>fees_czk</code>, <code>deposit_czk</code>, <code>district</code>, <code>street</code>, <code>lat</code>, <code>lng</code>, <code>furnishing</code>, <code>floor</code>, <code>has_elevator</code>, <code>has_balcony</code>, <code>has_parking</code>, <code>pets_allowed</code>, <code>available_from</code> (YYYY-MM-DD), <code>photos</code> (až {{ config('reality.max_photos', 20) }} adres <code>https://</code> v pořadí zobrazení).</p>
            <pre class="mt-4 overflow-x-auto rounded-xl bg-secondary p-4 text-xs"><code>{"listings": [{"external_id": "RK-1042", "offer_type": "rent", "property_type": "flat",
  "disposition": "2+kk", "area_m2": 54, "price_czk": 24000, "city": "Praha", "penb": "C",
  "description": "Světlý byt po rekonstrukci, 3 minuty od metra."}]}</code></pre>
            <p class="mt-3">Fotky se stahují na pozadí; přijímáme JPEG/PNG/WebP z veřejných HTTPS adres bez přesměrování.</p>
        </section>

        <section class="card overflow-x-auto p-6">
            <h2 class="text-2xl font-bold">XML-RPC import (RPC2)</h2>
            <p class="mt-2">Adresa <code>{{ route('import.rpc2') }}</code>, protokol XML-RPC, UTF-8, <code>POST</code>, max. 2 MB na požadavek. Pro výrobce CRM, kteří už mají XML-RPC konektor pro realitní inzerci: stačí změnit adresu a přihlašovací údaje.</p>
            <ol class="mt-3 list-decimal space-y-1 pl-5">
                <li><code>getHash(client_id)</code> vrátí <code>sessionId</code>.</li>
                <li>Před každým voláním klient spočítá nové <code>session_id</code> z předchozího, hesla a <code>software_key</code>.</li>
                <li><code>login</code>, volání (<code>addAdvert</code>, <code>delAdvert</code>, <code>listAdvert</code>), na konci <code>logout</code>. Relace vyprší po 15 minutách nečinnosti.</li>
            </ol>
            <p class="mt-3">Nepodporované metody (videa, statistiky, poptávky aj.) vracejí stav 501. Přesný popis mapování položek vám pošleme spolu s přístupem.</p>
        </section>
    </div>
</div>
@endsection
