<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's ONhost ID tokens. Never leaves the server; tokens are encrypted at rest and hidden from serialization. */
class OnhostConnection extends Model
{
    protected $fillable = ['user_id', 'subject', 'access_token', 'refresh_token', 'expires_at', 'scope'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, explode(' ', (string) $this->scope), true);
    }
}
