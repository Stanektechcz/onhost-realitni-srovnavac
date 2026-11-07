<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyReview extends Model
{
    public const STATUSES = ['pending', 'published', 'hidden'];

    protected $fillable = ['rating', 'text'];

    protected function casts(): array { return ['verified' => 'boolean', 'rating' => 'integer', 'moderated_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function agency(): BelongsTo { return $this->belongsTo(Agency::class); }
    public function broker(): BelongsTo { return $this->belongsTo(Broker::class); }
    public function reports(): HasMany { return $this->hasMany(AgencyReviewReport::class); }

    public function scopePublished(Builder $q): Builder { return $q->where('status', 'published'); }

    /** The agency the review is about (directly or through its broker). */
    public function targetAgency(): ?Agency { return $this->agency ?? $this->broker?->agency; }

    public function refreshTargets(): void
    {
        $this->agency?->recalcRating();
        $this->broker?->recalcRating();
    }

    /** Verified = the author contacted or booked a viewing of a listing of the agency's feed account. */
    public static function isVerifiedContact(User $user, Agency $agency): bool
    {
        if (! $agency->isVerified()) {
            return false;
        }
        $ids = Listing::where('user_id', $agency->claimed_by_user_id)->pluck('id');

        return Message::whereIn('listing_id', $ids)->where('sender_id', $user->id)->exists()
            || ViewingSlot::whereIn('listing_id', $ids)->where('booked_by', $user->id)->exists();
    }
}
