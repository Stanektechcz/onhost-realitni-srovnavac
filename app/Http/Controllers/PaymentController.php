<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\Comgate\ComgateException;
use App\Services\Payments\ComgatePaymentProvider;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\PaymentSettlement;
use Illuminate\Http\Request;

/** Return from the gateway and Comgate's push notification. Both only trigger a status re-read; neither trusts the caller. */
class PaymentController extends Controller
{
    public function back(Request $request, Payment $payment, PaymentSettlement $settlement)
    {
        abort_unless(config('reality.features.payments'), 404);
        abort_unless((int) $payment->user_id === (int) $request->user()->id, 404);
        try {
            $payment = $settlement->settle($payment);
        } catch (ComgateException) {
            return redirect()->route('dashboard')->withErrors(['payment' => 'Stav platby se teď nepodařilo ověřit. Zkuste stránku obnovit za chvíli.']);
        }

        return redirect()->route('dashboard')->with('status', match ($payment->status) {
            'paid' => 'Platba proběhla, členství je aktivní.',
            'failed' => 'Platba byla zrušena, členství nebylo aktivováno.',
            default => 'Platba se zpracovává. Členství aktivujeme hned po jejím připsání.',
        });
    }

    public function notify(Request $request, PaymentProvider $provider, PaymentSettlement $settlement)
    {
        abort_unless(config('reality.features.payments') && $provider instanceof ComgatePaymentProvider, 404);
        try {
            $transId = $provider->client()->verifyNotification($request);
        } catch (ComgateException $e) {
            return response('code=1&message=refused', $e->httpStatus)->header('Content-Type', 'text/plain');
        }
        $payment = Payment::where('provider', 'comgate')->where('provider_ref', $transId)->first();
        if ($payment) {
            try {
                $settlement->settle($payment);
            } catch (ComgateException) {
                return response('code=1&message=retry', 502)->header('Content-Type', 'text/plain'); // Comgate retries
            }
        }

        return response('code=0&message=OK')->header('Content-Type', 'text/plain');
    }
}
