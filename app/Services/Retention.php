<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;

/**
 * GDPR minimalizace: vypršelé inzeráty starší N let se anonymizují (zůstanou jen agregovatelné údaje
 * pro cenové statistiky), zprávy k inzerátům starší N let se mažou.
 */
class Retention
{
    /** @return array{anonymized:int, messages:int} */
    public function run(): array
    {
        if (! config('reality.retention.enabled')) {
            return ['anonymized' => 0, 'messages' => 0];
        }
        $cutoff = now()->subYears(max(1, (int) config('reality.retention.years', 2)));
        $anonymized = 0;
        Listing::where('source', 'internal')->where('status', 'expired')->whereNull('anonymized_at')
            ->where(fn ($q) => $q->where('expires_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('expires_at')->where('updated_at', '<', $cutoff)))
            ->with('photos')->chunkById(100, function ($listings) use (&$anonymized) {
                foreach ($listings as $l) {
                    $this->anonymize($l);
                    $anonymized++;
                }
            });
        $messages = Message::where('created_at', '<', $cutoff)->delete();
        if ($anonymized || $messages) {
            AuditLog::record('retention.run', null, ['anonymized' => $anonymized, 'messages' => $messages]);
        }

        return ['anonymized' => $anonymized, 'messages' => $messages];
    }

    public function anonymize(Listing $l): void
    {
        $disk = Storage::disk('public');
        foreach ($l->photos as $p) {
            $disk->delete(array_filter([$p->path, $p->thumb_path, $p->avif_path]));
            $p->delete();
        }
        // Ponecháno: typ nabídky/nemovitosti, dispozice, plocha, cena, obec, část obce, data (pro statistiky).
        $l->forceFill([
            'user_id' => null, 'title' => null, 'description' => null, 'street' => null,
            'lat' => null, 'lng' => null, 'moderation_note' => null, 'anonymized_at' => now(),
        ])->saveQuietly();
    }
}
