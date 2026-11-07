<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Zpráva z veřejného kontaktního formuláře (/kontakt). */
class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'topic', 'body', 'ip', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
