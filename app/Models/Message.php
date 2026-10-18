<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['listing_id', 'sender_id', 'recipient_id', 'sender_name', 'sender_email', 'sender_phone', 'body', 'relay_token', 'ip'];

    protected function casts(): array { return ['read_at' => 'datetime', 'archived_at' => 'datetime']; }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }

    /** Masked e-mail for display (jan.novak@example.cz -> j*******k@e******.cz). */
    public static function mask(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        $m = fn (string $s) => mb_strlen($s) <= 2 ? str_repeat('*', mb_strlen($s)) : mb_substr($s, 0, 1).str_repeat('*', mb_strlen($s) - 2).mb_substr($s, -1);
        $parts = explode('.', $d);
        $tld = count($parts) > 1 ? '.'.array_pop($parts) : '';

        return $m($u).'@'.$m(implode('.', $parts)).$tld;
    }

    public function relayAddress(): string
    {
        return 'zprava+'.$this->relay_token.'@'.parse_url(config('app.url'), PHP_URL_HOST);
    }
}
