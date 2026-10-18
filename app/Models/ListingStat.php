<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ListingStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['listing_id', 'day', 'views', 'saves', 'inquiries', 'bookings'];

    protected function casts(): array { return ['day' => 'date']; }

    public const COUNTERS = ['views', 'saves', 'inquiries', 'bookings'];

    /** Increment a daily counter; portable across MariaDB and SQLite. */
    public static function bump(Listing $listing, string $counter): void
    {
        abort_unless(in_array($counter, self::COUNTERS, true), 500);
        $day = now()->toDateString();
        $updated = static::where('listing_id', $listing->id)->whereDate('day', $day)->increment($counter);
        if (! $updated) {
            try {
                static::create(['listing_id' => $listing->id, 'day' => $day, $counter => 1]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                static::where('listing_id', $listing->id)->whereDate('day', $day)->increment($counter);
            }
        }
    }

    /** Last N days as [Y-m-d => [counters]] with zero-filled gaps. */
    public static function series(Listing $listing, int $days = 30): array
    {
        $rows = static::where('listing_id', $listing->id)->where('day', '>=', now()->subDays($days - 1)->toDateString())
            ->get()->keyBy(fn ($r) => $r->day->toDateString());
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $out[$d] = collect(self::COUNTERS)->mapWithKeys(fn ($c) => [$c => (int) ($rows[$d]->$c ?? 0)])->all();
        }

        return $out;
    }
}
