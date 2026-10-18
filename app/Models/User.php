<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['owner', 'seeker', 'agency', 'developer', 'admin'];

    protected $fillable = ['name', 'email', 'password', 'role', 'phone'];

    protected $hidden = ['password', 'remember_token', 'feed_token_hash'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_banned' => 'boolean', 'feed_autopublish' => 'boolean', 'feed_last_used_at' => 'datetime'];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canAdvertise(): bool
    {
        return in_array($this->role, ['owner', 'agency', 'developer', 'admin'], true);
    }

    public function listings(): HasMany { return $this->hasMany(Listing::class); }
    public function favorites(): HasMany { return $this->hasMany(Favorite::class); }
    public function savedSearches(): HasMany { return $this->hasMany(SavedSearch::class); }
    public function receivedMessages(): HasMany { return $this->hasMany(Message::class, 'recipient_id'); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
}
