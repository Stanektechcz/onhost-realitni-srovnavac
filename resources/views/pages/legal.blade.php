@extends('layouts.app', ['title' => $title, 'canonical' => route('legal', $slug)])
@section('content')
<div class="mx-auto max-w-3xl px-4 py-10">
    <p class="inline-block rounded-lg bg-pink px-3 py-1 text-sm font-bold text-white">NÁVRH – čeká na advokáta</p>
    <h1 class="mt-4 font-display text-4xl font-bold">{{ $title }}</h1>
    <div class="card mt-6 space-y-4 p-6 text-gray-700">
        <p>Tento dokument je pracovní placeholder a <strong>nemá právní účinky</strong>. Finální znění připraví advokát před spuštěním provozu.</p>
        @if ($slug === 'pravidla-moderace')
            <p>Osnova (DSA, nařízení (EU) 2022/2065): mechanismus oznamování nezákonného obsahu (čl. 16), odůvodnění rozhodnutí o omezení obsahu (čl. 17), vnitřní systém vyřizování stížností (čl. 20), kontaktní místo.</p>
        @elseif ($slug === 'cookies')
            <p>Osnova: portál používá pouze technicky nezbytné cookies (session, CSRF). Analytické či marketingové cookies nejsou nasazeny.</p>
        @elseif ($slug === 'ochrana-osobnich-udaju')
            <p>Osnova: správce, účely a právní základy zpracování, příjemci, doba uložení, práva subjektu údajů, kontakt na pověřence.</p>
        @else
            <p>Osnova: provozovatel, registrace, pravidla inzerce, placené služby (zatím neaktivní), odpovědnost, ukončení účtu, rozhodné právo.</p>
        @endif
    </div>
</div>
@endsection
