<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * In-system residential lease (NOZ § 2235 a násl.).
 * Flow: draft → signed_landlord → sent → signed_both; cancelled / expired are terminal side exits.
 * Any edit of the terms invalidates all signatures and returns the contract to draft.
 */
class RentalContract extends Model
{
    public const STATUSES = ['draft', 'signed_landlord', 'sent', 'signed_both', 'cancelled', 'expired'];
    public const EDITABLE = ['draft', 'signed_landlord', 'sent'];

    protected $fillable = ['listing_id', 'landlord_id', 'tenant_id', 'tenant_email', 'data', 'rent_halere', 'fees_halere', 'deposit_halere'];

    protected function casts(): array
    {
        return ['data' => 'array', 'sent_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'rent_halere' => 'integer', 'fees_halere' => 'integer', 'deposit_halere' => 'integer', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (RentalContract $c) => $c->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function landlord(): BelongsTo { return $this->belongsTo(User::class, 'landlord_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(User::class, 'tenant_id'); }
    public function signatures(): HasMany { return $this->hasMany(RentalContractSignature::class)->orderBy('signed_at'); }
    public function validSignatures(): HasMany { return $this->signatures()->whereNull('invalidated_at'); }
    public function payments(): MorphMany { return $this->morphMany(Payment::class, 'payable'); }

    public function isEditable(): bool { return in_array($this->status, self::EDITABLE, true); }

    public function roleOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }
        if ($user->id === $this->landlord_id) {
            return 'landlord';
        }
        if ($this->tenant_id ? $user->id === $this->tenant_id : strcasecmp($user->email, $this->tenant_email) === 0) {
            return 'tenant';
        }

        return null;
    }

    public function signatureOf(string $role): ?RentalContractSignature
    {
        return $this->validSignatures()->where('role', $role)->first();
    }

    public function d(string $key, mixed $default = null): mixed { return data_get($this->data, $key, $default); }

    public function firstPaymentHalere(): int { return $this->deposit_halere + $this->rent_halere + $this->fees_halere; }

    public function statusLabel(): string
    {
        return [
            'draft' => 'Koncept', 'signed_landlord' => 'Podepsáno pronajímatelem', 'sent' => 'Odesláno nájemci',
            'signed_both' => 'Podepsáno oběma stranami', 'cancelled' => 'Zrušeno', 'expired' => 'Propadlo',
        ][$this->status] ?? $this->status;
    }
}
