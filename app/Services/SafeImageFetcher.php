<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Downloads a remote image without SSRF exposure: https on port 443 only, every resolved address must be public,
 * the connection is pinned to the validated IP (CURLOPT_RESOLVE), redirects are not followed, 15 s timeout,
 * size capped at reality.photo.max_kb and the body must be a real JPEG/PNG/WebP meeting the upload dimensions.
 */
class SafeImageFetcher
{
    public const MIN_WIDTH = 400;

    public const MIN_HEIGHT = 300;

    private const BLOCKED_CIDRS = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001::/32', '2001:db8::/32', '2002::/16',
        'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    public function __construct(private HostResolver $resolver) {}

    /** @return array{bytes:string,mime:string,width:int,height:int} */
    public function fetch(string $url): array
    {
        [$host, $ip] = $this->validateUrl($url);
        $maxBytes = (int) config('reality.photo.max_kb') * 1024;
        $pin = $host.':443:'.(str_contains($ip, ':') ? '['.$ip.']' : $ip);

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'timeout' => 15,
                'connect_timeout' => 5,
                'curl' => [CURLOPT_RESOLVE => [$pin], CURLOPT_PROTOCOLS => CURLPROTO_HTTPS],
                'on_headers' => function ($res) use ($maxBytes) {
                    if ((int) $res->getHeaderLine('Content-Length') > $maxBytes) {
                        throw new RuntimeException('too large');
                    }
                },
                'progress' => function ($total, $downloaded) use ($maxBytes) {
                    if ($total > $maxBytes || $downloaded > $maxBytes) {
                        throw new RuntimeException('too large');
                    }
                },
            ])->withHeaders(['Accept' => 'image/jpeg,image/png,image/webp'])->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException(str_contains($e->getMessage(), 'too large') ? 'Soubor je příliš velký.' : 'Stažení selhalo.');
        }

        if ($response->status() !== 200) {
            throw new RuntimeException('Neočekávaná odpověď serveru ('.$response->status().').');
        }
        $bytes = $response->body();
        if (strlen($bytes) > $maxBytes || (int) $response->header('Content-Length') > $maxBytes) {
            throw new RuntimeException('Soubor je příliš velký.');
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || ! @imagecreatefromstring($bytes)) {
            throw new RuntimeException('Soubor není podporovaný obrázek.');
        }
        if ($info[0] < self::MIN_WIDTH || $info[1] < self::MIN_HEIGHT) {
            throw new RuntimeException('Obrázek je příliš malý.');
        }

        return ['bytes' => $bytes, 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1]];
    }

    /** @return array{0:string,1:string} host and the validated IP to pin */
    public function validateUrl(string $url): array
    {
        $p = parse_url($url);
        if (! $p || strtolower($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            throw new RuntimeException('Povoleny jsou jen adresy https://.');
        }
        if (isset($p['port']) && (int) $p['port'] !== 443) {
            throw new RuntimeException('Povolen je jen port 443.');
        }
        $host = strtolower(trim($p['host'], '[]'));
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolver->resolve($host);
        if ($ips === []) {
            throw new RuntimeException('Adresu nelze přeložit.');
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new RuntimeException('Adresa míří do neveřejné sítě.');
            }
        }

        return [$host, $ips[0]];
    }

    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $bin = inet_pton($ip);
        foreach (self::BLOCKED_CIDRS as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $nbin = inet_pton($net);
            if (strlen($nbin) !== strlen($bin)) {
                continue;
            }
            $bytes = intdiv((int) $bits, 8);
            $rem = (int) $bits % 8;
            if (substr($bin, 0, $bytes) !== substr($nbin, 0, $bytes)) {
                continue;
            }
            if ($rem === 0 || ((ord($bin[$bytes]) ^ ord($nbin[$bytes])) & (0xFF << (8 - $rem)) & 0xFF) === 0) {
                return false;
            }
        }

        return true;
    }
}
