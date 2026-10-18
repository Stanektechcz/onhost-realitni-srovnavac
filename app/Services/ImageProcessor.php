<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Resizes uploads with GD and stores WebP (+ AVIF when the GD build supports it). Strips EXIF by re-encoding. */
class ImageProcessor
{
    /** @return array{path:string,thumb_path:string,avif_path:?string,width:int,height:int} */
    public function store(UploadedFile $file, string $dir): array
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw new RuntimeException('Nepodporovaný obrázek.');
        }
        $src = $this->orient($src, $file);
        $cfg = config('reality.photo');
        $name = Str::random(24);
        $disk = Storage::disk('public');

        $full = $this->scale($src, $cfg['max_width']);
        $thumb = $this->scale($src, $cfg['thumb_width']);
        $out = [
            'path' => "$dir/$name.webp",
            'thumb_path' => "$dir/{$name}_t.webp",
            'avif_path' => null,
            'width' => imagesx($full),
            'height' => imagesy($full),
        ];
        $disk->put($out['path'], $this->encode($full, 'webp', $cfg['quality']));
        $disk->put($out['thumb_path'], $this->encode($thumb, 'webp', $cfg['quality']));
        if (function_exists('imageavif')) {
            $out['avif_path'] = "$dir/$name.avif";
            $disk->put($out['avif_path'], $this->encode($full, 'avif', 60));
        }

        return $out;
    }

    private function scale(\GdImage $img, int $maxW): \GdImage
    {
        $w = imagesx($img);
        if ($w <= $maxW) {
            return $img;
        }

        return imagescale($img, $maxW, -1, IMG_BICUBIC);
    }

    private function encode(\GdImage $img, string $fmt, int $q): string
    {
        ob_start();
        $fmt === 'avif' ? imageavif($img, null, $q) : imagewebp($img, null, $q);

        return (string) ob_get_clean();
    }

    private function orient(\GdImage $img, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg'], true)) {
            return $img;
        }
        $o = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        return match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    }
}
