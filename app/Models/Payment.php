<?php

namespace App\Models;

use App\Support\Operator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = ['user_id', 'service', 'provider', 'provider_ref', 'amount_halere', 'commission_halere', 'currency', 'status', 'meta', 'seller_snapshot'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'seller_snapshot' => 'array'];
    }

    /** Kontrakt onhost-operator/1: prodávající se zamrazí při vzniku platby; pozdější změna provozovatele ho nepřepíše. */
    protected static function booted(): void
    {
        static::creating(function (Payment $p): void {
            $p->seller_snapshot ??= Operator::snapshot();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
