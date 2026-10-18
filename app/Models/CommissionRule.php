<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Commission applies ONLY to paid portal services (memberships, boosts) — never to property prices. */
class CommissionRule extends Model
{
    protected $fillable = ['service', 'percent', 'fixed_halere', 'is_active'];

    protected function casts(): array { return ['percent' => 'float', 'fixed_halere' => 'integer', 'is_active' => 'boolean']; }
}
