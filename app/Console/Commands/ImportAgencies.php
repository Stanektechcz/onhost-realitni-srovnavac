<?php

namespace App\Console\Commands;

use App\Models\Agency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Imports Czech real-estate agencies from the public ARES REST API (business register open data),
 * filtered by CZ-NACE 68310 (realitní zprostředkování). Idempotent upsert by IČO.
 * For natural persons (podnikající fyzické osoby) only the business name, IČO, municipality and region are
 * stored — not the street address of the registered seat, which is usually a home address.
 */
class ImportAgencies extends Command
{
    protected $signature = 'reality:agencies:import {--max= : Maximální počet záznamů} {--kraj= : Kód kraje (ARES kodKraje)}';

    protected $description = 'Načte realitní kanceláře z veřejných dat ARES (CZ-NACE 68310)';

    public function handle(): int
    {
        $cfg = config('reality.ares');
        $max = (int) ($this->option('max') ?: $cfg['max_records']);
        $size = (int) $cfg['page_size'];
        $created = $updated = $skipped = 0;

        for ($start = 0; $start < $max; $start += $size) {
            if ($start > 0 && $cfg['delay_ms'] > 0) {
                usleep($cfg['delay_ms'] * 1000); // be polite to the public API
            }
            $body = ['start' => $start, 'pocet' => min($size, $max - $start), 'czNace' => $cfg['nace']];
            if ($this->option('kraj')) {
                $body['sidlo'] = ['kodKraje' => (int) $this->option('kraj')];
            }
            $res = Http::acceptJson()->timeout(30)->retry(3, 2000, throw: false)
                ->post(rtrim($cfg['base_url'], '/').'/ekonomicke-subjekty/vyhledat', $body);
            if (! $res->successful()) {
                $this->error('ARES odpověděl chybou '.$res->status().'.');

                return self::FAILURE;
            }
            $rows = $res->json('ekonomickeSubjekty') ?? [];
            foreach ($rows as $row) {
                $r = $this->upsert($row);
                $r === 'created' ? $created++ : ($r === 'updated' ? $updated++ : $skipped++);
            }
            if (count($rows) < $body['pocet'] || $start + $size >= (int) $res->json('pocetCelkem', 0)) {
                break;
            }
        }
        $this->info("Kanceláře: nových {$created}, aktualizovaných {$updated}, přeskočeno {$skipped}.");

        return self::SUCCESS;
    }

    private function upsert(array $row): string
    {
        $ico = str_pad(preg_replace('/\D/', '', (string) ($row['ico'] ?? '')), 8, '0', STR_PAD_LEFT);
        $name = trim((string) ($row['obchodniJmeno'] ?? ''));
        if ($ico === '00000000' || $name === '' || ! empty($row['datumZaniku'])) {
            return 'skipped';
        }
        $form = (string) ($row['pravniForma'] ?? '');
        $natural = (bool) preg_match('/^10[1-8]$/', $form); // 101–108: fyzická osoba podnikající
        $seat = $row['sidlo'] ?? [];
        $data = [
            'name' => mb_substr($name, 0, 255),
            'legal_form' => $form ?: null,
            'natural_person' => $natural,
            'address' => $natural ? null : (isset($seat['textovaAdresa']) ? mb_substr($seat['textovaAdresa'], 0, 255) : null),
            'city' => $seat['nazevObce'] ?? null,
            'region' => $seat['nazevKraje'] ?? null,
            'licence' => 'Realitní zprostředkování (CZ-NACE 68310), živnostenské oprávnění dle ARES',
            'founded_on' => $row['datumVzniku'] ?? null,
            'ares_synced_at' => now(),
        ];
        $agency = Agency::where('ico', $ico)->first();
        if ($agency) {
            $agency->fill($data)->save();

            return 'updated';
        }
        Agency::create($data + ['ico' => $ico, 'source' => 'ares']);

        return 'created';
    }
}
