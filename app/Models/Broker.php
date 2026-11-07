<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Broker extends Model
{
    protected $fillable = ['name', 'bio', 'specialization', 'is_public'];

    protected function casts(): array { return ['is_public' => 'boolean', 'rating_avg' => 'float']; }

    protected static function booted(): void
    {
        static::creating(function (Broker $b) {
            $b->slug ??= Str::slug(Str::limit($b->name, 60, '')).'-'.Str::lower(Str::random(6));
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function agency(): BelongsTo { return $this->belongsTo(Agency::class); }
    public function reviews(): HasMany { return $this->hasMany(AgencyReview::class); }

    public function recalcRating(): void
    {
        $pub = $this->reviews()->where('status', 'published');
        $this->forceFill(['reviews_count' => (clone $pub)->count(), 'rating_avg' => (clone $pub)->avg('rating')])->save();
    }
}
