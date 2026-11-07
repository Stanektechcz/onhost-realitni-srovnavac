@extends('layouts.account', ['title' => 'Nájemní smlouva'])
@section('account')
<a href="{{ route('contracts.index') }}" class="text-sm text-muted-foreground hover:text-foreground">← Nájemní smlouvy</a>
<div class="mt-1 flex flex-wrap items-center gap-3">
    <h1 class="text-3xl font-bold md:text-4xl">Nájemní smlouva</h1><span class="chip">{{ $c->statusLabel() }}</span>
</div>
@if (session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-sm">{{ session('status') }}</p>@endif

<div class="mt-4 flex flex-wrap gap-2">
    @if ($canSign)<a href="{{ route('contracts.sign', $c) }}" class="btn-primary btn-sm">Podepsat</a>@endif
    @can('update', $c)<a href="{{ route('contracts.edit', $c) }}" class="btn-ghost btn-sm">Upravit</a>@endcan
    @can('send', $c)<form method="POST" action="{{ route('contracts.send', $c) }}">@csrf<button class="btn-primary btn-sm">Odeslat nájemci ({{ $c->tenant_email }})</button></form>@endcan
    <a href="{{ route('contracts.print', $c) }}" class="btn-ghost btn-sm">Tisk / PDF</a>
    <a href="{{ route('contracts.audit', $c) }}" class="btn-ghost btn-sm">Auditní záznam</a>
    @can('cancel', $c)<form method="POST" action="{{ route('contracts.cancel', $c) }}" onsubmit="return confirm('Opravdu zrušit smlouvu?')">@csrf<button class="btn-ghost btn-sm">{{ $role === 'tenant' ? 'Odmítnout' : 'Zrušit' }}</button></form>@endcan
</div>

@if ($c->status === 'signed_both')
<section class="card mt-6 p-6 text-sm">
    <h2 class="font-display text-xl font-bold">Úhrada kauce a prvního nájemného</h2>
    <p class="mt-1">Celkem {{ \App\Models\Listing::formatCzk($c->firstPaymentHalere()) }} (kauce {{ \App\Models\Listing::formatCzk($c->deposit_halere) }} + nájemné {{ \App\Models\Listing::formatCzk($c->rent_halere) }} + zálohy {{ \App\Models\Listing::formatCzk($c->fees_halere) }}).</p>
    @if (config('reality.features.payments'))
        @php $paid = $c->payments->firstWhere('status', 'paid'); @endphp
        @if ($paid)<p class="mt-2 font-semibold">Zaplaceno ({{ $paid->updated_at->format('j. n. Y') }}).</p>
        @elseif ($role === 'tenant')<form method="POST" action="{{ route('contracts.pay', $c) }}" class="mt-3">@csrf<button class="btn-primary btn-sm">Zaplatit online</button></form>@endif
    @else
        <dl class="mt-3 grid gap-1 sm:grid-cols-2" data-testid="bank-details">
            <dt class="text-muted-foreground">Účet</dt><dd class="font-semibold">{{ $c->d('terms.account') }}</dd>
            <dt class="text-muted-foreground">Variabilní symbol</dt><dd class="font-semibold">{{ $c->id }}</dd>
        </dl>
        @if ($spd)
            <p class="mt-3 text-muted-foreground">QR Platba (formát SPD) – řetězec pro bankovní aplikaci:</p>
            <code class="mt-2 block break-all text-xs">{{ $spd }}</code>
        @endif
    @endif
</section>
@endif

<article class="card mt-6 p-8 text-sm">{!! $document !!}@include('contracts.signatures')</article>
@endsection
