<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;

/** Sdílené KPI pro administraci a týdenní souhrn. */
class AdminKpi
{
    /** @return array<string,int> */
    public function all(): array
    {
        return [
            'Nové inzeráty (7 dní)' => Listing::where('source', 'internal')->where('created_at', '>=', now()->subDays(7))->count(),
            'Nové inzeráty (30 dní)' => Listing::where('source', 'internal')->where('created_at', '>=', now()->subDays(30))->count(),
            'Veřejné inzeráty' => Listing::public()->count(),
            'Ke schválení' => Listing::where('status', 'pending')->count(),
            'Dotazy k inzerátům (7 dní)' => Message::where('created_at', '>=', now()->subDays(7))->count(),
            'Nové kontaktní zprávy' => ContactMessage::whereNull('handled_at')->count(),
            'Noví uživatelé (7 dní)' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'Aktivní uživatelé (30 dní)' => $this->activeUsers(),
        ];
    }

    /** Uživatelé přihlášení (heslem nebo ONhost ID) za posledních 30 dní. */
    public function activeUsers(int $days = 30): int
    {
        return User::whereNull('blocked_at')->where('last_login_at', '>=', now()->subDays($days))->count();
    }
}
