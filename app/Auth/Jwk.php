<?php

namespace App\Auth;

use RuntimeException;

/** Minimal JWK → PEM conversion and ECDSA signature format helpers (openssl only). */
final class Jwk
{
    public static function b64urlDecode(string $data): string
    {
        $r = base64_decode(strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4), true);
        if ($r === false) {
            throw new RuntimeException('Invalid base64url.');
        }

        return $r;
    }

    public static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function toPem(array $jwk): string
    {
        $kty = $jwk['kty'] ?? null;
        if ($kty === 'RSA') {
            $n = self::b64urlDecode((string) ($jwk['n'] ?? ''));
            $e = self::b64urlDecode((string) ($jwk['e'] ?? ''));
            if ($n === '' || $e === '') {
                throw new RuntimeException('Invalid RSA JWK.');
            }
            $rsaKey = self::der(0x30, self::derInt($n).self::derInt($e));
            $algo = self::der(0x30, hex2bin('06092a864886f70d010101').hex2bin('0500'));
            $spki = self::der(0x30, $algo.self::der(0x03, "\x00".$rsaKey));
        } elseif ($kty === 'EC') {
            if (($jwk['crv'] ?? null) !== 'P-256') {
                throw new RuntimeException('Unsupported curve.');
            }
            $x = self::b64urlDecode((string) ($jwk['x'] ?? ''));
            $y = self::b64urlDecode((string) ($jwk['y'] ?? ''));
            if (strlen($x) !== 32 || strlen($y) !== 32) {
                throw new RuntimeException('Invalid EC JWK.');
            }
            $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200')."\x04".$x.$y;
        } else {
            throw new RuntimeException('Unsupported key type.');
        }

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    /** JWS raw r||s (64 bytes) → DER ECDSA-Sig-Value. */
    public static function rawToDer(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new RuntimeException('Invalid ES256 signature length.');
        }

        return self::der(0x30, self::derInt(substr($raw, 0, 32)).self::derInt(substr($raw, 32)));
    }

    /** DER ECDSA-Sig-Value → JWS raw r||s (64 bytes). */
    public static function derToRaw(string $der): string
    {
        $pos = 0;
        if (ord($der[$pos++] ?? "\0") !== 0x30) {
            throw new RuntimeException('Invalid DER.');
        }
        self::readLen($der, $pos);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$pos++] ?? "\0") !== 0x02) {
                throw new RuntimeException('Invalid DER.');
            }
            $len = self::readLen($der, $pos);
            $int = ltrim(substr($der, $pos, $len), "\x00");
            $pos += $len;
            if (strlen($int) > 32) {
                throw new RuntimeException('Invalid DER.');
            }
            $out .= str_pad($int, 32, "\x00", STR_PAD_LEFT);
        }

        return $out;
    }

    private static function readLen(string $der, int &$pos): int
    {
        $len = ord($der[$pos++] ?? "\0");
        if ($len & 0x80) {
            $bytes = $len & 0x7f;
            $len = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $len = ($len << 8) | ord($der[$pos++] ?? "\0");
            }
        }

        return $len;
    }

    private static function derInt(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return self::der(0x02, $bytes);
    }

    private static function der(int $tag, string $content): string
    {
        $len = strlen($content);
        if ($len < 0x80) {
            $l = chr($len);
        } else {
            $b = ltrim(pack('N', $len), "\x00");
            $l = chr(0x80 | strlen($b)).$b;
        }

        return chr($tag).$l.$content;
    }
}
