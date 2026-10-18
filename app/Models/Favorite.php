<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'listing_id'];

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
}
