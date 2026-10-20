{{-- Nástroje pro kupující, prodávající, nájemníky a pronajímatele. Pravidla: config/reality_rules.php. Logika: App\Support\Calculators + resources/js/calculators.js --}}
@php($tax = $rules['tax'])
@php($verified = \Illuminate\Support\Carbon::parse($rules['verified'])->format('j. n. Y'))

<section x-cloak x-show="tab === 'najem-koupe'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="rentBuyCalc({{ $price }}, {{ $rent }})">
    <div class="card grid gap-4 p-6 sm:grid-cols-2">
        <h2 class="font-display text-2xl font-bold sm:col-span-2">Nájem, nebo koupě?</h2>
        <label class="block"><span class="label">Cena bytu (Kč)</span><input type="number" min="0" step="10000" x-model.number="price" class="input"></label>
        <label class="block"><span class="label">Srovnatelný nájem (Kč/měs.)</span><input type="number" min="0" step="500" x-model.number="rent" class="input"></label>
        <label class="block"><span class="label">Vlastní zdroje (%)</span><input type="number" min="0" max="100" x-model.number="ownPct" class="input"></label>
        <label class="block"><span class="label">Úrok hypotéky (% p.a.)</span><input type="number" min="0" step="0.05" x-model.number="rate" class="input"></label>
        <label class="block"><span class="label">Splatnost hypotéky (let)</span><input type="number" min="1" max="40" x-model.number="loanYears" class="input"></label>
        <label class="block"><span class="label">Srovnávací horizont: <span x-text="horizon"></span> let</span><input type="range" min="1" max="40" x-model.number="horizon" class="range"></label>
        <label class="block"><span class="label">Růst cen nemovitostí (% ročně)</span><input type="number" step="0.5" x-model.number="priceGrowth" class="input"></label>
        <label class="block"><span class="label">Růst nájmů (% ročně)</span><input type="number" step="0.5" x-model.number="rentGrowth" class="input"></label>
        <label class="block"><span class="label">Náklady vlastníka (% hodnoty ročně – fond oprav, údržba, daň z nemovitosti)</span><input type="number" step="0.1" min="0" x-model.number="ownerCost" class="input"></label>
        <label class="block"><span class="label">Výnos alternativní investice (% ročně)</span><input type="number" step="0.5" x-model.number="invest" class="input"></label>
        <label class="block"><span class="label">Jednorázové náklady koupě (Kč)</span><input type="number" min="0" step="1000" x-model.number="buyCosts" class="input"></label>
    </div>
    <div class="bg-space space-y-3 rounded-[calc(var(--radius)+4px)] p-6">
        <p class="text-sm opacity-80">Po <span x-text="horizon"></span> letech vychází lépe</p>
        <p class="kpi text-4xl" x-text="r.diff >= 0 ? 'koupě' : 'nájem'"></p>
        <p class="text-sm">o <strong x-text="fmt(Math.abs(r.diff)) + ' Kč'"></strong></p>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="opacity-75">Čisté jmění kupujícího</dt><dd x-text="fmt(r.buy) + ' Kč'"></dd></div>
            <div class="flex justify-between"><dt class="opacity-75">Čisté jmění nájemníka</dt><dd x-text="fmt(r.rent) + ' Kč'"></dd></div>
            <div class="flex justify-between"><dt class="opacity-75">Splátka hypotéky</dt><dd x-text="fmt(r.payment) + ' Kč/měs.'"></dd></div>
        </dl>
        <p class="text-xs opacity-75">Nájemník investuje vlastní zdroje a náklady koupě a každý rok rozdíl mezi výdaji vlastníka (splátka + náklady) a nájmem. Kupující má hodnotu bytu minus zůstatek úvěru. Bez daní a poplatků z investic; jen modelový výpočet.</p>
    </div>
</section>

