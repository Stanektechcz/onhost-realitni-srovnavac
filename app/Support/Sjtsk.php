<?php

namespace App\Support;

/**
 * WGS84 (EPSG:4326) -> S-JTSK / Křovák East North (EPSG:5514) conversion.
 * Helmert 7-parameter shift to Bessel 1841 + Křovák conic projection; accuracy ~1 m, enough for deep links
 * into ČÚZK "Nahlížení do katastru nemovitostí". Pure math, no network calls.
 */
class Sjtsk
{
    /** @return array{x: float, y: float} EPSG:5514 coordinates (both negative inside CZ, x = -Y, y = -X). */
    public static function fromWgs84(float $lat, float $lng, float $h = 300.0): array
    {
        // WGS84 geodetic -> geocentric
        $a = 6378137.0;
        $f = 1 / 298.257223563;
        $e2 = 1 - (1 - $f) ** 2;
        $B = deg2rad($lat);
        $L = deg2rad($lng);
        $N = $a / sqrt(1 - $e2 * sin($B) ** 2);
        $x = ($N + $h) * cos($B) * cos($L);
        $y = ($N + $h) * cos($B) * sin($L);
        $z = ($N * (1 - $e2) + $h) * sin($B);

        // WGS84 -> Bessel (S-JTSK) Helmert transformation
        $dx = -570.69; $dy = -85.69; $dz = -462.84;
        $wx = deg2rad(4.99821 / 3600); $wy = deg2rad(1.58676 / 3600); $wz = deg2rad(5.2611 / 3600);
        $m = -3.543e-6;
        $xn = $dx + (1 + $m) * ($x + $wz * $y - $wy * $z);
        $yn = $dy + (1 + $m) * (-$wz * $x + $y + $wx * $z);
        $zn = $dz + (1 + $m) * ($wy * $x - $wx * $y + $z);

        // geocentric -> Bessel geodetic
        $a = 6377397.15508;
        $f = 1 / 299.152812853;
        $e2 = 1 - (1 - $f) ** 2;
        $p = sqrt($xn ** 2 + $yn ** 2);
        $B = atan2($zn, $p * (1 - $e2));
        for ($i = 0; $i < 10; $i++) {
            $N = $a / sqrt(1 - $e2 * sin($B) ** 2);
            $hh = $p / cos($B) - $N;
            $B = atan2($zn, $p * (1 - $e2 * $N / ($N + $hh)));
        }
        $L = atan2($yn, $xn);

        // Křovák projection (EPSG method 9819)
        $e = sqrt($e2);
        $phiC = deg2rad(49.5);
        $lam0 = deg2rad(24 + 50 / 60);
        $alphaC = deg2rad(30.28813972222222);
        $phiP = deg2rad(78.5);
        $kP = 0.9999;
        $A = $a * sqrt(1 - $e2) / (1 - $e2 * sin($phiC) ** 2);
        $Bk = sqrt(1 + $e2 * cos($phiC) ** 4 / (1 - $e2));
        $g0 = asin(sin($phiC) / $Bk);
        $t0 = tan(M_PI / 4 + $g0 / 2) * ((1 + $e * sin($phiC)) / (1 - $e * sin($phiC))) ** ($e * $Bk / 2) / tan(M_PI / 4 + $phiC / 2) ** $Bk;
        $n = sin($phiP);
        $r0 = $kP * $A / tan($phiP);
        $U = 2 * (atan($t0 * tan($B / 2 + M_PI / 4) ** $Bk / ((1 + $e * sin($B)) / (1 - $e * sin($B))) ** ($e * $Bk / 2)) - M_PI / 4);
        $V = $Bk * ($lam0 - $L);
        $T = asin(cos($alphaC) * sin($U) + sin($alphaC) * cos($U) * cos($V));
        $D = asin(cos($U) * sin($V) / cos($T));
        $theta = $n * $D;
        $r = $r0 * tan(M_PI / 4 + $phiP / 2) ** $n / tan($T / 2 + M_PI / 4) ** $n;
        $X = $r * cos($theta);
        $Y = $r * sin($theta);

        return ['x' => -$Y, 'y' => -$X];
    }
}
