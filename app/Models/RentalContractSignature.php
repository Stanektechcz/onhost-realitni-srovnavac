<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Simple electronic signature (eIDAS "prostý elektronický podpis") with its audit evidence. */
class RentalContractSignature extends Model
{
    protected $fillable = ['user_id', 'role', 'typed_name', 'version', 'text_sha256', 'ip', 'user_agent', 'signed_at'];

    protected function casts(): array { return ['signed_at' => 'datetime', 'invalidated_at' => 'datetime', 'version' => 'integer']; }

    public function contract(): BelongsTo { return $this->belongsTo(RentalContract::class, 'rental_contract_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
