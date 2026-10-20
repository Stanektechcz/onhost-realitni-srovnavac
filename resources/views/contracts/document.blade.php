@php
    $czk = fn ($h) => \App\Models\Listing::formatCzk($h);
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('j. n. Y') : '—';
    $t = $c->data['terms'] ?? [];
    $party = fn ($p) => ($c->data[$p]['name'] ?? '').', bytem '.($c->data[$p]['address'] ?? '').', nar./IČO: '.($c->data[$p]['birth_or_ico'] ?? '').', e-mail: '.($c->data[$p]['email'] ?? '').(! empty($c->data[$p]['phone']) ? ', tel.: '.$c->data[$p]['phone'] : '');
@endphp
<div class="contract-doc">
<p class="contract-flag"><strong>[PRÁVNÍK]</strong> Vzorová smlouva – šablona musí být před ostrým použitím zkontrolována advokátem. Neposkytujeme právní poradenství.</p>
<h1>Smlouva o nájmu bytu</h1>
<p class="contract-sub">uzavřená podle § 2235 a násl. zákona č. 89/2012 Sb., občanský zákoník (dále jen „NOZ“)<br>Číslo smlouvy: {{ $c->uuid }} · verze {{ $c->version }}</p>

<h2>Smluvní strany</h2>
<p><strong>Pronajímatel:</strong> {{ $party('landlord') }}</p>
<p><strong>Nájemce:</strong> {{ $party('tenant') }}</p>

<h2>I. Předmět nájmu</h2>
<p>1. Pronajímatel přenechává nájemci k dočasnému užívání byt{{ ! empty($c->data['property']['unit_no']) ? ' č. '.$c->data['property']['unit_no'] : '' }} na adrese {{ $c->data['property']['address'] ?? '' }}, dispozice {{ $c->data['property']['disposition'] ?? '—' }}, o podlahové ploše {{ $c->data['property']['area_m2'] ?? '—' }} m² (dále jen „byt“), a nájemce se zavazuje platit pronajímateli nájemné.</p>
<p>2. Byt slouží k zajištění bytových potřeb nájemce a členů jeho domácnosti. Byt budou užívat nejvýše {{ $t['occupants'] ?? 1 }} osoby (osob) včetně nájemce. Nájemce oznámí pronajímateli změnu počtu osob bez zbytečného odkladu (§ 2272 NOZ).</p>

<h2>II. Doba nájmu</h2>
@if (($t['term'] ?? '') === 'fixed')
<p>Nájem se sjednává na dobu určitou od {{ $date($t['start_date'] ?? null) }} do {{ $date($t['end_date'] ?? null) }}. Upozorňujeme na § 2285 NOZ (fikce obnovení nájmu, nevyzve-li pronajímatel nájemce k vyklizení do tří měsíců po skončení nájmu).</p>
@else
<p>Nájem se sjednává na dobu neurčitou s účinností od {{ $date($t['start_date'] ?? null) }}.</p>
@endif
<p>Výpovědní doba činí {{ $t['notice_months'] ?? 3 }} měsíce (měsíců) a počíná běžet prvním dnem kalendářního měsíce následujícího po doručení výpovědi. Pronajímatel může vypovědět nájem pouze z důvodů uvedených v § 2288 NOZ; nájemce může vypovědět nájem na dobu neurčitou bez uvedení důvodu (§ 2287 NOZ).</p>

<h2>III. Nájemné a úhrady za služby</h2>
<p>1. Nájemné činí {{ $czk($c->rent_halere) }} měsíčně.</p>
<p>2. Zálohy na služby spojené s užíváním bytu (§ 2247 NOZ, zákon č. 67/2013 Sb.) činí {{ $czk($c->fees_halere) }} měsíčně. Pronajímatel vyúčtuje zálohy nejpozději do 4 měsíců po skončení zúčtovacího období.</p>
<p>3. Nájemné a zálohy jsou splatné měsíčně předem, vždy do {{ $t['payment_day'] ?? 15 }}. dne příslušného měsíce, na účet pronajímatele č. {{ $t['account'] ?? '' }}.</p>
<p>4. Pronajímatel může navrhnout zvýšení nájemného postupem podle § 2249 NOZ.</p>

<h2>IV. Jistota (kauce)</h2>
<p>Nájemce složí pronajímateli jistotu ve výši {{ $czk($c->deposit_halere) }} nejpozději v den předání bytu. Jistota nepřevyšuje trojnásobek měsíčního nájemného (§ 2254 odst. 1 NOZ). Při skončení nájmu pronajímatel jistotu vrátí včetně úroků po započtení případných pohledávek z nájmu (§ 2254 odst. 2 NOZ).</p>

<h2>V. Práva a povinnosti stran</h2>
<p>1. Pronajímatel předá nájemci byt ve stavu způsobilém k nastěhování a obývání a zajistí nájemci plný a nerušený výkon jeho práv (§ 2243 NOZ). O předání bude sepsán předávací protokol.</p>
<p>2. Nájemce provádí a hradí běžnou údržbu a drobné opravy podle nařízení vlády č. 308/2015 Sb. Stavební úpravy smí nájemce provádět jen se souhlasem pronajímatele (§ 2263 NOZ).</p>
<p>3. Podnájem bytu nebo jeho části je možný jen s písemným souhlasem pronajímatele (§ 2274 a 2275 NOZ).</p>
<p>4. Chov zvířat v bytě: {{ ! empty($t['pets']) ? 'povolen, nezpůsobí-li pronajímateli ani ostatním obyvatelům domu obtíže nepřiměřené poměrům v domě (§ 2258 NOZ).' : 'pouze v rozsahu § 2258 NOZ (zvířata nezpůsobující obtíže); jiný chov vyžaduje souhlas pronajímatele.' }}</p>

<h2>VI. Stavy měřidel</h2>
<p class="whitespace-pre-line">{{ ($t['meter_readings'] ?? '') ?: 'Budou zapsány v předávacím protokolu při předání bytu.' }}</p>

<h2>VII. Zvláštní ujednání</h2>
<p class="whitespace-pre-line">{{ ($t['special_terms'] ?? '') ?: 'Žádná.' }}</p>

<h2>VIII. Závěrečná ustanovení</h2>
<p>1. Ujednání odchylná od NOZ, která zkracují práva nájemce, jsou neplatná (§ 2235 odst. 2 NOZ).</p>
<p>2. Smlouva je uzavřena v elektronické podobě a podepsána prostým elektronickým podpisem obou stran prostřednictvím portálu {{ config('app.name') }} (čl. 3 bod 10 nařízení eIDAS, § 561 NOZ). Každá strana obdrží finální znění e-mailem spolu s auditním záznamem podpisů.</p>
<p>3. Smlouva nabývá platnosti a účinnosti podpisem obou smluvních stran. Strany prohlašují, že si smlouvu přečetly a souhlasí s jejím obsahem.</p>
</div>
