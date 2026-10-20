<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\RentalContract;
use App\Models\User;
use App\Services\Contracts\RentalContractService;
use Illuminate\Http\Request;

/** In-system rental deal: draft → landlord signs → send → tenant signs → listing closed. Flag: reality.features.contracts. */
class RentalContractController extends Controller
{
    public function __construct(private RentalContractService $svc) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $contracts = RentalContract::with('listing')
            ->where(fn ($q) => $q->where('landlord_id', $u->id)->orWhere('tenant_id', $u->id)
                ->orWhere(fn ($w) => $w->whereNull('tenant_id')->where('tenant_email', strtolower($u->email))))
            ->latest()->get();

        return view('contracts.index', compact('contracts'));
    }

    public function create(Request $request, Listing $listing)
    {
        $this->authorizeListing($listing);
        if ($open = RentalContract::where('listing_id', $listing->id)->whereIn('status', RentalContract::EDITABLE)->first()) {
            return redirect()->route('contracts.show', $open);
        }
        $email = $request->query('najemce');
        $tenant = $email ? User::where('email', $email)->first() : null;

        return view('contracts.form', ['listing' => $listing, 'contract' => null, 'v' => $this->svc->prefill($listing, $request->user(), $tenant, $email)]);
    }

    public function store(Request $request, Listing $listing)
    {
        $this->authorizeListing($listing);
        abort_if(RentalContract::where('listing_id', $listing->id)->whereIn('status', RentalContract::EDITABLE)->exists(), 409);
        $c = $this->svc->create($listing, $request->user(), $request->validate(RentalContractService::rules()));

        return redirect()->route('contracts.show', $c)->with('status', 'Koncept smlouvy byl uložen. Zkontrolujte text a podepište.');
    }

    public function show(Request $request, RentalContract $contract)
    {
        $this->authorize('view', $contract);
        $contract->load('signatures', 'listing', 'payments');

        return view('contracts.show', [
            'c' => $contract, 'role' => $contract->roleOf($request->user()), 'document' => $this->svc->text($contract),
            'canSign' => $this->svc->canSign($contract, $request->user()), 'spd' => $this->svc->spd($contract),
        ]);
    }

    public function edit(RentalContract $contract)
    {
        $this->authorize('update', $contract);

        return view('contracts.form', ['listing' => $contract->listing, 'contract' => $contract, 'v' => [
            ...$contract->data, 'rent' => intdiv($contract->rent_halere, 100), 'fees' => intdiv($contract->fees_halere, 100), 'deposit' => intdiv($contract->deposit_halere, 100),
        ]]);
    }

    public function update(Request $request, RentalContract $contract)
    {
        $this->authorize('update', $contract);
        $hadSignatures = $contract->validSignatures()->exists();
        $this->svc->update($contract, $request->validate(RentalContractService::rules()));

        return redirect()->route('contracts.show', $contract)->with('status', $hadSignatures
            ? 'Smlouva byla upravena. Dosavadní podpisy pozbyly platnosti a smlouva je opět koncept.' : 'Smlouva byla upravena.');
    }

    public function printable(RentalContract $contract)
    {
        $this->authorize('view', $contract);

        return view('contracts.print', ['c' => $contract->load('signatures'), 'document' => $this->svc->text($contract)]);
    }

    public function signForm(Request $request, RentalContract $contract)
    {
        $this->authorize('view', $contract);
        abort_unless($role = $this->svc->canSign($contract, $request->user()), 403);

        return view('contracts.sign', ['c' => $contract, 'role' => $role, 'document' => $this->svc->text($contract), 'sha' => $this->svc->hash($contract)]);
    }

    public function sendCode(Request $request, RentalContract $contract)
    {
        $this->authorize('view', $contract);
        $this->svc->sendCode($contract, $request->user());

        return back()->with('status', 'Ověřovací kód jsme poslali na váš e-mail.');
    }

    public function sign(Request $request, RentalContract $contract)
    {
        $this->authorize('view', $contract);
        $v = $request->validate([
            'consent' => ['accepted'], 'typed_name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'digits:6'], 'text_sha256' => ['required', 'string', 'size:64'],
        ]);
        $this->svc->sign($contract, $request->user(), $v['typed_name'], $v['code'], $v['text_sha256'], $request->ip(), $request->userAgent());

        return redirect()->route('contracts.show', $contract)->with('status', $contract->status === 'signed_both'
            ? 'Smlouva je podepsána oběma stranami. Finální dokument jsme poslali e-mailem.' : 'Smlouva je podepsána. Nyní ji můžete odeslat nájemci.');
    }

    public function send(RentalContract $contract)
    {
        $this->authorize('send', $contract);
        $this->svc->sendToTenant($contract);

        return back()->with('status', 'Smlouva byla odeslána nájemci k podpisu.');
    }

    public function cancel(RentalContract $contract)
    {
        $this->authorize('cancel', $contract);
        $this->svc->cancel($contract);

        return back()->with('status', 'Smlouva byla zrušena.');
    }

    public function pay(Request $request, RentalContract $contract)
    {
        abort_unless($contract->roleOf($request->user()) === 'tenant', 403);
        $url = $this->svc->startPayment($contract, $request->user());

        return $url ? redirect()->away($url) : redirect()->route('contracts.show', $contract)->with('status', 'Platba byla zpracována.');
    }

    public function audit(RentalContract $contract)
    {
        $this->authorize('audit', $contract);

        return response(view('contracts.audit-text', ['c' => $contract->load('signatures')])->render(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function authorizeListing(Listing $listing): void
    {
        abort_unless($listing->source === 'internal' && $listing->user_id === auth()->id(), 403);
        abort_unless($listing->offer_type === 'rent' && in_array($listing->status, ['active', 'expired'], true), 409);
    }
}