<section x-cloak x-show="tab === 'cashflow'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="cashflowCalc({{ $price }}, {{ $rent }}, @js($tax))">
    <div class="card grid gap-4 p-6 sm:grid-cols-2">
        <h2 class="font-display text-2xl font-bold sm:col-span-2">Výnos a cash-flow pronájmu vč. daně</h2>
        <label class="block"><span class="label">Kupní cena (Kč)</span><input type="number" min="0" step="10000" x-model.number="price" class="input"></label>
        <label class="block"><span class="label">Nájem bez služeb (Kč/měs.)</span><input type="number" min="0" step="500" x-model.number="rent" class="input"></label>
        <label class="block"><span class="label">Neobsazenost (%)</span><input type="number" min="0" max="100" x-model.number="vacancy" class="input"></label>
        <label class="block"><span class="label">Roční náklady (fond oprav, pojištění, údržba, daň z nemovitosti)</span><input type="number" min="0" step="1000" x-model.number="costs" class="input"></label>
        <label class="block"><span class="label">Vlastní zdroje (%)</span><input type="number" min="0" max="100" x-model.number="ownPct" class="input"></label>
        <label class="block"><span class="label">Úrok hypotéky (% p.a.)</span><input type="number" min="0" step="0.05" x-model.number="rate" class="input"></label>
        <label class="block"><span class="label">Podíl budovy na ceně (%, pro odpisy)</span><input type="number" min="0" max="100" x-model.number="buildingPct" class="input"></label>
        <label class="block"><span class="label">Sazba daně (%)</span><select x-model.number="taxPct" class="input"><option value="15">15 %</option><option value="23">23 %</option></select></label>
    </div>
    <div class="bg-space space-y-3 rounded-[calc(var(--radius)+4px)] p-6">
        <p class="text-sm opacity-80">Hrubý výnos <strong x-text="grossYield.toFixed(2) + ' %'"></strong> · příjem <span x-text="fmt(r.income)"></span> Kč/rok</p>
        <table class="w-full text-sm">
            <thead><tr class="opacity-75"><th class="text-left font-normal"></th><th class="text-right font-normal">Paušál {{ $tax['flat_expense_pct'] }} %</th><th class="text-right font-normal">Skutečné výdaje</th></tr></thead>
            <tbody>
                <tr><td class="opacity-75">Výdaje</td><td class="text-right" x-text="fmt(r.flat.expenses)"></td><td class="text-right" x-text="fmt(r.actual.expenses)"></td></tr>
                <tr><td class="opacity-75">Základ daně</td><td class="text-right" x-text="fmt(r.flat.base)"></td><td class="text-right" x-text="fmt(r.actual.base)"></td></tr>
                <tr><td class="opacity-75">Daň</td><td class="text-right" x-text="fmt(r.flat.tax)"></td><td class="text-right" x-text="fmt(r.actual.tax)"></td></tr>
                <tr class="font-semibold"><td>Cash-flow / rok</td><td class="text-right" x-text="fmt(r.flat.cashflow)"></td><td class="text-right" x-text="fmt(r.actual.cashflow)"></td></tr>
            </tbody>
        </table>
        <p class="text-sm">Výhodnější: <strong x-text="r.better === 'flat' ? 'paušální výdaje' : 'skutečné výdaje'"></strong></p>
        <p class="text-xs opacity-75">Cash-flow = příjem − náklady − splátky hypotéky (<span x-text="fmt(payment)"></span> Kč/měs.) − daň. Skutečné výdaje = náklady + úroky z hypotéky za 1. rok (<span x-text="fmt(interest)"></span> Kč) + rovnoměrný odpis budovy 3,4 % (odpisová skupina 5; v 1. roce jen 1,4 %, pozemek se neodepisuje). Paušál {{ $tax['flat_expense_pct'] }} % nejvýše {{ number_format($tax['flat_expense_cap'], 0, ',', ' ') }} Kč (§ 9 odst. 4 ZDP) už zahrnuje všechny výdaje včetně úroků. Ztrátu z nájmu tento výpočet neuplatňuje.</p>
    </div>
</section>

