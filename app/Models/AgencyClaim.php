<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyClaim extends Model
{
    protected $fillable = ['message'];

    protected function casts(): array { return ['decided_at' => 'datetime']; }

    public function agency(): BelongsTo { return $this->belongsTo(Agency::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Grants the claim: the user's account becomes the agency's verified feed account. */
    public function approve(string $method): void
    {
        $this->forceFill(['status' => 'approved', 'method' => $method, 'decided_at' => now()])->save();
        $this->agency->forceFill(['claimed_by_user_id' => $this->user_id, 'verified_at' => now(), 'source' => 'claimed'])->save();
        $this->agency->claims()->where('id', '!=', $this->id)->where('status', 'pending')
            ->update(['status' => 'rejected', 'decided_at' => now()]);
        $this->agency->recalcRating();
    }
}
