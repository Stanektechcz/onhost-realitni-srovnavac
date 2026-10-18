<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Imports public-sector sale offers from the Rejstřík nepotřebného nemovitého majetku (portal.gov.cz),
 * published by state organisations under zákon 106/1999. Shown publicly as source=gov with a link back
 * to the official record; contact persons are not copied, the visitor contacts the office at the source.
 */
class FetchGovProperty extends Command
{
    public const URL = 'https://portal.gov.cz/nepotrebny-nemovity-majetek/?export=csv';

    public const SOURCE = 'gov:nepotrebny-majetek';

    protected $signature = 'reality:gov:fetch {--file= : Místo stahování načíst CSV ze souboru}';

    protected $description = 'Načte nabídky nepotřebného majetku státu (portal.gov.cz) jako veřejné inzeráty source=gov';

    public function handle(): int
    {
        $csv = $this->option('file') ? @file_get_contents($this->option('file')) : $this->download();
        if (! is_string($csv) || ! str_starts_with(ltrim($csv, "\xEF\xBB\xBF"), 'ID_ZAZNAMU')) {
            $this->error('CSV se nepodařilo načíst nebo má neočekávaný formát.');

            return self::FAILURE;
        }
        $rows = $this->parse(ltrim($csv, "\xEF\xBB\xBF"));
        $seen = [];
        $created = $updated = 0;
        foreach ($rows as $r) {
            $id = trim($r['ID_ZAZNAMU'] ?? '');
            $validTo = $this->date($r['PLATNOST_DO'] ?? null);
            if ($id === '' || ($validTo && $validTo->isPast())) {
                continue;
            }
            $seen[] = $id;
            $listing = Listing::firstOrNew(['source_name' => self::SOURCE, 'source_id' => $id]);
            $exists = $listing->exists;
            $listing->forceFill($this->attributes($r) + [
                'source' => 'gov', 'status' => 'active', 'user_id' => null,
                'source_url' => 'https://portal.gov.cz/nepotrebny-nemovity-majetek/'.rawurlencode($id),
                'published_at' => $listing->published_at ?? ($this->date($r['PUBLIKOVANO'] ?? null) ?? now()),
                'expires_at' => $validTo?->endOfDay(),
            ])->save();
            $exists ? $updated++ : $created++;
        }
        $withdrawn = Listing::where('source_name', self::SOURCE)->where('status', 'active')->whereNotIn('source_id', $seen)
            ->update(['status' => 'expired', 'expires_at' => now()]);
        AuditLog::record('gov.fetch', null, compact('created', 'updated', 'withdrawn'));
        $this->info("Hotovo: nových $created, aktualizovaných $updated, staženo $withdrawn.");

        return self::SUCCESS;
    }

    private function download(): ?string
    {
        $res = Http::timeout(60)->withUserAgent('reality.onhost.cz (open data import)')->get(self::URL);

        return $res->successful() ? $res->body() : null;
    }

    private function parse(string $csv): array
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $csv);
        rewind($fh);
        $head = fgetcsv($fh, escape: '');
        $out = [];
        while (($row = fgetcsv($fh, escape: '')) !== false) {
            if (count($row) === count($head)) {
                $out[] = array_combine($head, $row);
            }
        }
        fclose($fh);

        return $out;
    }

    private function attributes(array $r): array
    {
        $type = ($r['TYP_BUDOVA'] ?? '') === 'ano' ? 'house' : (($r['TYP_JEDNOTKA'] ?? '') === 'ano' ? 'flat' : (($r['TYP_POZEMEK'] ?? '') === 'ano' ? 'land' : 'commercial'));
        $ku = array_map('trim', explode(',', (string) ($r['KATASTRALNI_UZEMI'] ?? '')));
        $city = $ku[1] ?? $ku[0] ?? null;
        $price = preg_replace('/\D/', '', (string) ($r['POZADOVANA_CENA'] ?? ''));
        $parts = array_filter([
            $r['POPIS'] ?? null,
            ($r['POPIS_POLOZKY'] ?? '') !== ($r['POPIS'] ?? '') ? ($r['POPIS_POLOZKY'] ?? null) : null,
            ! empty($r['PARCELNI_CISLO']) ? 'Parcela: '.$r['PARCELNI_CISLO'].' (k. ú. '.($ku[0] ?? '').')' : null,
            'Nabízí: '.trim(($r['PUBLIKUJCI'] ?? '').(! empty($r['NABIZEJICI_ZAKLADATEL']) ? ', zřizovatel '.$r['NABIZEJICI_ZAKLADATEL'] : '')),
            'Podmínky prodeje, prohlídky a kontakt jsou uvedeny u záznamu v Rejstříku nepotřebného nemovitého majetku.',
        ]);

        return [
            'title' => Str::limit(trim((string) ($r['POPIS'] ?? 'Nabídka státního majetku')), 155),
            'description' => implode("\n\n", array_map('trim', $parts)),
            'offer_type' => 'sale', 'property_type' => $type,
            'price_halere' => $price !== '' ? (int) $price * 100 : null,
            'city' => $city ? Str::limit($city, 95, '') : null,
            'district' => isset($ku[1]) ? Str::limit($ku[0], 95, '') : null,
            'street' => ! empty($r['ULICE']) ? Str::limit(trim($r['ULICE'].' '.($r['CP_CEV'] ?? '')), 145, '') : null,
            'penb' => 'none',
        ];
    }

    private function date(?string $v): ?Carbon
    {
        try {
            return $v ? Carbon::parse($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