<section x-cloak x-show="tab === 'koupe'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="buyCostCalc({{ $price }}, {{ $rules['cadastre']['deposit_fee'] }})">
    <div class="card grid gap-4 p-6 sm:grid-cols-2">
        <h2 class="font-display text-2xl font-bold sm:col-span-2">Kolik stojí koupě kromě ceny</h2>
        <label class="block"><span class="label">Kupní cena (Kč)</span><input type="number" min="0" step="10000" x-model.number="price" class="input"></label>
        <label class="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" x-model="mortgage" class="h-4 w-4"> Financuji hypotékou</label>
        <label class="block"><span class="label">Odhad nemovitosti (Kč)</span><input type="number" min="0" step="500" x-model.number="valuation" class="input"></label>
        <label class="block"><span class="label">Úschova (% z ceny, min. 10 000 Kč)</span><input type="number" min="0" step="0.05" x-model.number="escrowPct" class="input"></label>
        <label class="block"><span class="label">Advokát / notář – smlouva a návrh na vklad (Kč)</span><input type="number" min="0" step="1000" x-model.number="lawyer" class="input"></label>
        <label class="block"><span class="label">Provize makléře hrazená kupujícím (%)</span><input type="number" min="0" step="0.5" x-model.number="brokerPct" class="input"></label>
        <label class="block"><span class="label">Ostatní (ověření podpisů, výpisy, stěhování)</span><input type="number" min="0" step="500" x-model.number="other" class="input"></label>
    </div>
    <div class="bg-space space-y-3 rounded-[calc(var(--radius)+4px)] p-6">
        <p class="text-sm opacity-80">Vedlejší náklady celkem</p>
        <p class="kpi text-5xl"><span x-text="fmt(total)"></span> <span class="text-base">Kč</span></p>
        <dl class="space-y-1.5 text-sm"><template x-for="[k, v] in items" :key="k"><div class="flex justify-between gap-3"><dt class="opacity-75" x-text="k"></dt><dd class="shrink-0" x-text="fmt(v) + ' Kč'"></dd></div></template></dl>
        <p class="text-xs opacity-75">Správní poplatek za každý návrh na vklad do katastru je {{ number_format($rules['cadastre']['deposit_fee'], 0, ',', ' ') }} Kč (pol. 120 zákona č. 634/2004 Sb.). Daň z nabytí nemovitých věcí byla zrušena (zákon č. 386/2020 Sb.). Ceny odhadu, úschovy a právních služeb jsou orientační tržní hodnoty – upravte je podle nabídky.</p>
    </div>
</section>

<section x-cloak x-show="tab === 'prodej'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="saleCalc({{ $price }}, @js($tax))">
    <div class="card grid gap-4 p-6 sm:grid-cols-2">
        <h2 class="font-display text-2xl font-bold sm:col-span-2">Prodej: náklady a daň z příjmu</h2>
        <label class="block"><span class="label">Prodejní cena (Kč)</span><input type="number" min="0" step="10000" x-model.number="salePrice" class="input"></label>
        <label class="block"><span class="label">Pořizovací cena (Kč)</span><input type="number" min="0" step="10000" x-model.number="purchasePrice" class="input"></label>
        <label class="block"><span class="label">Prokazatelné výdaje (technické zhodnocení, poplatky)</span><input type="number" min="0" step="1000" x-model.number="costs" class="input"></label>
        <label class="block"><span class="label">Provize makléře (%)</span><input type="number" min="0" step="0.5" x-model.number="brokerPct" class="input"></label>
        <label class="block"><span class="label">Datum nabytí</span><input type="date" x-model="acquired" class="input"></label>
        <label class="block"><span class="label">Datum prodeje</span><input type="date" x-model="sold" class="input"></label>
        <label class="block"><span class="label">Bydliště v nemovitosti těsně před prodejem (let)</span><input type="number" min="0" step="0.5" x-model.number="residenceYears" class="input"></label>
        <label class="block"><span class="label">Ostatní roční základ daně (pro hranici 23 %)</span><input type="number" min="0" step="10000" x-model.number="otherIncome" class="input"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="isHome" class="h-4 w-4"> Byt (jednotka) nebo rodinný dům</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="reinvest" class="h-4 w-4"> Peníze použiji na vlastní bydlení</label>
    </div>
    <div class="bg-space space-y-3 rounded-[calc(var(--radius)+4px)] p-6">
        <p class="text-sm opacity-80">Daň z příjmu z prodeje</p>
        <p class="kpi text-5xl"><span x-text="fmt(r.tax)"></span> <span class="text-base">Kč</span></p>
        <p class="text-sm" x-show="r.exempt">Osvobozeno: <strong x-text="r.reason"></strong></p>
        <p class="text-sm" x-show="!r.exempt">Časový test <span x-text="r.testYears"></span> let – osvobození při prodeji od <strong x-text="r.exemptFrom"></strong></p>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="opacity-75">Zisk (základ daně)</dt><dd x-text="fmt(r.profit) + ' Kč'"></dd></div>
            <div class="flex justify-between"><dt class="opacity-75">Provize makléře</dt><dd x-text="fmt(broker) + ' Kč'"></dd></div>
            <div class="flex justify-between font-semibold"><dt>Čistě vám zůstane</dt><dd x-text="fmt(net) + ' Kč'"></dd></div>
        </dl>
        <p class="text-xs opacity-75">§ 4 odst. 1 písm. a), b) ZDP: osvobozen je prodej bytu/RD, kde jste měli bydliště alespoň 2 roky bezprostředně před prodejem, nebo když doba vlastnictví přesáhne 10 let (u nabytí před 1. 1. 2021 platí 5 let), případně při použití peněz na vlastní bytovou potřebu (lhůty a doklady hlídejte). Jinak daň {{ $tax['rate'] }} %, nad {{ number_format($tax['high_rate_threshold'], 0, ',', ' ') }} Kč ročního základu {{ $tax['rate_high'] }} % (2026). Osvobozený příjem nad 5 mil. Kč je nutné oznámit finančnímu úřadu. Nejde o daňové poradenství.</p>
    </div>
