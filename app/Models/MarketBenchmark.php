<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketBenchmark extends Model
{
    protected $guarded = [];

    protected $casts = ['value' => 'float'];
}
