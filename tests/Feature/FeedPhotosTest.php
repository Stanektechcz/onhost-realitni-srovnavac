<?php

use App\Models\Listing;
use App\Services\HostResolver;
use App\Services\SafeImageFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function feedPhotoJpeg(int $w = 800, int $h = 600): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 120, 160, 200));
    ob_start();
    imagejpeg($img, null, 80);

    return (string) ob_get_clean();
}

function feedPhotoResolver(array $map): void
{
    app()->instance(HostResolver::class, new class($map) extends HostResolver
    {
        public function __construct(private array $map) {}

        public function resolve(string $host): array
        {
            return $this->map[$host] ?? [];
        }
    });
}

function feedPhotoPush(string $token, array $photos): void
{
    test()->withToken($token)->postJson('/api/feed/v1/listings', ['listings' => [feedRow(['photos' => $photos])]])->assertOk();
}

beforeEach(function () {
    Storage::fake('public');
    feedPhotoResolver(['img.example.com' => ['93.184.216.34'], 'evil.example.com' => ['10.0.0.5'], 'mixed.example.com' => ['93.184.216.34', '::1']]);
});

it('downloads feed photos in order and stores their source', function () {
    Http::fake(['https://img.example.com/*' => Http::response(feedPhotoJpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
    [, $token] = feedAgency();
    feedPhotoPush($token, ['https://img.example.com/b.jpg', 'https://img.example.com/a.jpg']);

    $photos = Listing::sole()->photos()->orderBy('position')->get();
    expect($photos->pluck('source_url')->all())->toBe(['https://img.example.com/b.jpg', 'https://img.example.com/a.jpg'])
        ->and($photos->pluck('position')->all())->toBe([1, 2])
        ->and($photos[0]->source_hash)->toHaveLength(64);
    Storage::disk('public')->assertExists($photos[0]->path);
});

it('does not re-download an unchanged list and reuses kept urls on reorder', function () {
    Http::fake(['https://img.example.com/*' => Http::response(feedPhotoJpeg(), 200)]);
    [, $token] = feedAgency();
    feedPhotoPush($token, ['https://img.example.com/1.jpg', 'https://img.example.com/2.jpg']);
    Http::assertSentCount(2);
    feedPhotoPush($token, ['https://img.example.com/1.jpg', 'https://img.example.com/2.jpg']);
    Http::assertSentCount(2);
    feedPhotoPush($token, ['https://img.example.com/2.jpg', 'https://img.example.com/3.jpg']);
    Http::assertSentCount(3);
    expect(Listing::sole()->photos()->orderBy('position')->pluck('source_url')->all())
        ->toBe(['https://img.example.com/2.jpg', 'https://img.example.com/3.jpg']);
});

it('rejects private and loopback targets without sending a request', function () {
    Http::fake();
    [, $token] = feedAgency();
    feedPhotoPush($token, ['https://evil.example.com/x.jpg', 'https://mixed.example.com/x.jpg', 'https://127.0.0.1/x.jpg', 'https://[::1]/x.jpg', 'https://169.254.169.254/x.jpg', 'https://img.example.com:8443/x.jpg']);
    Http::assertNothingSent();
    expect(Listing::sole()->photos()->count())->toBe(0);
});

it('classifies ip ranges', function (string $ip, bool $public) {
    expect(SafeImageFetcher::isPublicIp($ip))->toBe($public);
})->with([
    ['93.184.216.34', true], ['2606:2800:220:1::1', true], ['10.1.2.3', false], ['192.168.1.1', false], ['172.20.0.1', false],
    ['127.0.0.1', false], ['169.254.169.254', false], ['100.64.0.1', false], ['0.0.0.0', false], ['::1', false],
    ['fe80::1', false], ['fd00::1', false], ['::ffff:127.0.0.1', false], ['224.0.0.1', false],
]);

it('rejects http urls', function () {
    Http::fake();
    [, $token] = feedAgency();
    $this->withToken($token)->postJson('/api/feed/v1/listings', ['listings' => [feedRow(['photos' => ['http://img.example.com/a.jpg']])]])
        ->assertStatus(422);
    expect(fn () => app(SafeImageFetcher::class)->fetch('http://img.example.com/a.jpg'))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

it('rejects too large, non-image, too small and redirect responses', function () {
    config(['reality.photo.max_kb' => 50]);
    Http::fake([
        'https://img.example.com/big.jpg' => Http::response(str_repeat('a', 60 * 1024), 200),
        'https://img.example.com/text.jpg' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'image/jpeg']),
        'https://img.example.com/small.jpg' => Http::response(feedPhotoJpeg(100, 80), 200),
        'https://img.example.com/redir.jpg' => Http::response('', 302, ['Location' => 'https://127.0.0.1/']),
    ]);
    [, $token] = feedAgency();
    feedPhotoPush($token, ['https://img.example.com/big.jpg', 'https://img.example.com/text.jpg', 'https://img.example.com/small.jpg', 'https://img.example.com/redir.jpg']);
    expect(Listing::sole()->photos()->count())->toBe(0);
    expect(fn () => app(SafeImageFetcher::class)->fetch('https://img.example.com/big.jpg'))->toThrow(RuntimeException::class, 'příliš velký');
});
