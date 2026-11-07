<?php

use App\Jobs\ExpireListings;
use App\Jobs\SendSavedSearchAlerts;
use App\Mail\WeeklyKpiReport;
use App\Models\User;
use App\Services\AdminKpi;
use App\Services\Benchmarks\CsuBenchmarks;
use App\Services\Contracts\RentalContractService;
use App\Services\Heartbeat;
use App\Services\Maintenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireListings)->hourly();
Schedule::call(fn () => config('reality.features.contracts') && app(RentalContractService::class)->expireStale())->daily()->name('contracts:expire');
Schedule::command('reality:alerts:send')->dailyAt('07:00')->when(fn () => (bool) config('reality.features.alerts'))->withoutOverlapping();
Schedule::command('reality:agencies:import-all')->monthlyOn(10, '03:00')->when(fn () => (bool) config('reality.features.agencies_auto_import'))->withoutOverlapping(720)->runInBackground();
Schedule::command('reality:benchmarks:fetch')->monthlyOn(5, '04:00');
Schedule::command('reality:maintenance')->dailyAt('05:30')->withoutOverlapping();
Schedule::command('reality:report:weekly')->weeklyOn(1, '07:00')->when(fn () => (bool) config('reality.weekly_report.enabled'))->withoutOverlapping();
Schedule::command('reality:gov:fetch')->twiceDaily(6, 18);
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();

Artisan::command('reality:benchmarks:fetch {--csv : Also rewrite database/data/market_benchmarks_csu.csv} {--from-csv : Load committed CSV instead of downloading}', function (CsuBenchmarks $csu) {
    $path = base_path(CsuBenchmarks::CSV_PATH);
    if ($this->option('from-csv')) {
        $rows = $csu->readCsv($path);
    } else {
        try {
            $rows = $csu->fetch();
        } catch (Throwable $e) {
            $this->error('Stažení z ČSÚ selhalo: '.$e->getMessage());

            return 1;
        }
        if ($this->option('csv')) {
            $csu->writeCsv($rows, $path);
        }
    }
    $n = $csu->store($rows);
    $this->info("Uloženo {$n} oficiálních údajů (ČSÚ).");

    return 0;
})->purpose('Stáhne oficiální cenové ukazatele nemovitostí z otevřených dat ČSÚ');

Artisan::command('reality:alerts:send', function () {
    if (! config('reality.features.alerts')) {
        $this->warn('Upozornění hlídacích psů jsou vypnutá (REALITY_FEATURE_ALERTS).');

        return 0;
    }
    $n = (new SendSavedSearchAlerts)->handle();
    $this->info("Odesláno {$n} souhrnů hlídacích psů.");

    return 0;
})->purpose('Odešle denní souhrn nových inzerátů k uloženým hledáním');

Artisan::command('reality:agencies:import-all {--force : Spustit i při vypnutém příznaku}', function () {
    if (! config('reality.features.agencies_auto_import') && ! $this->option('force')) {
        $this->warn('Automatický import kanceláří je vypnutý (REALITY_FEATURE_AGENCIES_AUTO_IMPORT).');

        return 0;
    }
    $regions = config('reality.agency_import_regions');
    $pause = (int) config('reality.agency_import_pause_s');
    $failed = 0;
    foreach (array_values($regions) as $i => $kraj) {
        if ($i > 0 && $pause > 0) {
            sleep($pause); // be polite to ARES between regions
        }
        $this->line("Kraj {$kraj}…");
        $code = $this->call('reality:agencies:import', ['--kraj' => $kraj]);
        $failed += $code === 0 ? 0 : 1;
    }
    $this->info('Hotovo, krajů: '.count($regions).', chyb: '.$failed.'.');

    return $failed === count($regions) ? 1 : 0;
})->purpose('Měsíční import realitních kanceláří z ARES po krajích');

Artisan::command('reality:maintenance', function (Maintenance $m) {
    if (! config('reality.automation.enabled')) {
        $this->warn('Automatická údržba je vypnutá (REALITY_AUTOMATION_ENABLED).');

        return 0;
    }
    $r = $m->run();
    $this->info("Vypršelo: {$r['expired']}, připomínek: {$r['reminded']}, smazaných zpráv: {$r['pruned']}, anonymizováno: {$r['anonymized']}, smazaných dotazů: {$r['messages']}.");

    return 0;
})->purpose('Denní údržba: expirace inzerátů, připomínky vlastníkům, čištění vyřízených zpráv');

Artisan::command('reality:report:weekly', function (AdminKpi $kpi, Heartbeat $hb) {
    if (! config('reality.weekly_report.enabled')) {
        $this->warn('Týdenní souhrn je vypnutý (REALITY_WEEKLY_REPORT_ENABLED).');

        return 0;
    }
    $admins = User::where('role', 'admin')->whereNull('blocked_at')->pluck('email')->filter()->all();
    if (! $admins) {
        $this->warn('Žádný administrátor s e-mailem.');

        return 0;
    }
    Mail::to($admins)->send(new WeeklyKpiReport($kpi->all(), $hb->stale()));
    $this->info('Souhrn odeslán '.count($admins).' administrátorům.');

    return 0;
})->purpose('Týdenní souhrn KPI e-mailem administrátorům');
