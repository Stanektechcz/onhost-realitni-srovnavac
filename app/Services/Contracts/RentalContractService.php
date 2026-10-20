<?php

namespace App\Services\Contracts;

use App\Mail\RentalContractCode;
use App\Mail\RentalContractCompleted;
use App\Mail\RentalContractInvitation;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\RentalContract;
use App\Models\User;
use App\Services\Payments\CommissionCalculator;
use App\Services\Payments\PaymentProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentalContractService
{
    public function __construct(private CommissionCalculator $commissions, private PaymentProvider $payments) {}

    /** Validation rules for the contract form (amounts in whole CZK). */
    public static function rules(): array
    {
        $party = fn (string $p) => [
            "$p.name" => ['required', 'string', 'max:160'],
            "$p.address" => ['required', 'string', 'max:255'],
            "$p.birth_or_ico" => ['required', 'string', 'max:40'],
            "$p.email" => ['required', 'email', 'max:255'],
            "$p.phone" => ['nullable', 'string', 'max:30'],
        ];

        return [
            ...$party('landlord'), ...$party('tenant'),
            'property.address' => ['required', 'string', 'max:255'],
            'property.disposition' => ['nullable', 'string', 'max:10'],
            'property.area_m2' => ['nullable', 'numeric', 'min:1', 'max:10000'],
            'property.unit_no' => ['nullable', 'string', 'max:40'],
            'rent' => ['required', 'integer', 'min:1', 'max:10000000'],
            'fees' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            // NOZ § 2254 odst. 1: jistota nesmí převýšit trojnásobek měsíčního nájemného.
            'deposit' => ['nullable', 'integer', 'min:0', function ($attr, $value, $fail) {
                if ((int) $value > 3 * (int) request('rent')) {
                    $fail('Jistota (kauce) nesmí převýšit trojnásobek měsíčního nájemného (§ 2254 NOZ).');
                }
            }],
            'terms.start_date' => ['required', 'date'],
            'terms.term' => ['required', 'in:fixed,indefinite'],
            'terms.end_date' => ['nullable', 'required_if:terms.term,fixed', 'date', 'after:terms.start_date'],
            'terms.notice_months' => ['required', 'integer', 'min:3', 'max:12'],
            'terms.pets' => ['nullable', 'boolean'],
            'terms.occupants' => ['required', 'integer', 'min:1', 'max:20'],
            'terms.payment_day' => ['required', 'integer', 'min:1', 'max:28'],
            'terms.account' => ['required', 'string', 'max:40', function ($attr, $value, $fail) {
                if (! SpdPayment::toIban($value)) {
                    $fail('Zadejte platné číslo účtu (např. 19-2000145399/0800) nebo IBAN.');
                }
            }],
            'terms.meter_readings' => ['nullable', 'string', 'max:2000'],
            'terms.special_terms' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Form defaults from the listing and both users. */
    public function prefill(Listing $listing, User $landlord, ?User $tenant = null, ?string $tenantEmail = null): array
    {
        return [
            'landlord' => ['name' => $landlord->name, 'email' => $landlord->email, 'phone' => $landlord->phone, 'address' => '', 'birth_or_ico' => ''],
            'tenant' => ['name' => $tenant?->name ?? '', 'email' => $tenant?->email ?? $tenantEmail ?? '', 'phone' => $tenant?->phone ?? '', 'address' => '', 'birth_or_ico' => ''],
            'property' => ['address' => $listing->locationLabel(), 'disposition' => $listing->disposition, 'area_m2' => $listing->area_m2, 'unit_no' => ''],
            'rent' => $listing->price_halere ? intdiv($listing->price_halere, 100) : null,
            'fees' => intdiv((int) $listing->fees_halere, 100),
            'deposit' => $listing->deposit_halere ? intdiv($listing->deposit_halere, 100) : null,
            'terms' => [
                'start_date' => ($listing->available_from && $listing->available_from->isFuture() ? $listing->available_from : now()->addDays(14))->toDateString(),
                'term' => 'fixed', 'end_date' => null, 'notice_months' => 3, 'pets' => (bool) $listing->pets_allowed,
                'occupants' => 1, 'payment_day' => 15, 'account' => '', 'meter_readings' => '', 'special_terms' => '',
            ],
        ];
    }

    public function create(Listing $listing, User $landlord, array $v): RentalContract
    {
        $c = new RentalContract(['listing_id' => $listing->id, 'landlord_id' => $landlord->id]);
        $this->fill($c, $v);
        $c->save();
        AuditLog::record('contract.created', $c, ['version' => 1]);

        return $c;
    }

    /** Edit terms: invalidates all signatures, bumps version and returns the contract to draft. */
    public function update(RentalContract $c, array $v): RentalContract
    {
        abort_unless($c->isEditable(), 409);
        DB::transaction(function () use ($c, $v) {
            $invalidated = $c->validSignatures()->update(['invalidated_at' => now(), 'invalidated_reason' => 'edited']);
            $this->fill($c, $v);
            $c->version++;
            $c->status = 'draft';
            $c->sent_at = null;
            $c->save();
            AuditLog::record('contract.edited', $c, ['version' => $c->version, 'signatures_invalidated' => $invalidated]);
        });

        return $c;
    }

    private function fill(RentalContract $c, array $v): void
    {
        $data = collect($v)->only(['landlord', 'tenant', 'property', 'terms'])->all();
        $data['terms']['pets'] = (bool) ($data['terms']['pets'] ?? false);
        $c->fill([
            'data' => $data,
            'tenant_email' => Str::lower($v['tenant']['email']),
            'rent_halere' => (int) $v['rent'] * 100,
            'fees_halere' => (int) ($v['fees'] ?? 0) * 100,
            'deposit_halere' => (int) ($v['deposit'] ?? 0) * 100,
        ]);
        if ($c->tenant_id && $c->isDirty('tenant_email')) {
            $c->tenant_id = null;
        }
    }

    /** The exact contract text (HTML) the parties sign. Deterministic for a given version. */
    public function text(RentalContract $c): string
    {
        return view('contracts.document', ['c' => $c])->render();
    }

    public function hash(RentalContract $c): string { return hash('sha256', $this->text($c)); }

    public function canSign(RentalContract $c, User $user): ?string
    {
        $role = $c->roleOf($user);

        return match (true) {
            $role === 'landlord' && $c->status === 'draft' => 'landlord',
            $role === 'tenant' && $c->status === 'sent' => 'tenant',
            default => null,
        };
    }

    public function sendCode(RentalContract $c, User $user): void
    {
        $role = $this->canSign($c, $user) ?? abort(403);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->codeKey($c, $user), ['hash' => Hash::make($code), 'attempts' => 0, 'version' => $c->version],
            now()->addMinutes(config('reality.contracts.code_ttl_minutes', 10)));
        Mail::to($user->email)->send(new RentalContractCode($c, $code));
        AuditLog::record('contract.code_sent', $c, ['role' => $role, 'version' => $c->version]);
    }

    /** Verifies the e-mail code + typed name + text hash and stores the signature with its audit evidence. */
    public function sign(RentalContract $c, User $user, string $typedName, string $code, string $sha256, ?string $ip, ?string $ua): void
    {
        $role = $this->canSign($c, $user) ?? abort(403);
        $fail = fn (string $field, string $msg) => throw ValidationException::withMessages([$field => $msg]);

        if (! hash_equals($this->hash($c), $sha256)) {
            $fail('text_sha256', 'Text smlouvy se mezitím změnil. Přečtěte si prosím aktuální znění.');
        }
        $expected = (string) $c->d("$role.name");
        if (Str::lower(Str::squish($typedName)) !== Str::lower(Str::squish($expected))) {
            $fail('typed_name', 'Vepište své celé jméno přesně tak, jak je uvedeno ve smlouvě.');
        }
        $key = $this->codeKey($c, $user);
        $entry = Cache::get($key);
        if (! $entry || $entry['version'] !== $c->version) {
            $fail('code', 'Kód vypršel. Vyžádejte si nový.');
        }
        if ($entry['attempts'] >= config('reality.contracts.code_attempts', 5)) {
            Cache::forget($key);
            $fail('code', 'Příliš mnoho pokusů. Vyžádejte si nový kód.');
        }
        if (! Hash::check($code, $entry['hash'])) {
            $entry['attempts']++;
            Cache::put($key, $entry, now()->addMinutes(config('reality.contracts.code_ttl_minutes', 10)));
            $fail('code', 'Nesprávný ověřovací kód.');
        }
        Cache::forget($key);

        DB::transaction(function () use ($c, $user, $role, $typedName, $sha256, $ip, $ua) {
            $c->signatures()->create([
                'user_id' => $user->id, 'role' => $role, 'typed_name' => Str::squish($typedName), 'version' => $c->version,
                'text_sha256' => $sha256, 'ip' => $ip, 'user_agent' => Str::limit((string) $ua, 490, ''), 'signed_at' => now(),
            ]);
            if ($role === 'tenant') {
                $c->tenant_id = $user->id;
            }
            $c->status = $role === 'landlord' ? 'signed_landlord' : 'signed_both';
            $c->save();
            AuditLog::record('contract.signed', $c, ['role' => $role, 'version' => $c->version, 'sha256' => $sha256]);
        });

        if ($c->status === 'signed_both') {
            $this->complete($c);
        }
    }

    public function sendToTenant(RentalContract $c): void
    {
        abort_unless($c->status === 'signed_landlord', 409);
        $c->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        Mail::to($c->tenant_email)->send(new RentalContractInvitation($c));
        AuditLog::record('contract.sent', $c, ['version' => $c->version]);
    }

    public function cancel(RentalContract $c): void
    {
        abort_unless($c->isEditable(), 409);
        $c->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        AuditLog::record('contract.cancelled', $c);
    }

    /** Sent contracts the tenant never signed expire after N days. Returns count. */
    public function expireStale(): int
    {
        $n = 0;
        RentalContract::where('status', 'sent')->where('sent_at', '<=', now()->subDays(config('reality.contracts.sent_expiry_days', 30)))
            ->each(function (RentalContract $c) use (&$n) {
                $c->forceFill(['status' => 'expired'])->save();
                AuditLog::record('contract.expired', $c);
                $n++;
            });

        return $n;
    }

    /** Both signed: close the listing, cancel viewing slots, deliver document + audit record to both parties. */
    private function complete(RentalContract $c): void
    {
        $c->forceFill(['completed_at' => now()])->save();
        $listing = $c->listing;
        $listing->forceFill(['status' => 'rented', 'moderation_note' => 'Pronajato přes smlouvu '.$c->uuid])->save();
        $cancelled = $listing->slots()->where('starts_at', '>', now())->delete();
        AuditLog::record('contract.completed', $c, ['listing_id' => $listing->id, 'slots_cancelled' => $cancelled]);

        $c->load('signatures', 'landlord', 'tenant');
        $document = $this->text($c);
        foreach (array_unique([$c->landlord->email, $c->tenant?->email ?? $c->tenant_email]) as $to) {
            Mail::to($to)->send(new RentalContractCompleted($c, $document));
        }
    }

    /** Payments flag on: deposit + first month via PaymentProvider; commission from CommissionRule (default 0). */
    public function startPayment(RentalContract $c, User $payer): ?string
    {
        abort_unless(config('reality.features.payments') && $c->status === 'signed_both', 404);
        $existing = $c->payments()->whereIn('status', ['created', 'paid'])->first();
        if ($existing) {
            return null;
        }
        $amount = $c->firstPaymentHalere();
        $payment = new Payment([
            'user_id' => $payer->id, 'service' => 'rental_contract', 'provider' => $this->payments->name(),
            'amount_halere' => $amount, 'commission_halere' => $this->commissions->forService('rental_contract', $amount),
            'currency' => 'CZK', 'status' => 'created',
            'meta' => ['deposit_halere' => $c->deposit_halere, 'rent_halere' => $c->rent_halere, 'fees_halere' => $c->fees_halere],
        ]);
        $payment->payable()->associate($c);
        $payment->save();
        AuditLog::record('contract.payment_created', $c, ['payment_id' => $payment->id]);

        return $this->payments->create($payment);
    }

    public function spd(RentalContract $c): ?string
    {
        $iban = SpdPayment::toIban($c->d('terms.account'));

        return $iban ? SpdPayment::string($iban, $c->firstPaymentHalere(), (string) $c->id, 'Kauce a najem '.$c->d('property.address')) : null;
    }

    private function codeKey(RentalContract $c, User $user): string { return "rc-code:{$c->id}:{$user->id}"; }
}
