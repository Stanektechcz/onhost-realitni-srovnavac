<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FeedToken extends Command
{
    protected $signature = 'reality:feed:token {email} {--autopublish : Inzeráty z feedu publikovat bez ruční moderace} {--revoke : Zrušit token i importní heslo} {--rpc : Vydat heslo pro Sreality-kompatibilní XML-RPC import (/import/RPC2)}';

    protected $description = 'Vydá (nebo zruší) token importního feedu / heslo XML-RPC importu pro realitní kancelář';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || ! in_array($user->role, ['agency', 'developer'], true)) {
            $this->error('Uživatel neexistuje nebo nemá roli agency/developer.');

            return self::FAILURE;
        }
        if ($this->option('revoke')) {
            $user->forceFill(['feed_token_hash' => null, 'feed_import_password' => null])->save();
            AuditLog::record('feed.token_revoked', null, ['agency' => $user->id]);
            $this->info('Token i importní heslo zrušeny.');

            return self::SUCCESS;
        }
        if ($this->option('rpc')) {
            $password = Str::random(32);
            $user->forceFill(['feed_import_password' => $password, 'feed_autopublish' => (bool) $this->option('autopublish')])->save();
            AuditLog::record('feed.rpc_password_issued', null, ['agency' => $user->id, 'autopublish' => (bool) $this->option('autopublish')]);
            $this->line('XML-RPC import (zobrazeno jen jednou, předejte kanceláři bezpečně):');
            $this->line('client_id: '.$user->id);
            $this->line('heslo: '.$password);

            return self::SUCCESS;
        }
        $token = 'rf_'.Str::random(48);
        $user->forceFill(['feed_token_hash' => hash('sha256', $token), 'feed_autopublish' => (bool) $this->option('autopublish')])->save();
        AuditLog::record('feed.token_issued', null, ['agency' => $user->id, 'autopublish' => (bool) $this->option('autopublish')]);
        $this->line('Token (zobrazen jen jednou, předejte ho kanceláři bezpečně):');
        $this->line($token);

        return self::SUCCESS;
    }
}
