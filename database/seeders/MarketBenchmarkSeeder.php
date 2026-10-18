<?php

namespace Database\Seeders;

use App\Services\Benchmarks\CsuBenchmarks;
use Illuminate\Database\Seeder;

/** Real official values (ČSÚ open data) committed in database/data – safe also in production. */
class MarketBenchmarkSeeder extends Seeder
{
    public function run(CsuBenchmarks $csu): void
    {
        $csu->store($csu->readCsv(base_path(CsuBenchmarks::CSV_PATH)));
    }
}
