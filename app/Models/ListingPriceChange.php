<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPriceChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['old_price_halere', 'new_price_halere'];

    protected function casts(): array
    {
        return ['old_price_halere' => 'integer', 'new_price_halere' => 'integer', 'created_at' => 'datetime'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
