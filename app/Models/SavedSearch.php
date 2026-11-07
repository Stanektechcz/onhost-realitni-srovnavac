<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $fillable = ['name', 'filters', 'last_notified_at', 'notify'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'last_notified_at' => 'datetime', 'notify' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
