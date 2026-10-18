<?php

namespace App\Services;

/** DNS lookup (A + AAAA) behind a seam so tests can swap it. */
class HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        $ips = [];
        foreach ([DNS_A, DNS_AAAA] as $type) {
            foreach (@dns_get_record($host, $type) ?: [] as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
            }
        }
        if (! array_filter($ips)) {
            $ips = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique(array_filter($ips)));
    }
}
