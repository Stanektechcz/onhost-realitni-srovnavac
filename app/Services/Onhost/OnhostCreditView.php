<?php

namespace App\Services\Onhost;

use Illuminate\View\View;

/** View composer: the signed-in user's ONhost connection and balance (one API call per request; failures never break a page). */
class OnhostCreditView
{
    private ?array $data = null;

    public function __construct(private readonly OnhostCreditClient $credit) {}

    public function compose(View $view): void
    {
        if (! OnhostCreditClient::enabled() || ! auth()->check()) {
            return;
        }
        $view->with($this->data ??= $this->load());
    }

    private function load(): array
    {
        $user = auth()->user();
        if (! $user->onhostConnection()->exists()) {
            return ['onhostConnected' => false];
        }
        try {
            return ['onhostConnected' => true, 'onhostBalance' => $this->credit->balance($user)];
        } catch (OnhostCreditException $e) {
            if ($e->needsReconnect()) {
                $user->onhostConnection()->delete();

                return ['onhostConnected' => false];
            }

            return ['onhostConnected' => true, 'onhostError' => $e->userMessage()];
        }
    }
}
