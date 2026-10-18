<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'pending', 'active', 'expired', 'rejected'];

    protected $guarded = ['id', 'user_id', 'status', 'source', 'views', 'published_at', 'expires_at', 'moderation_note', 'slug'];

    protected function casts(): array
    {
        return [
            'area_m2' => 'float', 'lat' => 'float', 'lng' => 'float',
            'price_halere' => 'integer', 'fees_halere' => 'integer', 'deposit_halere' => 'integer',
            'floor' => 'integer',
            'has_elevator' => 'boolean', 'has_balcony' => 'boolean', 'has_parking' => 'boolean', 'pets_allowed' => 'boolean',
            'available_from' => 'date', 'published_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $l) {
            $l->slug ??= Str::lower(Str::random(10));
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function photos(): HasMany { return $this->hasMany(ListingPhoto::class)->orderBy('position'); }
    public function reports(): HasMany { return $this->hasMany(Report::class); }

    /** Only internal, active, non-expired listings are ever shown publicly. */
    public function scopePublic(Builder $q): Builder
    {
        return $q->where('source', 'internal')
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeImported(Builder $q): Builder { return $q->where('source', 'imported'); }

    public function isPublic(): bool
    {
        return $this->source === 'internal' && $this->status === 'active' && $this->published_at
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function scopeFilter(Builder $q, array $f): Builder
    {
        $q->when($f['offer_type'] ?? null, fn ($q, $v) => $q->where('offer_type', $v))
          ->when($f['property_type'] ?? null, fn ($q, $v) => $q->where('property_type', $v))
          ->when($f['disposition'] ?? null, fn ($q, $v) => $q->whereIn('disposition', (array) $v))
          ->when($f['city'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('city', 'like', "%$v%")->orWhere('district', 'like', "%$v%")))
          ->when($f['price_min'] ?? null, fn ($q, $v) => $q->where('price_halere', '>=', (int) $v * 100))
          ->when($f['price_max'] ?? null, fn ($q, $v) => $q->where('price_halere', '<=', (int) $v * 100))
          ->when($f['area_min'] ?? null, fn ($q, $v) => $q->where('area_m2', '>=', (float) $v))
          ->when($f['area_max'] ?? null, fn ($q, $v) => $q->where('area_m2', '<=', (float) $v))
          ->when($f['penb'] ?? null, fn ($q, $v) => $q->whereIn('penb', (array) $v))
          ->when($f['furnishing'] ?? null, fn ($q, $v) => $q->where('furnishing', $v));
        foreach (['has_balcony', 'has_elevator', 'has_parking', 'pets_allowed'] as $flag) {
            if (! empty($f[$flag])) {
                $q->where($flag, true);
            }
        }

        return match ($f['sort'] ?? 'newest') {
            'price_asc' => $q->orderBy('price_halere'),
            'price_desc' => $q->orderByDesc('price_halere'),
            'area_desc' => $q->orderByDesc('area_m2'),
            default => $q->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    public function priceCzk(): ?int { return $this->price_halere === null ? null : intdiv($this->price_halere, 100); }

    public static function formatCzk(?int $halere): string
    {
        return $halere === null ? '—' : number_format(intdiv($halere, 100), 0, ',', ' ').' Kč';
    }

    public function displayTitle(): string
    {
        $type = config('reality.offer_types')[$this->offer_type] ?? '';
        $prop = mb_strtolower(config('reality.property_types')[$this->property_type] ?? '');

        return $this->title ?: trim("$type $prop {$this->disposition}".($this->area_m2 ? ', '.rtrim(rtrim(number_format($this->area_m2, 1, ',', ''), '0'), ',').' m²' : ''));
    }

    public function locationLabel(): string
    {
        return collect([$this->street, $this->district, $this->city])->filter()->join(', ');
    }
}
