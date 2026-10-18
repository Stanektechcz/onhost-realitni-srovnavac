<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = ['listing_id', 'reporter_id', 'reporter_email', 'reason', 'description'];

    protected function casts(): array { return ['decided_at' => 'datetime']; }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
}
