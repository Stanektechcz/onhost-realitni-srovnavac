<?php

namespace App\Services\Ecosystem;

/**
 * Podpis zpráv sběrnice ONhost (kompatibilní s Onhost\SaasKit\Bus\SignatureVerifier):
 * `X-Onhost-Signature: t=<unix>,v1=<hex hmac-sha256 z "t.body">`, více tajemství při rotaci, okno 300 s.
 */
final class BusSignature
{
    public const HEADER = 'X-Onhost-Signature';

    /** @param list<string> $secrets */
    public function __construct(private readonly array $secrets, private readonly int $tolerance = 300) {}

    public static function sign(string $secret, string $body, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function verify(?string $header, string $body, ?int $now = null): bool
    {
        $secrets = array_values(array_filter($this->secrets, fn ($s) => is_string($s) && $s !== ''));
        if ($secrets === [] || $header === null || $header === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === [] || abs(($now ?? time()) - $timestamp) > $this->tolerance) {
            return false;
        }
        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
            foreach ($signatures as $sig) {
                if (hash_equals($expected, $sig)) {
                    return true;
                }
            }
        }

        return false;
    }
}
