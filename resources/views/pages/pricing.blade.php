@extends('layouts.app', ['title' => 'Členství a ceník', 'canonical' => route('pricing')])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1 class="font-display text-4xl font-bold">Členství</h1>
    <p class="text-gray-600">Ceny jsou za portálové služby. Z ceny nemovitosti si nebereme nic.</p>
    @foreach (['advertiser' => 'Pro inzerenty', 'seeker' => 'Pro hledající'] as $aud => $label)
        @if (isset($plans[$aud]))
            <h2 class="mt-8 font-display text-2xl font-bold">{{ $label }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($plans[$aud] as $plan)
                    <div class="card flex flex-col p-6">
                        <h3 class="font-display text-xl font-bold text-plum">{{ $plan->name }}</h3>
                        <p class="mt-2 font-display text-3xl font-extrabold">{{ $plan->price_halere ? \App\Models\Listing::formatCzk($plan->price_halere) : 'Zdarma' }}<span class="text-sm font-normal text-gray-500"> / {{ $plan->period_days }} dní</span></p>
                        <ul class="mt-4 flex-1 space-y-1 text-sm">@foreach ($plan->features ?? [] as $feat)<li>✓ {{ $feat }}</li>@endforeach</ul>
                        @if (config('reality.features.payments') && $plan->price_halere)
                            @auth<form method="POST" action="{{ route('pricing.subscribe', $plan) }}" class="mt-4">@csrf<button class="btn-primary w-full">Aktivovat</button></form>@else<a href="{{ route('login') }}" class="btn-ghost mt-4">Přihlaste se</a>@endauth
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
@endsection
