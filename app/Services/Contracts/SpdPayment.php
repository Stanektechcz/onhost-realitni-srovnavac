<?php

namespace App\Services\Contracts;

/** Czech "Short Payment Descriptor" (SPD 1.0, QR Platba) string builder + domestic account → IBAN. */
class SpdPayment
{
    /** Accepts "CZ65 0800 ..." IBAN or Czech "[prefix-]number/bank". Returns IBAN without spaces, or null when invalid. */
    public static function toIban(?string $account): ?string
    {
        $a = strtoupper(preg_replace('/\s+/', '', (string) $account));
        if (preg_match('/^CZ\d{22}$/', $a)) {
            return self::mod97(substr($a, 4).'1235'.substr($a, 2, 2)) === 1 ? $a : null;
        }
        if (! preg_match('/^(?:(\d{1,6})-)?(\d{2,10})\/(\d{4})$/', $a, $m)) {
            return null;
        }
        $bban = $m[3].str_pad($m[1] ?: '0', 6, '0', STR_PAD_LEFT).str_pad($m[2], 10, '0', STR_PAD_LEFT);
        $check = 98 - self::mod97($bban.'123500'); // "CZ" = 12 35, check digits 00

        return 'CZ'.str_pad((string) $check, 2, '0', STR_PAD_LEFT).$bban;
    }

    public static function string(string $iban, int $amountHalere, ?string $vs = null, ?string $message = null): string
    {
        $parts = ['SPD', '1.0', 'ACC:'.$iban, 'AM:'.number_format($amountHalere / 100, 2, '.', ''), 'CC:CZK'];
        if ($vs !== null && $vs !== '') {
            $parts[] = 'X-VS:'.substr(preg_replace('/\D/', '', $vs), 0, 10);
        }
        if ($message) {
            $msg = strtoupper(str_replace('*', '', \Illuminate\Support\Str::ascii($message)));
            $parts[] = 'MSG:'.mb_substr($msg, 0, 60);
        }

        return implode('*', $parts);
    }

    private static function mod97(string $digits): int
    {
        $r = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $r = (int) (($r.$chunk) % 97);
        }

        return $r;
    }
}
