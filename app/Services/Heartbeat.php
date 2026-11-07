<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Heartbeat plánovače: reality:maintenance zapisuje čas posledního běhu. */
class Heartbeat
{
    public const KEY = 'reality:maintenance:heartbeat';

    public function beat(): void
    {
        Cache::forever(self::KEY, now()->toIso8601String());
    }

    public function last(): ?CarbonImmutable
    {
        $v = Cache::get(self::KEY);

        return $v ? CarbonImmutable::parse($v) : null;
    }

    public function stale(): bool
    {
        $last = $this->last();

        return ! $last || $last->lt(now()->subHours((int) config('reality.automation.heartbeat_max_hours', 48)));
    }
}
