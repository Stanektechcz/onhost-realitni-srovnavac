<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViewingSlot extends Model
{
    protected $fillable = ['listing_id', 'starts_at', 'duration_min'];

    protected function casts(): array { return ['starts_at' => 'datetime', 'booked_at' => 'datetime']; }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function booker(): BelongsTo { return $this->belongsTo(User::class, 'booked_by'); }

    public function scopeFree(Builder $q): Builder { return $q->whereNull('booked_by')->where('starts_at', '>', now()); }

    public function isFree(): bool { return $this->booked_by === null && $this->starts_at->isFuture(); }
}
