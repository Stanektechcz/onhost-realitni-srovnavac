<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Czech real-estate agency (IČO from public ARES data, optionally claimed by its feed account). */
class Agency extends Model
{
    protected $guarded = ['id', 'claimed_by_user_id', 'verified_at', 'rating_avg', 'reviews_count'];

    protected function casts(): array
    {
        return ['natural_person' => 'boolean', 'verified_at' => 'datetime', 'ares_synced_at' => 'datetime', 'founded_on' => 'date', 'rating_avg' => 'float'];
    }

    protected static function booted(): void
    {
        static::creating(function (Agency $a) {
            $a->slug ??= Str::slug(Str::limit($a->name, 80, '')).'-'.$a->ico;
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'claimed_by_user_id'); }
    public function brokers(): HasMany { return $this->hasMany(Broker::class); }
    public function reviews(): HasMany { return $this->hasMany(AgencyReview::class); }
    public function claims(): HasMany { return $this->hasMany(AgencyClaim::class); }

    public function isVerified(): bool { return $this->verified_at !== null && $this->claimed_by_user_id !== null; }

    /** Public listings of the agency's verified feed account (never imported ones: Listing::public()). */
    public function publicListings(): Builder
    {
        return Listing::public()->where('user_id', $this->isVerified() ? $this->claimed_by_user_id : 0);
    }

    /** Adds offers_count (public listings of the verified feed account). */
    public function scopeWithOfferCount(Builder $q): Builder
    {
        $sub = Listing::public()->selectRaw('count(*)')->whereColumn('listings.user_id', 'agencies.claimed_by_user_id');

        return $q->select('agencies.*')
            ->selectRaw('(case when agencies.verified_at is null then 0 else ('.$sub->toRawSql().') end) as offers_count');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('ico', $term));
    }

    public function recalcRating(): void
    {
        $pub = $this->reviews()->where('status', 'published');
        $this->forceFill(['reviews_count' => (clone $pub)->count(), 'rating_avg' => (clone $pub)->avg('rating')])->save();
    }

    public static function forListing(Listing $l): ?self
    {
        return $l->user_id ? static::whereNotNull('verified_at')->where('claimed_by_user_id', $l->user_id)->first() : null;
    }
}
