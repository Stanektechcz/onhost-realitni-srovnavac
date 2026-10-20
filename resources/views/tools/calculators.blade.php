@extends('layouts.app', ['title' => 'Kalkulačky bydlení', 'description' => 'Hypoteční kalkulačka, náklady na bydlení, výnos z pronájmu, energie podle PENB a náklady na dojezd. Orientační výpočty.', 'canonical' => route('tools.calculators')])
@php($tabs = ['hypoteka' => ['calc', 'Hypotéka'], 'naklady' => ['home', 'Náklady na bydlení'], 'vynos' => ['chart', 'Výnos z pronájmu'], 'najem-koupe' => ['home', 'Nájem vs. koupě'], 'cashflow' => ['chart', 'Cash-flow pronájmu'], 'koupe' => ['calc', 'Náklady koupě'], 'prodej' => ['chart', 'Prodej a daň'], 'kauce' => ['home', 'Kauce a výpověď'], 'energie' => ['leaf', 'Energie dle PENB'], 'dojezd' => ['map', 'Dojezd']])
@php($rules = config('reality_rules'))
@section('content')
<section class="bg-space"><div class="container-page py-12 md:py-16">
    <p class="eyebrow">Nástroje</p>
    <h1 class="text-4xl font-extrabold md:text-6xl">Kalkulačky bydlení</h1>
    <p class="mt-3 max-w-2xl opacity-85">Všechny výpočty jsou orientační a probíhají jen ve vašem prohlížeči. Nejde o finanční poradenství ani nabídku úvěru.</p>
