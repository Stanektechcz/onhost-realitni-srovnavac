<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Platby a předplatné jen ke čtení; tarify (ceny v haléřích) lze spravovat. */
class BillingAdminController extends Controller
{
    public const PAYMENT_STATUSES = ['created', 'paid', 'failed', 'refunded'];

    public const SUBSCRIPTION_STATUSES = ['pending', 'active', 'cancelled', 'expired'];

    public function payments(Request $request)
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(self::PAYMENT_STATUSES)]]);
        $payments = Payment::with('user')->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.billing.payments', ['payments' => $payments, 'f' => $f, 'flagsOff' => $this->flagsOff()]);
    }

    public function subscriptions(Request $request)
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(self::SUBSCRIPTION_STATUSES)]]);
        $subscriptions = Subscription::with(['user', 'plan'])->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.billing.subscriptions', ['subscriptions' => $subscriptions, 'f' => $f, 'flagsOff' => $this->flagsOff()]);
    }

    public function plans()
    {
        return view('admin.billing.plans', ['plans' => MembershipPlan::orderBy('audience')->orderBy('price_halere')->get(), 'flagsOff' => $this->flagsOff()]);
    }

    public function createPlan()
    {
        return view('admin.billing.plan-form', ['plan' => new MembershipPlan(['is_active' => true, 'period_days' => 30, 'audience' => 'advertiser'])]);
    }

    public function storePlan(Request $request)
    {
        $plan = MembershipPlan::create($this->validatePlan($request));
        AuditLog::record('admin.plan.create', $plan, ['code' => $plan->code, 'price_halere' => $plan->price_halere]);

        return redirect()->route('admin.plans.index')->with('status', 'Tarif byl vytvořen.');
    }

    public function editPlan(MembershipPlan $plan)
    {
        return view('admin.billing.plan-form', compact('plan'));
    }

    public function updatePlan(Request $request, MembershipPlan $plan)
    {
        $plan->fill($this->validatePlan($request, $plan));
        $changes = $plan->getDirty();
        $plan->save();
        AuditLog::record('admin.plan.update', $plan, ['changed' => array_keys($changes)]);

        return redirect()->route('admin.plans.index')->with('status', 'Tarif byl uložen.');
    }

    public function destroyPlan(MembershipPlan $plan)
    {
        if (Subscription::where('membership_plan_id', $plan->id)->exists()) {
            // tarif s historií předplatného jen vypneme, aby zůstala evidence
            $plan->update(['is_active' => false]);
            AuditLog::record('admin.plan.deactivate', $plan, ['code' => $plan->code]);

            return back()->with('status', 'Tarif má předplatné, byl proto jen vypnut.');
        }
        AuditLog::record('admin.plan.delete', $plan, ['code' => $plan->code]);
        $plan->delete();

        return back()->with('status', 'Tarif byl smazán.');
    }

    private function validatePlan(Request $request, ?MembershipPlan $plan = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/', Rule::unique('membership_plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:120'],
            'audience' => ['required', Rule::in(['advertiser', 'seeker'])],
            'price_halere' => ['required', 'integer', 'min:0', 'max:100000000'],
            'period_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'features' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
        $data['features'] = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['features'] ?? '')))));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** Vypnuté peněžní příznaky – zobrazí se upozornění. */
    private function flagsOff(): array
    {
        return array_keys(array_filter(['memberships' => ! config('reality.features.memberships'), 'payments' => ! config('reality.features.payments')]));
    }
}