</section>

<section x-cloak x-show="tab === 'kauce'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="leaseCalc({{ $rent }}, @js($rules['lease']))">
    <div class="card grid gap-4 p-6 sm:grid-cols-2">
        <h2 class="font-display text-2xl font-bold sm:col-span-2">Kauce a výpovědní lhůta</h2>
        <label class="block"><span class="label">Nájemné bez služeb (Kč/měs.)</span><input type="number" min="0" step="500" x-model.number="rent" class="input"></label>
        <label class="block"><span class="label">Požadovaná kauce + smluvní pokuty (Kč)</span><input type="number" min="0" step="500" x-model.number="deposit" class="input"></label>
        <label class="block sm:col-span-2"><span class="label">Den, kdy výpověď došla druhé straně</span><input type="date" x-model="delivered" class="input"></label>
    </div>
    <div class="bg-space space-y-3 rounded-[calc(var(--radius)+4px)] p-6">
        <p class="text-sm opacity-80">Nejvyšší přípustná kauce</p>
        <p class="kpi text-5xl"><span x-text="fmt(max)"></span> <span class="text-base">Kč</span></p>
        <p class="text-sm" x-show="!ok"><span class="badge bg-accent text-accent-foreground">Překračuje zákonný limit</span></p>
        <p class="text-sm">Výpovědní doba (3 měsíce) běží od <strong x-text="date(notice.start)"></strong> a nájem skončí <strong x-text="date(notice.end)"></strong>.</p>
        <p class="text-xs opacity-75">§ 2254 OZ: jistota a smluvní pokuty dohromady nejvýše trojnásobek měsíčního nájemného; při skončení nájmu se vrací s úroky. § 2286 OZ: výpověď písemně, doba běží od 1. dne měsíce po doručení. Nájemce může nájem na dobu neurčitou vypovědět bez udání důvodu (§ 2231), pronajímatel jen z důvodů § 2288 a musí poučit nájemce o právu podat námitky. Bez výpovědní doby jen při zvlášť závažném porušení, např. nezaplacení nájemného a služeb za alespoň 3 měsíce (§ 2291), po předchozí výzvě.</p>
    </div>
</section>

<section class="card mt-10 p-6 text-sm" id="zdroje">
    <h2 class="font-display text-xl font-bold">Zdroje a pravidla (ověřeno {{ $verified }})</h2>
    <ul class="mt-3 list-disc space-y-1 pl-5">
        @foreach ($rules['sources'] as [$label, $url])
            <li><a class="underline" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a></li>
        @endforeach
    </ul>
    <p class="mt-3 text-xs text-muted-foreground">Výpočty jsou orientační a nenahrazují právní, daňové ani finanční poradenství.</p>
</section>
