@if (! empty($flagsOff))
    <div class="card mb-4 border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="note">
        <p class="font-semibold">Peněžní funkce jsou vypnuté</p>
        <p class="mt-1">Vypnuto: {{ collect($flagsOff)->map(fn ($f) => ['memberships' => 'členství (REALITY_FEATURE_MEMBERSHIPS)', 'payments' => 'platby (REALITY_FEATURE_PAYMENTS)'][$f])->join(', ') }}. Uživatelé nemohou nic zaplatit; údaje níže jsou jen evidence. Zapnutí se provádí proměnnou prostředí na serveru.</p>
    </div>
@endif
<div class="mb-4 flex flex-wrap gap-2 text-sm">
    @foreach (['admin.billing.payments' => 'Platby', 'admin.billing.subscriptions' => 'Předplatné', 'admin.plans.index' => 'Tarify'] as $r => $label)
        <a href="{{ route($r) }}" @class(['chip', 'border-primary text-primary font-semibold' => request()->routeIs($r)])>{{ $label }}</a>
    @endforeach
</div>
