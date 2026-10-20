<?php

namespace App\Auth;

use RuntimeException;

/** Minimální ověření JWS (RS256) proti JWKS – bez externí knihovny. */
class Jwt
{
    /** @return array<string, mixed> claims */
    public static function verifyRs256(string $jwt, array $jwks): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('malformed_jwt');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::b64d($h), true);
        $claims = json_decode(self::b64d($p), true);
        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256') {
            throw new RuntimeException('unsupported_alg');
        }
        $key = collect($jwks['keys'] ?? [])->first(fn ($k) => ($k['kty'] ?? null) === 'RSA'
            && (! isset($header['kid']) || ($k['kid'] ?? null) === $header['kid']) && ($k['use'] ?? 'sig') === 'sig');
        if (! $key) {
            throw new RuntimeException('unknown_key');
        }
        $ok = openssl_verify("$h.$p", self::b64d($s), self::jwkToPem($key['n'], $key['e']), OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new RuntimeException('bad_signature');
        }

        return $claims;
    }

    public static function b64d(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    public static function b64e(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** RSA JWK (n, e) → PEM SubjectPublicKeyInfo (ruční DER kódování). */
    public static function jwkToPem(string $n, string $e): string
    {
        $int = fn (string $bytes) => "\x02".self::len(strlen($bytes = (ord($bytes[0]) > 0x7F ? "\x00".$bytes : $bytes))).$bytes;
        $rsa = $int(self::b64d($n)).$int(self::b64d($e));
        $rsa = "\x30".self::len(strlen($rsa)).$rsa;
        $bitString = "\x03".self::len(strlen($rsa) + 1)."\x00".$rsa;
        $algo = "\x30\x0D\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00";
        $spki = "\x30".self::len(strlen($algo.$bitString)).$algo.$bitString;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function len(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
