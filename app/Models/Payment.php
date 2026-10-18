<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = ['user_id', 'service', 'provider', 'provider_ref', 'amount_halere', 'commission_halere', 'currency', 'status', 'meta'];

    protected function casts(): array { return ['meta' => 'array']; }

    public function payable(): MorphTo { return $this->morphTo(); }
}
