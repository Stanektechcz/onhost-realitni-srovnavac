<?php

use App\Jobs\ExpireListings;
use App\Jobs\SendSavedSearchAlerts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireListings)->hourly();
Schedule::job(new SendSavedSearchAlerts)->dailyAt('07:00');
Schedule::command('reality:benchmarks:fetch')->monthlyOn(5, '04:00');
Schedule::command('reality:gov:fetch')->twiceDaily(6, 18);
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();

Artisan::command('reality:benchmarks:fetch {--csv : Also rewrite database/data/market_benchmarks_csu.csv} {--from-csv : Load committed CSV instead of downloading}', function (App\Services\Benchmarks\CsuBenchmarks $csu) {
    $path = base_path(App\Services\Benchmarks\CsuBenchmarks::CSV_PATH);
    if ($this->option('from-csv')) {
        $rows = $csu->readCsv($path);
    } else {
        try {
            $rows = $csu->fetch();
        } catch (\Throwable $e) {
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
