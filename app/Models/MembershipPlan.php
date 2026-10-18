<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    protected $fillable = ['code', 'name', 'audience', 'price_halere', 'period_days', 'features', 'is_active'];

    protected function casts(): array { return ['features' => 'array', 'is_active' => 'boolean', 'price_halere' => 'integer']; }
}
