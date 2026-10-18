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
}
