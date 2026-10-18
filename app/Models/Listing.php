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

    /** internal = our advertisers and agency feeds; gov = public-sector offers (state property register). Never imported. */
    public const PUBLIC_SOURCES = ['internal', 'gov'];

    public const STATUSES = ['draft', 'pending', 'active', 'expired', 'rejected'];

    protected $guarded = ['id', 'user_id', 'status', 'source', 'views', 'published_at', 'expires_at', 'moderation_note', 'slug', 'boosted_until', 'renewed_at'];

    protected function casts(): array
    {
        return [
            'area_m2' => 'float', 'lat' => 'float', 'lng' => 'float',
            'price_halere' => 'integer', 'fees_halere' => 'integer', 'deposit_halere' => 'integer',
            'floor' => 'integer',
            'has_elevator' => 'boolean', 'has_balcony' => 'boolean', 'has_parking' => 'boolean', 'pets_allowed' => 'boolean',
            'available_from' => 'date', 'published_at' => 'datetime', 'expires_at' => 'datetime',
            'boosted_until' => 'datetime', 'renewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $l) {
            $l->slug ??= Str::lower(Str::random(10));
        });
        $flush = fn () => \App\Services\MarketStats::flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function photos(): HasMany { return $this->hasMany(ListingPhoto::class)->orderBy('position'); }
    public function reports(): HasMany { return $this->hasMany(Report::class); }
    public function stats(): HasMany { return $this->hasMany(ListingStat::class); }
    public function slots(): HasMany { return $this->hasMany(ViewingSlot::class)->orderBy('starts_at'); }
    public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function favorites(): HasMany { return $this->hasMany(Favorite::class); }

    public function isBoosted(): bool { return $this->boosted_until?->isFuture() ?? false; }

    public function pricePerM2(): ?int
    {
        return $this->price_halere && $this->area_m2 > 0 ? (int) round($this->price_halere / 100 / $this->area_m2) : null;
    }

    /** Monthly cost for rent: rent + fees. */
    public function monthlyCostHalere(): ?int
    {
        return $this->offer_type === 'rent' && $this->price_halere !== null ? $this->price_halere + (int) $this->fees_halere : null;
    }

    public function isGov(): bool { return $this->source === 'gov'; }

    /** Only internal/gov, active, non-expired listings are ever shown publicly. */
    public function scopePublic(Builder $q): Builder
    {
        return $q->whereIn('source', self::PUBLIC_SOURCES)
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeImported(Builder $q): Builder { return $q->where('source', 'imported'); }

    public function isPublic(): bool
    {
        return in_array($this->source, self::PUBLIC_SOURCES, true) && $this->status === 'active' && $this->published_at
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
          ->when($f['furnishing'] ?? null, fn ($q, $v) => $q->where('furnishing', $v))
          ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('description', 'like', "%$v%")->orWhere('street', 'like', "%$v%")))
          ->when($f['floor_min'] ?? null, fn ($q, $v) => $q->where('floor', '>=', (int) $v))
          ->when($f['available_by'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->whereNull('available_from')->orWhere('available_from', '<=', $v)))
          ->when($f['bbox'] ?? null, function ($q, $v) {
              [$w, $s, $e, $n] = array_map('floatval', explode(',', $v));
              $q->whereBetween('lat', [min($s, $n), max($s, $n)])->whereBetween('lng', [min($w, $e), max($w, $e)]);
          })
          ->when($f['poly'] ?? null, function ($q, $v) {
              $poly = self::parsePolygon($v);
              if (count($poly) < 3) {
                  return;
              }
              $lats = array_column($poly, 1);
              $lngs = array_column($poly, 0);
              $ids = (clone $q)->whereBetween('lat', [min($lats), max($lats)])->whereBetween('lng', [min($lngs), max($lngs)])
                  ->limit(5000)->get(['id', 'lat', 'lng'])
                  ->filter(fn ($l) => self::pointInPolygon((float) $l->lng, (float) $l->lat, $poly))->pluck('id');
              $q->whereIn('id', $ids);
          });
        foreach (['has_balcony', 'has_elevator', 'has_parking', 'pets_allowed'] as $flag) {
            if (! empty($f[$flag])) {
                $q->where($flag, true);
            }
        }

        if (($f['sort'] ?? 'newest') === 'newest') {
            $q->orderByRaw('CASE WHEN boosted_until IS NOT NULL AND boosted_until > ? THEN 1 ELSE 0 END DESC', [now()]);
        }

        return match ($f['sort'] ?? 'newest') {
            'price_asc' => $q->orderBy('price_halere'),
            'price_desc' => $q->orderByDesc('price_halere'),
            'area_desc' => $q->orderByDesc('area_m2'),
            'ppm2_asc' => $q->whereNotNull('area_m2')->where('area_m2', '>', 0)->orderByRaw('price_halere / area_m2 ASC'),
            default => $q->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /** "lng lat;lng lat;..." -> [[lng, lat], ...] (max 50 vertices). */
    public static function parsePolygon(string $v): array
    {
        return collect(explode(';', $v))->take(50)->map(fn ($p) => array_map('floatval', preg_split('/[\s,]+/', trim($p))))
            ->filter(fn ($p) => count($p) === 2 && abs($p[0]) <= 180 && abs($p[1]) <= 90)->values()->all();
    }

    public static function pointInPolygon(float $x, float $y, array $poly): bool
    {
        $in = false;
        for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
            [$xi, $yi] = $poly[$i];
            [$xj, $yj] = $poly[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $in = ! $in;
            }
        }

        return $in;
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
