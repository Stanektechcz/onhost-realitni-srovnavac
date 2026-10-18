<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ListingPhoto extends Model
{
    protected $fillable = ['path', 'thumb_path', 'avif_path', 'width', 'height', 'position', 'source_url', 'source_hash'];

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }

    public function url(): string { return Storage::disk('public')->url($this->path); }
    public function thumbUrl(): string { return Storage::disk('public')->url($this->thumb_path); }
    public function avifUrl(): ?string { return $this->avif_path ? Storage::disk('public')->url($this->avif_path) : null; }
}
