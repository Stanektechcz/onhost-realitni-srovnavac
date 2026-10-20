<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Onhost\OnhostCreditClient;
use App\Services\Onhost\OnhostCreditException;
use App\Services\Onhost\OnhostIdClient;
use App\Services\Payments\CommissionCalculator;
use App\Services\Payments\OnhostCreditPaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** ONhost ID connection + payment by ONhost credit. Every route is 404 while ONHOST_CREDIT_ENABLED is off. */
class OnhostCreditController extends Controller
{
    public function connect(Request $request, OnhostIdClient $id)
    {
        self::ensureEnabled();
        abort_unless($id->configured(), 503);
        $auth = $id->authorizationRequest();
        $request->session()->put('onhost_oidc', ['state' => $auth['state'], 'verifier' => $auth['verifier'], 'back' => self::internalPath(url()->previous())]);

        return redirect()->away($auth['url']);
    }

    public function callback(Request $request, OnhostIdClient $id)
    {
        self::ensureEnabled();
        $pending = $request->session()->pull('onhost_oidc');
        if (! is_array($pending) || ! is_string($request->query('state')) || ! hash_equals($pending['state'], $request->query('state'))) {
            return redirect()->route('dashboard')->with('error', 'Propojení s ONhost ID se nezdařilo (neplatný požadavek). Zkuste to znovu.');
        }
        if (! is_string($request->query('code'))) {
            return redirect()->route('dashboard')->with('error', 'Propojení s ONhost ID bylo zrušeno.');
        }
        try {
            $id->exchangeCode($request->user(), $request->query('code'), $pending['verifier']);
        } catch (OnhostCreditException $e) {
            return redirect()->route('dashboard')->with('error', 'Propojení s ONhost ID se nezdařilo. '.$e->userMessage());
        }
        AuditLog::record('onhost.connected', $request->user());

        return redirect()->to(self::internalPath($pending['back'] ?? null) ?? route('dashboard'))->with('status', 'Účet je propojen s ONhost ID.');
    }

    public function disconnect(Request $request, OnhostIdClient $id)
    {
        self::ensureEnabled();
        $id->disconnect($request->user());

        return redirect()->route('dashboard')->with('status', 'Propojení s ONhost ID bylo zrušeno.');
    }

    /** "Zaplatit kreditem ONhost" for a membership plan. */
    public function payPlan(Request $request, MembershipPlan $plan, OnhostCreditPaymentProvider $provider, CommissionCalculator $commission)
    {
        self::ensureEnabled();
        abort_unless(config('reality.features.memberships') && $plan->is_active && $plan->price_halere > 0, 404);
        $user = $request->user();
        abort_if($plan->audience === 'advertiser' && ! $user->canAdvertise(), 403);
        if (! $user->onhostConnection()->exists()) {
            return redirect()->route('onhost.connect');
        }

        // Reuse an open (`created`) payment for the same user + plan (e.g. left by a network failure), so a repeated
        // POST resumes it with the same idempotency key instead of opening a second charge. The user row lock
        // serialises concurrent requests of one user.
        [$sub, $payment] = DB::transaction(function () use ($user, $plan, $provider, $commission) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $open = Payment::where('user_id', $user->id)->where('provider', $provider->name())->where('service', 'membership')
                ->where('status', 'created')->where('payable_type', (new Subscription)->getMorphClass())
                ->whereIn('payable_id', Subscription::query()->where('user_id', $user->id)->where('membership_plan_id', $plan->id)->where('status', 'pending')->select('id'))
                ->lockForUpdate()->latest('id')->first();
            if ($open && $open->payable instanceof Subscription) {
                return [$open->payable, $open];
            }
            $sub = $user->subscriptions()->create(['membership_plan_id' => $plan->id, 'status' => 'pending']);
            $payment = new Payment([
                'user_id' => $user->id, 'service' => 'membership', 'provider' => $provider->name(),
                'amount_halere' => $plan->price_halere, 'commission_halere' => $commission->forService('membership', $plan->price_halere),
                'status' => 'created', 'meta' => ['plan' => $plan->code, 'description' => 'členství '.$plan->name],
            ]);
            $payment->payable()->associate($sub);
            $payment->save();

            return [$sub, $payment];
        });

        try {
            $provider->create($payment);
        } catch (OnhostCreditException $e) {
            if ($payment->status === 'failed') {
                $sub->update(['status' => 'cancelled']);
            }
            AuditLog::record('membership.credit_failed', $sub, ['payment_id' => $payment->id, 'error' => $e->error]);
            if ($e->needsReconnect()) {
                $user->onhostConnection()->delete();
            }

            return redirect()->route('pricing')->with('error', $e->userMessage());
        }
        $sub->update(['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays($plan->period_days)]);
        AuditLog::record('membership.subscribed', $sub, ['payment_id' => $payment->id, 'status' => $payment->status, 'provider' => $provider->name()]);

        return redirect()->route('dashboard')->with('status', 'Členství '.$plan->name.' je aktivní (zaplaceno kreditem ONhost).');
    }

    /** Cancellation by an admin: reverses the ONhost charge and cancels the membership. */
    public function refund(Request $request, Payment $payment, OnhostCreditPaymentProvider $provider)
    {
        self::ensureEnabled();
        abort_unless($payment->provider === OnhostCreditPaymentProvider::NAME, 404);
        try {
            $provider->reverse($payment, 'zrušeno administrátorem');
        } catch (OnhostCreditException $e) {
            return back()->with('error', $e->userMessage());
        }
        if ($payment->payable instanceof Subscription) {
            $payment->payable->update(['status' => 'cancelled', 'ends_at' => now()]);
        }
        AuditLog::record('payment.refunded', $payment, ['provider' => $payment->provider]);

        return back()->with('status', 'Platba byla vrácena na kredit ONhost.');
    }

    /** Only same-site relative paths are kept as a return target (no open redirect via Referer). */
    private static function internalPath(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $root = rtrim(url('/'), '/');
        if (str_starts_with($url, $root.'/') || $url === $root) {
            $url = substr($url, strlen($root)) ?: '/';
        }
        if (! str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\\\\\x00-\x1f]/', $url)) {
            return null;
        }

        return $url;
    }

    private static function ensureEnabled(): void
    {
        abort_unless(OnhostCreditClient::enabled(), 404);
    }
}
