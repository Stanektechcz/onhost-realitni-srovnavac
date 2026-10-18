<?php

namespace Database\Seeders;

use App\Models\CommissionRule;
use App\Models\Listing;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Demo data — ALL people and listings are fictional (example.test domain, invented descriptions). */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! env('SEED_ALLOW_PRODUCTION')) {
            $this->command?->error('Demo seeder se na produkci nespouští – data na webu musí být pravdivá.');

            return;
        }
        $adminPassword = env('SEED_ADMIN_PASSWORD') ?: Str::password(16);
        User::updateOrCreate(['email' => 'admin@example.test'], ['name' => 'Správce Demo', 'password' => $adminPassword, 'role' => 'admin']);
        $this->command?->warn("Admin: admin@example.test / heslo: ".(env('SEED_ADMIN_PASSWORD') ? '(z SEED_ADMIN_PASSWORD)' : $adminPassword));

        $owners = collect(range(1, 6))->map(fn ($i) => User::updateOrCreate(
            ['email' => "majitel$i@example.test"],
            ['name' => "Fiktivní Majitel $i", 'password' => Str::password(16), 'role' => $i === 6 ? 'agency' : 'owner'],
        ));
        User::updateOrCreate(['email' => 'hledajici@example.test'], ['name' => 'Fiktivní Hledající', 'password' => Str::password(16), 'role' => 'seeker']);

        $districts = [
            ['Vinohrady', 50.0755, 14.4430, 'Slezská'], ['Žižkov', 50.0840, 14.4500, 'Seifertova'], ['Karlín', 50.0925, 14.4500, 'Sokolovská'],
            ['Holešovice', 50.1030, 14.4380, 'Dělnická'], ['Smíchov', 50.0710, 14.4030, 'Nádražní'], ['Dejvice', 50.1000, 14.3920, 'Bubenečská'],
            ['Nusle', 50.0630, 14.4410, 'Táborská'], ['Libeň', 50.1040, 14.4760, 'Zenklova'], ['Vršovice', 50.0700, 14.4560, 'Krymská'],
            ['Břevnov', 50.0840, 14.3650, 'Bělohorská'], ['Letná', 50.1000, 14.4270, 'Milady Horákové'], ['Strašnice', 50.0730, 14.4930, 'Starostrašnická'],
        ];
        $disp = [['1+kk', 28, 15500], ['1+1', 36, 17000], ['2+kk', 52, 23500], ['2+1', 60, 25000], ['3+kk', 78, 32000], ['3+1', 85, 34000], ['4+kk', 105, 45000]];
        $adjectives = ['Světlý', 'Útulný', 'Zrekonstruovaný', 'Tichý', 'Prostorný', 'Moderní'];
        $penb = ['B', 'C', 'C', 'D', 'D', 'E', 'F', 'G', 'none'];
        mt_srand(42);

        for ($i = 0; $i < 40; $i++) {
            [$district, $lat, $lng, $street] = $districts[$i % count($districts)];
            [$d, $area, $price] = $disp[mt_rand(0, count($disp) - 1)];
            $area += mt_rand(-4, 12);
            $price = (int) round(($price + mt_rand(-2000, 6000)) / 500) * 500;
            $l = Listing::firstOrNew(['slug' => 'demo-praha-'.($i + 1)]);
            $l->forceFill([
                'user_id' => $owners[$i % $owners->count()]->id,
                'title' => null,
                'description' => $adjectives[$i % 6]." byt $d v lokalitě $district. FIKTIVNÍ UKÁZKOVÝ INZERÁT – nemovitost ani osoby neexistují.\n\n"
                    ."Byt se nachází v ".mt_rand(1, 6).". patře činžovního domu, okna do klidného dvora. Kuchyňská linka se spotřebiči, koupelna se sprchovým koutem. "
                    ."Výborná dostupnost MHD, v okolí obchody a park.",
                'offer_type' => 'rent', 'property_type' => 'flat', 'disposition' => $d, 'area_m2' => $area,
                'price_halere' => $price * 100, 'fees_halere' => mt_rand(20, 50) * 100 * 100, 'deposit_halere' => $price * 2 * 100,
                'city' => 'Praha', 'district' => $district, 'street' => $street,
                'lat' => round($lat + (mt_rand(-60, 60) / 10000), 7), 'lng' => round($lng + (mt_rand(-60, 60) / 10000), 7),
                'penb' => $penb[$i % count($penb)], 'furnishing' => ['none', 'partial', 'full'][$i % 3], 'floor' => mt_rand(0, 6),
                'has_elevator' => $i % 2 === 0, 'has_balcony' => $i % 3 === 0, 'has_parking' => $i % 5 === 0, 'pets_allowed' => $i % 4 === 0,
                'available_from' => now()->addDays(mt_rand(0, 60))->toDateString(),
                'status' => $i >= 37 ? 'pending' : 'active', 'source' => 'internal',
                'published_at' => $i >= 37 ? null : now()->subHours($i * 7),
                'expires_at' => $i >= 37 ? null : now()->addDays(60),
            ])->save();
        }

        $this->seedSalesAndMarket($owners, $districts);

        foreach ([
            ['adv-free', 'Start', 'advertiser', 0, ['1 aktivní inzerát', 'Základní statistiky']],
            ['adv-plus', 'Plus', 'advertiser', 29900, ['5 aktivních inzerátů', 'Topování 1× týdně', 'Statistiky zobrazení']],
            ['adv-pro', 'Profi', 'advertiser', 99000, ['Neomezeně inzerátů', 'Import XML', 'Prioritní podpora']],
            ['seek-free', 'Základ', 'seeker', 0, ['1 hlídací pes', 'Oblíbené']],
            ['seek-plus', 'Hledač+', 'seeker', 14900, ['Okamžitý hlídací pes', 'Neomezeně hlídacích psů', 'Upozornění na pokles ceny']],
        ] as [$code, $name, $aud, $price, $features]) {
            MembershipPlan::updateOrCreate(['code' => $code], ['name' => $name, 'audience' => $aud, 'price_halere' => $price, 'period_days' => 30, 'features' => $features, 'is_active' => true]);
        }
        CommissionRule::firstOrCreate(['service' => 'membership'], ['percent' => 0, 'fixed_halere' => 0, 'is_active' => true]);
    }

    /** Sale listings, aggregate-only imported market data, stats and viewing slots (all fictional). */
    private function seedSalesAndMarket($owners, array $districts): void
    {
        $saleDisp = [['1+kk', 30, 4_300_000], ['2+kk', 55, 7_200_000], ['3+kk', 80, 10_100_000], ['3+1', 88, 10_900_000], ['4+kk', 110, 14_500_000]];
        $premium = ['Vinohrady' => 1.15, 'Karlín' => 1.12, 'Dejvice' => 1.1, 'Letná' => 1.12, 'Holešovice' => 1.02, 'Smíchov' => 1.0, 'Žižkov' => 0.95, 'Nusle' => 0.93, 'Vršovice' => 0.97, 'Libeň' => 0.9, 'Břevnov' => 0.96, 'Strašnice' => 0.88];
        for ($i = 0; $i < 20; $i++) {
            [$district, $lat, $lng, $street] = $districts[$i % count($districts)];
            [$d, $area, $price] = $saleDisp[mt_rand(0, count($saleDisp) - 1)];
            $area += mt_rand(-3, 10);
            $price = (int) round($price * ($premium[$district] ?? 1) * (mt_rand(92, 110) / 100) / 10000) * 10000;
            $l = Listing::firstOrNew(['slug' => 'demo-prodej-'.($i + 1)]);
            $l->forceFill([
                'user_id' => $owners[$i % $owners->count()]->id, 'title' => null,
                'description' => "Byt $d na prodej v lokalitě $district. FIKTIVNÍ UKÁZKOVÝ INZERÁT – nemovitost ani osoby neexistují.\n\nOsobní vlastnictví, dům po revitalizaci, sklep. Nízký fond oprav, klidná ulice, škola i obchody v docházkové vzdálenosti.",
                'offer_type' => 'sale', 'property_type' => 'flat', 'disposition' => $d, 'area_m2' => $area,
                'price_halere' => $price * 100, 'fees_halere' => null, 'deposit_halere' => null,
                'city' => 'Praha', 'district' => $district, 'street' => $street,
                'lat' => round($lat + (mt_rand(-60, 60) / 10000), 7), 'lng' => round($lng + (mt_rand(-60, 60) / 10000), 7),
                'penb' => ['B', 'C', 'D', 'E'][$i % 4], 'furnishing' => 'partial', 'floor' => mt_rand(1, 7),
                'has_elevator' => $i % 2 === 1, 'has_balcony' => $i % 3 === 1, 'has_parking' => $i % 4 === 0, 'pets_allowed' => true,
                'status' => 'active', 'source' => 'internal', 'published_at' => now()->subDays($i * 3 + 1), 'expires_at' => now()->addDays(45),
                'boosted_until' => $i === 0 ? now()->addDays(5) : null,
            ])->save();
        }

        // Imported market data: used ONLY for aggregates (never shown individually).
        for ($i = 0; $i < 160; $i++) {
            [$district, $lat, $lng] = $districts[$i % count($districts)];
            $rent = $i % 3 !== 0;
            $area = mt_rand(26, 110);
            $ppm2 = $rent ? mt_rand(330, 450) * ($premium[$district] ?? 1) : mt_rand(115_000, 150_000) * ($premium[$district] ?? 1);
            $months = mt_rand(0, 11);
            Listing::firstOrNew(['source_name' => 'demo-import', 'source_id' => (string) $i])->forceFill([
                'slug' => 'imp-'.$i, 'offer_type' => $rent ? 'rent' : 'sale', 'property_type' => 'flat',
                'disposition' => ['1+kk', '2+kk', '2+1', '3+kk', '3+1'][$i % 5], 'area_m2' => $area,
                'price_halere' => (int) round($area * $ppm2 * (1 + $months * 0.004)) * 100,
                'city' => 'Praha', 'district' => $district,
                'lat' => round($lat + (mt_rand(-80, 80) / 10000), 7), 'lng' => round($lng + (mt_rand(-80, 80) / 10000), 7),
                'penb' => 'D', 'status' => $months > 2 ? 'expired' : 'active', 'source' => 'imported', 'user_id' => null,
                'published_at' => now()->subMonths($months)->subDays(mt_rand(0, 25)),
            ])->save();
        }

        // Stats + viewing slots for demo internal listings.
        $demo = Listing::where('slug', 'like', 'demo-%')->where('status', 'active')->get();
        foreach ($demo as $idx => $l) {
            for ($d = 0; $d < 30; $d++) {
                \App\Models\ListingStat::updateOrCreate(['listing_id' => $l->id, 'day' => now()->subDays($d)->toDateString()],
                    ['views' => mt_rand(0, 25), 'saves' => mt_rand(0, 2), 'inquiries' => mt_rand(0, 1), 'bookings' => 0]);
            }
            if ($idx < 10 && ! $l->slots()->exists()) {
                foreach ([1, 2, 4] as $day) {
                    foreach (['17:00', '17:30', '18:00'] as $t) {
                        $l->slots()->create(['starts_at' => now()->addDays($day)->setTimeFromTimeString($t), 'duration_min' => 30]);
                    }
                }
            }
        }
    }
}