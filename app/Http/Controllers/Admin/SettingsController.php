<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LegalEntity;

/**
 * Nastavení jen ke čtení: zobrazuje VÝHRADNĚ zapnuto/vypnuto a neveřejné názvy (poskytovatel plateb).
 * Nikdy nečte client_secret, merchant, secret, klíče ani tokeny – hodnoty se berou jen z vyjmenovaných klíčů.
 */
class SettingsController extends Controller
{
    /** [config klíč => [env proměnná, popis]] – jen booleovské příznaky. */
    public const FLAGS = [
        'Funkce portálu' => [
            'reality.features.memberships' => ['REALITY_FEATURE_MEMBERSHIPS', 'Placená členství (tarify) pro inzerenty a hledající. Bez něj stránka tarifů ani nákup neexistují.'],
            'reality.features.payments' => ['REALITY_FEATURE_PAYMENTS', 'Platby přes platební bránu (PaymentProvider). Bez něj se žádná platba nezaloží.'],
            'reality.features.commissions' => ['REALITY_FEATURE_COMMISSIONS', 'Výpočet provizí portálu z plateb podle pravidel provizí.'],
            'reality.features.boost' => ['REALITY_FEATURE_BOOST', 'Placené topování (zvýhodnění) inzerátu ve výpisu.'],
            'reality.features.contracts' => ['REALITY_FEATURE_CONTRACTS', 'Nájemní smlouva v systému: návrh, elektronický podpis a automatické uzavření inzerátu.'],
            'reality.features.alerts' => ['REALITY_FEATURE_ALERTS', 'Hlídací pes – e-mailový přehled nových inzerátů podle uložených hledání.'],
            'reality.features.agencies_auto_import' => ['REALITY_FEATURE_AGENCIES_AUTO_IMPORT', 'Měsíční import realitních kanceláří z otevřených dat ARES po krajích.'],
            'reality.features.price_history' => ['REALITY_FEATURE_PRICE_HISTORY', 'Historie ceny a štítek „cena snížena“ na detailu inzerátu.'],
            'reality.automation.enabled' => ['REALITY_AUTOMATION_ENABLED', 'Denní údržba: expirace inzerátů, připomínka 3 dny před koncem, smazání vyřízených zpráv po 180 dnech.'],
        ],
        'ONhost' => [
            'oidc.enabled' => ['ONHOST_OIDC_ENABLED', 'Přihlášení přes ONhost ID (OpenID Connect).'],
            'oidc.backchannel_logout' => ['ONHOST_OIDC_BACKCHANNEL', 'Odhlášení ze všech relací na pokyn ONhost ID (back-channel logout).'],
            'onhost.credit.enabled' => ['ONHOST_CREDIT_ENABLED', 'Platba kreditem ONhost a propojení peněženky. Vypnuto = žádná trasa ani volání API.'],
            'onhost.credit.allow_bonus' => ['ONHOST_CREDIT_ALLOW_BONUS', 'Bonusový kredit smí uhradit část platby (kolik, rozhoduje ONhost).'],
        ],
        'Ekosystém ONhost' => [
            'ecosystem.catalog.enabled' => ['ECOSYSTEM_CATALOG_ENABLED', 'Zveřejnění nabídek do katalogu ekosystému ONhost.'],
            'ecosystem.health.enabled' => ['ECOSYSTEM_HEALTH_ENABLED', 'Endpoint zdraví aplikace pro monitoring ekosystému.'],
            'ecosystem.bus.enabled' => ['ECOSYSTEM_BUS_ENABLED', 'Příjem a odesílání událostí sběrnice ekosystému (podepsané webhooky).'],
        ],
        'Platby' => [
            'reality.payments.comgate.test' => ['COMGATE_TEST', 'Testovací režim brány Comgate (bez skutečných plateb).'],
        ],
    ];

    public function index()
    {
        $groups = [];
        foreach (self::FLAGS as $group => $flags) {
            foreach ($flags as $key => [$env, $desc]) {
                $groups[$group][] = ['key' => $key, 'env' => $env, 'description' => $desc, 'on' => (bool) config($key)];
            }
        }
        $provider = (string) config('reality.payments.provider');
        $providers = ['stub' => 'Stub (jen lokální vývoj, bez skutečných plateb)', 'comgate' => 'Comgate'];

        return view('admin.settings', [
            'groups' => $groups,
            'legal' => LegalEntity::displayLine(),
            'provider' => $providers[$provider] ?? 'neznámý',
            'providerCode' => array_key_exists($provider, $providers) ? $provider : null,
        ]);
    }
}
