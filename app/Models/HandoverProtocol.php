<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverProtocol extends Model
{
    protected $fillable = ['listing_id', 'user_id', 'data'];

    protected function casts(): array { return ['data' => 'array']; }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
}
