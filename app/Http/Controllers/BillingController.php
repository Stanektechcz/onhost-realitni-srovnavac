<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Services\Payments\Comgate\ComgateException;
use App\Services\Payments\CommissionCalculator;
use App\Services\Payments\PaymentProvider;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function subscribe(Request $request, MembershipPlan $plan, PaymentProvider $provider, CommissionCalculator $commission)
    {
        abort_unless(config('reality.features.memberships') && config('reality.features.payments') && $plan->is_active, 404);
        $user = $request->user();
        abort_if($plan->audience === 'advertiser' && ! $user->canAdvertise(), 403);

        $sub = $user->subscriptions()->create(['membership_plan_id' => $plan->id, 'status' => 'pending']);
        $payment = new Payment([
            'user_id' => $user->id, 'service' => 'membership', 'provider' => $provider->name(),
            'amount_halere' => $plan->price_halere,
            'commission_halere' => $commission->forService('membership', $plan->price_halere),
            'status' => 'created', 'meta' => ['plan' => $plan->code],
        ]);
        $payment->payable()->associate($sub);
        $payment->save();

        try {
            $redirect = $provider->create($payment);
        } catch (ComgateException) {
            $payment->update(['status' => 'failed']);
            $sub->update(['status' => 'cancelled']);

            return redirect()->route('pricing')->withErrors(['payment' => 'Platební bránu se nepodařilo kontaktovat. Zkuste to prosím později.']);
        }
        if ($redirect) {
            return redirect()->away($redirect);
        }
        if ($provider->status($payment->refresh()) === 'paid') {
            $sub->update(['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays($plan->period_days)]);
        }
        AuditLog::record('membership.subscribed', $sub, ['payment_id' => $payment->id, 'status' => $payment->status]);

        return redirect()->route('dashboard')->with('status', 'Členství '.$plan->name.' je aktivní.');
    }
}
