<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\ImageProcessor;
use App\Services\SafeImageFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Syncs a feed listing's photos with the agency's ordered URL list; unchanged URLs are reused, not re-downloaded. */
class ImportFeedPhotos implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @param list<string> $urls */
    public function __construct(public int $listingId, public array $urls) {}

    /** Called from the feed request: dispatches only when the URL list differs from what is stored. */
    public static function dispatchIfChanged(Listing $listing, array $urls): void
    {
        $urls = array_values(array_unique(array_map('strval', $urls)));
        if (self::currentUrls($listing) !== $urls) {
            static::dispatch($listing->id, $urls);
        }
    }

    private static function currentUrls(Listing $listing): array
    {
        return $listing->photos()->whereNotNull('source_url')->orderBy('position')->pluck('source_url')->all();
    }

    public function handle(SafeImageFetcher $fetcher, ImageProcessor $images): void
    {
        $listing = Listing::find($this->listingId);
        if (! $listing || self::currentUrls($listing) === $this->urls) {
            return;
        }
        $existing = $listing->photos()->whereNotNull('source_url')->get()->keyBy('source_url');
        $keep = [];
        $pos = 0;
        foreach (array_slice($this->urls, 0, (int) config('reality.max_photos')) as $url) {
            if ($photo = $existing->get($url)) {
                $photo->update(['position' => ++$pos]);
                $keep[] = $photo->id;

                continue;
            }
            try {
                $img = $fetcher->fetch($url);
                $tmp = tempnam(sys_get_temp_dir(), 'feedimg');
                file_put_contents($tmp, $img['bytes']);
                try {
                    $stored = $images->store(new UploadedFile($tmp, 'feed', $img['mime'], null, true), 'listings/'.$listing->id);
                } finally {
                    @unlink($tmp);
                }
                $photo = $listing->photos()->create($stored + [
                    'position' => ++$pos, 'source_url' => $url, 'source_hash' => hash('sha256', $img['bytes']),
                ]);
                $keep[] = $photo->id;
            } catch (RuntimeException $e) {
                if ($e instanceof QueryException) {
                    throw $e;
                }
                // Only our own reason and the host; never the remote payload.
                Log::warning('feed.photo_rejected', ['listing' => $listing->id, 'host' => parse_url($url, PHP_URL_HOST), 'reason' => $e->getMessage()]);
            }
        }
        foreach ($existing->filter(fn ($p) => ! in_array($p->id, $keep, true)) as $old) {
            Storage::disk('public')->delete(array_filter([$old->path, $old->thumb_path, $old->avif_path]));
            $old->delete();
        }
    }
}