</div></section>
<div class="container-page -mt-8" x-data="{ tab: @js($tab) }">
    <div class="card flex gap-1 overflow-x-auto p-1.5" role="tablist">
        @foreach ($tabs as $k => [$i, $t])
            <button type="button" role="tab" class="pill flex shrink-0 items-center gap-2" :class="tab === '{{ $k }}' && 'is-active !bg-primary !text-primary-foreground'" @click="tab = '{{ $k }}'; history.replaceState(null, '', '?tab={{ $k }}')" :aria-selected="tab === '{{ $k }}'"><x-icon :name="$i" class="h-4 w-4" /> {{ $t }}</button>
        @endforeach
    </div>

    <section x-show="tab === 'hypoteka'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="mortgageLimitsCalc({{ $price }}, @js($rules['cnb']))">
        <div class="card space-y-5 p-6">
            <h2 class="font-display text-2xl font-bold">Hypoteční kalkulačka</h2>
            <label class="block"><span class="label">Cena nemovitosti (Kč)</span><input type="number" min="0" step="10000" x-model.number="price" class="input"></label>
            <label class="block"><span class="label">Vlastní zdroje: <span x-text="ownPct"></span> % (<span x-text="fmt(price * ownPct / 100)"></span> Kč)</span><input type="range" min="0" max="90" step="5" x-model.number="ownPct" class="range"></label>
            <label class="block"><span class="label">Úroková sazba: <span x-text="rate"></span> % p.a.</span><input type="range" min="0.5" max="10" step="0.05" x-model.number="rate" class="range"></label>
            <label class="block"><span class="label">Doba splácení: <span x-text="years"></span> let</span><input type="range" min="5" max="40" step="1" x-model.number="years" class="range"></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="label">Čistý měsíční příjem žadatelů (Kč)</span><input type="number" min="0" step="1000" x-model.number="income" class="input"></label>
                <label class="block"><span class="label">Ostatní dluhy – zůstatek (Kč)</span><input type="number" min="0" step="10000" x-model.number="otherDebt" class="input"></label>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="under36" class="h-4 w-4"> Žadatel mladší 36 let, vlastní bydlení (LTV až {{ $rules['cnb']['ltv_under36'] }} %)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="investment" class="h-4 w-4"> Investiční nemovitost (k pronájmu, třetí a další byt): LTV {{ $rules['cnb']['ltv_investment'] }} %, DTI max. {{ $rules['cnb']['dti_investment'] }}</label>
            <p class="text-xs text-muted-foreground">Limity ČNB (stav k {{ \Illuminate\Support\Carbon::parse($rules['verified'])->format('j. n. Y') }}): LTV {{ $rules['cnb']['ltv'] }} % ({{ $rules['cnb']['ltv_under36'] }} % do 36 let) pro vlastní bydlení; ukazatele DTI a DSTI jsou pro vlastní bydlení deaktivované. Pro investiční hypotéky ČNB od 1. 4. 2026 doporučuje LTV {{ $rules['cnb']['ltv_investment'] }} % a DTI {{ $rules['cnb']['dti_investment'] }}. Zdroj: <a class="underline" href="{{ $rules['sources']['cnb'][1] }}" rel="noopener" target="_blank">ČNB</a>.</p>
        </div>
        <div class="bg-space space-y-4 rounded-[calc(var(--radius)+4px)] p-6">
            <p class="text-sm opacity-80">Měsíční splátka</p>
            <p class="kpi text-5xl"><span x-text="fmt(payment)"></span> <span class="text-base">Kč</span></p>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="opacity-75">Výše úvěru</dt><dd x-text="fmt(loan) + ' Kč'"></dd></div>
                <div class="flex justify-between"><dt class="opacity-75">LTV</dt><dd><span x-text="fmt(ltv)"></span> % <span x-show="ltv > ltvMax" class="badge bg-accent text-accent-foreground">nad limit <span x-text="ltvMax"></span> %</span></dd></div>
                <div class="flex justify-between"><dt class="opacity-75">Max. úvěr dle LTV</dt><dd x-text="fmt(maxLoan) + ' Kč'"></dd></div>
                <div class="flex justify-between"><dt class="opacity-75">DTI (dluh / roční příjem)</dt><dd><span x-text="dti === null ? '—' : dti.toFixed(1)"></span> <span x-show="!dtiOk" class="badge bg-accent text-accent-foreground">nad 7</span></dd></div>
                <div class="flex justify-between"><dt class="opacity-75">DSTI (splátka / příjem)</dt><dd x-text="dsti === null ? '—' : dsti.toFixed(1) + ' %'"></dd></div>
                <div class="flex justify-between"><dt class="opacity-75">Zaplaceno celkem</dt><dd x-text="fmt(total) + ' Kč'"></dd></div>
                <div class="flex justify-between"><dt class="opacity-75">Z toho úroky</dt><dd x-text="fmt(interest) + ' Kč'"></dd></div>
                
            </dl>
            <p class="text-xs opacity-75">Výpočet anuitní splátky při neměnné sazbě. Neobsahuje poplatky ani pojištění. Nejde o nabídku, doporučení ani zprostředkování spotřebitelského úvěru ve smyslu zákona č. 257/2016 Sb. Konkrétní podmínky vám sdělí banka nebo vázaný zástupce s oprávněním ČNB.</p>
        </div>
    </section>

    <section x-cloak x-show="tab === 'naklady'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="costCalc({{ $rent }}, {{ $area }}, @js($penb))">
        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-display text-2xl font-bold sm:col-span-2">Celkové měsíční náklady na bydlení</h2>
            @foreach (['rent' => 'Nájem / splátka', 'fees' => 'Zálohy na služby / fond oprav', 'energy' => 'Elektřina, plyn, teplo', 'internet' => 'Internet a TV', 'insurance' => 'Pojištění domácnosti', 'other' => 'Ostatní (parkování…)'] as $k => $v)
                <label class="block"><span class="label">{{ $v }}</span><input type="number" min="0" step="100" x-model.number="{{ $k }}" class="input"></label>
            @endforeach
        </div>
        <div class="bg-space rounded-[calc(var(--radius)+4px)] p-6">
            <p class="text-sm opacity-80">Celkem měsíčně</p>
            <p class="kpi text-5xl"><span x-text="fmt(total)"></span> <span class="text-base">Kč</span></p>
            <p class="mt-3 text-sm opacity-80">Ročně <span x-text="fmt(yearly)"></span> Kč</p>
            <p class="mt-4 text-xs opacity-75">Doporučuje se, aby náklady na bydlení nepřesáhly zhruba 30–40 % čistého příjmu domácnosti.</p>
        </div>
    </section>

    <section x-cloak x-show="tab === 'vynos'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="yieldCalc({{ $price }}, {{ $rent }})">
        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-display text-2xl font-bold sm:col-span-2">Výnos z pronájmu</h2>
            <label class="block"><span class="label">Kupní cena (Kč)</span><input type="number" min="0" step="10000" x-model.number="price" class="input"></label>
            <label class="block"><span class="label">Měsíční nájem (Kč)</span><input type="number" min="0" step="500" x-model.number="rent" class="input"></label>
            <label class="block"><span class="label">Roční náklady majitele (fond oprav, pojištění, údržba)</span><input type="number" min="0" step="1000" x-model.number="costs" class="input"></label>
            <label class="block"><span class="label">Neobsazenost: <span x-text="vacancy"></span> %</span><input type="range" min="0" max="30" x-model.number="vacancy" class="range"></label>
            <label class="block"><span class="label">Sazba daně z příjmů (%)</span><input type="number" min="0" max="23" x-model.number="taxPct" class="input"></label>
            <label class="block"><span class="label">Paušální výdaje (%)</span><input type="number" min="0" max="30" x-model.number="flat" class="input"></label>
        </div>
        <div class="bg-space space-y-3 rounded-[calc(var(--radius)+4px)] p-6">
            <div><p class="text-sm opacity-80">Hrubý výnos</p><p class="kpi text-4xl"><span x-text="gross.toFixed(2)"></span> %</p></div>
            <div><p class="text-sm opacity-80">Čistý výnos před daní</p><p class="kpi text-3xl"><span x-text="net.toFixed(2)"></span> %</p></div>
            <div><p class="text-sm opacity-80">Čistý výnos po dani</p><p class="kpi text-3xl"><span x-text="netAfterTax.toFixed(2)"></span> %</p></div>
            <p class="text-sm opacity-80">Odhad daně ročně: <span x-text="fmt(tax)"></span> Kč · návratnost <span x-text="isFinite(payback) ? payback.toFixed(1) + ' let' : '—'"></span></p>
            <p class="text-xs opacity-75">Daň: příjmy z nájmu (§ 9 ZDP) s paušálními výdaji 30 %. Jen orientačně – neposuzuje vaši celkovou daňovou situaci; poraďte se s daňovým poradcem.</p>
        </div>
    </section>

    <section x-cloak x-show="tab === 'energie'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="energyCalc({{ $area }}, @js($penb))">
        <div class="card space-y-4 p-6">
            <h2 class="font-display text-2xl font-bold">Energetický odhad podle PENB</h2>
            <label class="block"><span class="label">Plocha (m²)</span><input type="number" min="5" x-model.number="area" class="input"></label>
            <div><span class="label">Třída PENB</span><div class="flex flex-wrap gap-1"><template x-for="c in classes"><button type="button" class="penb" :class="'penb-' + c + (penb === c ? ' ring-2 ring-offset-2 ring-[var(--ring)]' : ' opacity-60')" @click="penb = c" x-text="c"></button></template></div></div>
            <label class="block"><span class="label">Cena energie (Kč/kWh vč. DPH)</span><input type="number" min="0" step="0.1" x-model.number="kwhPrice" class="input"></label>
            <div class="space-y-1.5 pt-2">
                <template x-for="c in classes"><div class="flex items-center gap-2 text-xs"><span class="w-4 font-bold" x-text="c"></span><div class="h-2 flex-1 rounded-full bg-muted"><div class="bar-fill h-2 rounded-full" :style="'width:' + (compare(c) / compare('G') * 100) + '%'"></div></div><span class="w-24 text-right" x-text="fmt(compare(c)) + ' Kč/měs.'"></span></div></template>
            </div>
        </div>
        <div class="bg-space rounded-[calc(var(--radius)+4px)] p-6">
            <p class="text-sm opacity-80">Odhad nákladů na energie</p>
            <p class="kpi text-5xl"><span x-text="fmt(monthly)"></span> <span class="text-base">Kč / měs.</span></p>
            <p class="mt-3 text-sm opacity-80"><span x-text="fmt(kwh)"></span> kWh ročně</p>
            <p class="mt-4 text-xs opacity-75">Typické hodnoty dodané energie pro třídu (A ≈ 40 … G ≈ 330 kWh/m²/rok). Skutečná spotřeba se může výrazně lišit.</p>
        </div>
    </section>

    <section x-cloak x-show="tab === 'dojezd'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="commuteCalc()">
        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-display text-2xl font-bold sm:col-span-2">Náklady a čas na dojíždění</h2>
            <div class="sm:col-span-2 pill-nav"><button type="button" class="pill" :class="mode === 'pt' && 'is-active'" @click="mode = 'pt'">MHD</button><button type="button" class="pill" :class="mode === 'car' && 'is-active'" @click="mode = 'car'">Auto</button></div>
            <label class="block"><span class="label">Pracovních dní v měsíci</span><input type="number" min="1" max="31" x-model.number="days" class="input"></label>
            <label class="block"><span class="label">Doba cesty jedním směrem (min)</span><input type="number" min="1" x-model.number="minutes" class="input"></label>
            <template x-if="mode === 'pt'"><label class="block"><span class="label">Měsíční jízdenka (Kč)</span><input type="number" min="0" x-model.number="pass" class="input"></label></template>
            <template x-if="mode === 'car'"><label class="block"><span class="label">Vzdálenost jedním směrem (km)</span><input type="number" min="0" x-model.number="km" class="input"></label></template>
            <template x-if="mode === 'car'"><label class="block"><span class="label">Spotřeba (l/100 km)</span><input type="number" min="0" step="0.1" x-model.number="fuel" class="input"></label></template>
            <template x-if="mode === 'car'"><label class="block"><span class="label">Cena paliva (Kč/l)</span><input type="number" min="0" step="0.1" x-model.number="fuelPrice" class="input"></label></template>
        </div>
        <div class="bg-space rounded-[calc(var(--radius)+4px)] p-6">
            <p class="text-sm opacity-80">Měsíčně za dopravu</p>
            <p class="kpi text-5xl"><span x-text="fmt(monthly)"></span> <span class="text-base">Kč</span></p>
            <p class="mt-3 text-sm opacity-80">Čas na cestách: <span x-text="hours.toFixed(1)"></span> h měsíčně</p>
            <p class="mt-4 text-xs opacity-75">Auto: jen palivo, bez parkování, opotřebení a pojištění.</p>
        </div>
    </section>

    @include('tools._new-calculators')
</div>
@endsection
