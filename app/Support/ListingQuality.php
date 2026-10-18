<?php

namespace App\Support;

use App\Models\Listing;

/** Completeness score 0–100 with actionable tips. */
class ListingQuality
{
    public static function check(Listing $l): array
    {
        $photos = $l->relationLoaded('photos') ? $l->photos->count() : $l->photos()->count();
        $checks = [
            ['Alespoň 5 fotografií', 20, $photos >= 5],
            ['Alespoň 1 fotografie', 10, $photos >= 1],
            ['Popis delší než 400 znaků', 15, mb_strlen((string) $l->description) >= 400],
            ['Vyplněná plocha', 10, $l->area_m2 > 0],
            ['Uvedená dispozice', 5, filled($l->disposition)],
            ['Poloha na mapě', 10, $l->lat && $l->lng],
            ['Ulice a čtvrť', 5, filled($l->street) && filled($l->district)],
            ['Energetický štítek (PENB) vydán', 10, filled($l->penb) && $l->penb !== 'none'],
            ['Poplatky / kauce uvedeny', 5, $l->offer_type === 'sale' || $l->fees_halere !== null],
            ['Datum dostupnosti', 5, $l->available_from !== null],
            ['Patro a vybavení', 5, $l->floor !== null && filled($l->furnishing)],
        ];
        $score = 0;
        $tips = [];
        foreach ($checks as [$label, $pts, $ok]) {
            $ok ? $score += $pts : $tips[] = $label;
        }

        return ['score' => $score, 'checks' => $checks, 'tips' => $tips];
    }
}
