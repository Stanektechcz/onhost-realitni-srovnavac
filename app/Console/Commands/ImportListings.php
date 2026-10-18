<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Imports scraper output (JSON Lines). Stored as source=imported — used ONLY for internal
 * price statistics in admin, never displayed publicly (see Listing::scopePublic / ListingPolicy).
 */
class ImportListings extends Command
{
    protected $signature = 'reality:import {file : Cesta k .jsonl souboru}';

    protected $description = 'Import dat ze scraperu (JSONL) jako source=imported pro interní cenovou statistiku';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_readable($path)) {
            $this->error("Soubor nelze číst: $path");

            return self::FAILURE;
        }
        $created = $updated = $skipped = 0;
        $fh = fopen($path, 'r');
        $line = 0;
        while (($raw = fgets($fh)) !== false) {
            $line++;
            if (trim($raw) === '') {
                continue;
            }
            $row = json_decode($raw, true);
            $v = Validator::make(is_array($row) ? $row : [], [
                'source' => ['required', 'string', 'max:60'],
                'source_id' => ['required', 'max:190'],
                'url' => ['nullable', 'string', 'max:1000'],
                'offer_type' => ['required', Rule::in(['rent', 'sale'])],
                'disposition' => ['nullable', 'string', 'max:10'],
                'area_m2' => ['nullable', 'numeric', 'min:0'],
                'price_czk' => ['nullable', 'numeric', 'min:0'],
                'fees_czk' => ['nullable', 'numeric', 'min:0'],
                'city' => ['nullable', 'string', 'max:100'],
                'district' => ['nullable', 'string', 'max:100'],
                'lat' => ['nullable', 'numeric', 'between:-90,90'],
                'lng' => ['nullable', 'numeric', 'between:-180,180'],
                'penb' => ['nullable', 'string', 'max:10'],
                'published_at' => ['nullable', 'date'],
            ]);
            if ($v->fails()) {
                $skipped++;
                $this->warn("Řádek $line přeskočen: ".implode('; ', $v->errors()->all()));

                continue;
            }
            $d = $v->validated();
            $penb = strtoupper((string) ($d['penb'] ?? ''));
            $listing = Listing::firstOrNew(['source_name' => $d['source'], 'source_id' => (string) $d['source_id']]);
            $exists = $listing->exists;
            $listing->forceFill([
                'source' => 'imported',
                'status' => 'active', // status is irrelevant: scopePublic() excludes source=imported
                'source_url' => $d['url'] ?? null,
                'offer_type' => $d['offer_type'],
                'property_type' => 'flat',
                'disposition' => $d['disposition'] ?? null,
                'area_m2' => $d['area_m2'] ?? null,
                'price_halere' => isset($d['price_czk']) ? (int) round($d['price_czk'] * 100) : null,
                'fees_halere' => isset($d['fees_czk']) ? (int) round($d['fees_czk'] * 100) : null,
                'city' => $d['city'] ?? null,
                'district' => $d['district'] ?? null,
                'lat' => $d['lat'] ?? null,
                'lng' => $d['lng'] ?? null,
                'penb' => in_array($penb, ['A', 'B', 'C', 'D', 'E', 'F', 'G'], true) ? $penb : null,
                'published_at' => isset($d['published_at']) ? Carbon::parse($d['published_at']) : null,
            ])->save();
            $exists ? $updated++ : $created++;
        }
        fclose($fh);
        AuditLog::record('import.run', null, compact('path', 'created', 'updated', 'skipped'));
        $this->info("Hotovo: nových $created, aktualizovaných $updated, přeskočených $skipped.");

        return self::SUCCESS;
    }
}
