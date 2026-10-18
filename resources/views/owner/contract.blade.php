@extends('layouts.app', ['title' => $tpl['title'], 'noindex' => true])
@php($l = $listing)
@php($blank = '………………………')
@php($v = fn ($x) => filled($x) ? $x : $blank)
@section('content')
<div class="container-page max-w-3xl py-8">
    <div class="no-print mb-4 flex gap-2"><a href="{{ route('owner.contracts') }}" class="btn-ghost btn-sm">← Šablony</a><button type="button" class="btn-primary btn-sm ml-auto" onclick="print()">Vytisknout / PDF</button></div>
    <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note"><strong>NÁVRH – čeká na advokáta.</strong> Nejde o právní službu. Text je pracovní a před použitím musí projít kontrolou advokátem.</div>
    <article class="card space-y-4 p-8 text-sm leading-relaxed" contenteditable="true" spellcheck="false" aria-label="Upravitelný text smlouvy">
        <h1 class="text-center text-2xl font-bold">{{ $tpl['title'] }}</h1>
        <p class="text-center text-xs text-muted-foreground">(NÁVRH – čeká na advokáta)</p>
        <p><strong>{{ $slug === 'rezervacni-smlouva' ? 'Prodávající' : 'Pronajímatel' }}:</strong> {{ $user->name }}, nar. {{ $blank }}, bytem {{ $blank }}</p>
        <p><strong>{{ $slug === 'rezervacni-smlouva' ? 'Zájemce' : 'Nájemce' }}:</strong> {{ $blank }}, nar. {{ $blank }}, bytem {{ $blank }}</p>
        <p><strong>Předmět:</strong> {{ $l ? $l->displayTitle() : 'byt' }}, {{ $l ? $v($l->locationLabel()) : $blank }}, plocha {{ $l?->area_m2 ? $l->area_m2.' m²' : $blank }}, dispozice {{ $v($l?->disposition) }}, PENB třídy {{ $l && $l->penb !== 'none' ? $l->penb : $blank }}.</p>
        @if ($slug === 'najemni-smlouva-byt')
            <h2 class="text-lg font-bold">I. Nájemné a úhrady</h2>
            <p>Nájemné činí {{ $l ? \App\Models\Listing::formatCzk($l->price_halere) : $blank }} měsíčně, zálohy na služby {{ $l ? \App\Models\Listing::formatCzk($l->fees_halere) : $blank }} měsíčně, splatné vždy do {{ $blank }}. dne v měsíci na účet {{ $blank }}. Zálohy budou vyúčtovány jednou ročně.</p>
            <h2 class="text-lg font-bold">II. Jistota</h2>
            <p>Nájemce složí jistotu ve výši {{ $l ? \App\Models\Listing::formatCzk($l->deposit_halere) : $blank }} (nejvýše trojnásobek měsíčního nájemného, § 2254 OZ).</p>
            <h2 class="text-lg font-bold">III. Doba nájmu</h2>
            <p>Nájem se sjednává na dobu {{ $blank }} od {{ $l?->available_from?->format('j. n. Y') ?? $blank }}.</p>
            <h2 class="text-lg font-bold">IV. Práva a povinnosti</h2>
            <p>Drobné opravy a běžnou údržbu hradí nájemce (nařízení vlády č. 308/2015 Sb.). Chov zvířat: {{ $l ? ($l->pets_allowed ? 'povolen' : 'po dohodě') : $blank }}. Byt se předává dle předávacího protokolu, který je přílohou smlouvy.</p>
            <h2 class="text-lg font-bold">V. Skončení nájmu</h2>
            <p>Výpověď se řídí § 2285 a násl. občanského zákoníku. Výpovědní doba činí tři měsíce.</p>
        @elseif ($slug === 'rezervacni-smlouva')
            <h2 class="text-lg font-bold">I. Rezervace</h2>
            <p>Prodávající se zavazuje do {{ $blank }} nenabízet nemovitost třetím osobám a uzavřít se zájemcem kupní smlouvu za kupní cenu {{ $l ? \App\Models\Listing::formatCzk($l->price_halere) : $blank }}.</p>
            <h2 class="text-lg font-bold">II. Rezervační záloha</h2>
            <p>Zájemce složí rezervační zálohu {{ $blank }} Kč do úschovy {{ $blank }}. Záloha se započítává na kupní cenu. Nedojde-li k uzavření kupní smlouvy z důvodů na straně prodávajícího nebo z důvodu nezískání úvěru, záloha se vrací v plné výši.</p>
        @else
            <h2 class="text-lg font-bold">I. Ukončení nájmu</h2>
            <p>Strany se dohodly, že nájem dle smlouvy ze dne {{ $blank }} skončí ke dni {{ $blank }}.</p>
            <h2 class="text-lg font-bold">II. Vypořádání</h2>
            <p>Byt bude předán dle předávacího protokolu. Jistota bude po odečtení případných pohledávek vrácena do {{ $blank }} dnů.</p>
        @endif
        <p class="pt-6">V {{ $l ? $v($l->city) : $blank }} dne {{ $blank }}</p>
        <div class="grid grid-cols-2 gap-10 pt-12"><div class="border-t pt-2 text-center text-muted-foreground">podpis</div><div class="border-t pt-2 text-center text-muted-foreground">podpis</div></div>
    </article>
</div>
@endsection
