@php
    $fmt = fn ($v) => number_format($v, 0, ',', ' ');
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateAgent',
        'name' => $agency->name,
        'url' => route('agencies.show', $agency),
        'identifier' => ['@type' => 'PropertyValue', 'propertyID' => 'IČO', 'value' => $agency->ico],
        'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $agency->address, 'addressLocality' => $agency->city, 'addressRegion' => $agency->region, 'addressCountry' => 'CZ']),
        'sameAs' => $agency->web ?: null,
        'telephone' => $agency->phone ?: null,
        'email' => $agency->email ?: null,
        'aggregateRating' => $agency->reviews_count ? ['@type' => 'AggregateRating', 'ratingValue' => round($agency->rating_avg, 2), 'reviewCount' => $agency->reviews_count, 'bestRating' => 5, 'worstRating' => 1] : null,
        'employee' => $brokers->isEmpty() ? null : $brokers->map(fn ($b) => ['@type' => 'Person', 'name' => $b->name, 'url' => route('agencies.broker', [$agency, $b])])->all(),
    ]);
@endphp
@extends('layouts.app', [
    'title' => $agency->name.' – realitní kancelář'.($agency->city ? ', '.$agency->city : ''),
    'description' => $agency->name.' (IČO '.$agency->ico.')'.($agency->reviews_count ? ', hodnocení '.number_format($agency->rating_avg, 1, ',', '').'/5' : '').', '.$stats['count'].' aktuálních nabídek.',
    'canonical' => route('agencies.show', $agency),
])
@push('head')
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<div class="container-page space-y-6 py-6">
    <nav class="text-sm text-muted-foreground"><a href="{{ route('agencies.index') }}" class="hover:text-foreground">Realitní kanceláře</a>@if ($agency->region) › <a class="hover:text-foreground" href="{{ route('agencies.index', ['kraj' => $agency->region]) }}">{{ $agency->region }}</a>@endif</nav>
    @if (session('status'))<p class="rounded-2xl border bg-plum-50 px-4 py-3 text-sm">{{ session('status') }}</p>@endif

    <header class="card p-6">
        <h1 class="font-display text-3xl font-extrabold">{{ $agency->name }}</h1>
        <p class="mt-1 text-muted-foreground">IČO {{ $agency->ico }}@if ($agency->address) · {{ $agency->address }}@elseif ($agency->city) · {{ $agency->city }}@endif</p>
        <p class="mt-3 flex flex-wrap gap-2 text-sm">
            @if ($agency->reviews_count)<span class="chip">★ {{ number_format($agency->rating_avg, 1, ',', '') }} z 5 ({{ $agency->reviews_count }} hodnocení)</span>@else<span class="chip">Zatím bez hodnocení</span>@endif
            @if ($agency->isVerified())<span class="chip">Ověřená kancelář</span>@else<span class="chip">Údaje z ARES</span>@endif
        </p>
        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
            @if ($agency->licence)<div><dt class="text-muted-foreground">Oprávnění</dt><dd>{{ $agency->licence }}</dd></div>@endif
            @if ($agency->founded_on)<div><dt class="text-muted-foreground">Vznik</dt><dd>{{ $agency->founded_on->format('j. n. Y') }}</dd></div>@endif
            @if ($agency->web)<div><dt class="text-muted-foreground">Web</dt><dd><a class="text-accent underline" rel="noopener nofollow" target="_blank" href="{{ \Illuminate\Support\Str::start($agency->web, 'https://') }}">{{ $agency->web }}</a></dd></div>@endif
            @if ($agency->phone)<div><dt class="text-muted-foreground">Telefon</dt><dd>{{ $agency->phone }}</dd></div>@endif
            @if ($agency->email)<div><dt class="text-muted-foreground">E-mail</dt><dd>{{ $agency->email }}</dd></div>@endif
        </dl>
        <p class="mt-3 text-xs text-muted-foreground">Zdroj základních údajů: veřejný rejstřík ARES (Ministerstvo financí).</p>
    </header>

    <section class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5"><p class="text-xs uppercase text-muted-foreground">Aktivní nabídky</p><p class="font-display text-3xl font-bold" data-stat="count">{{ $stats['count'] }}</p></div>
        <div class="card p-5"><p class="text-xs uppercase text-muted-foreground">Průměrná cena za m²</p>
            <p class="text-sm">Prodej: <strong>{{ $stats['ppm2_sale'] ? $fmt($stats['ppm2_sale']).' Kč' : '–' }}</strong></p>
            <p class="text-sm">Nájem: <strong>{{ $stats['ppm2_rent'] ? $fmt($stats['ppm2_rent']).' Kč' : '–' }}</strong></p></div>
        <div class="card p-5"><p class="text-xs uppercase text-muted-foreground">Lokality</p>
            <p class="text-sm">@forelse ($stats['cities'] as $city => $n){{ $city }} ({{ $n }})@if (! $loop->last), @endif @empty – @endforelse</p></div>
    </section>

    @if ($listings->isNotEmpty())
        <section>
            <h2 class="font-display text-2xl font-bold">Aktuální nabídky</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)@include('partials.listing-card', ['l' => $listing])@endforeach
            </div>
        </section>
    @endif

    @if ($brokers->isNotEmpty())
        <section class="card p-6">
            <h2 class="font-display text-2xl font-bold">Makléři</h2>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($brokers as $b)<li><a class="text-accent underline" href="{{ route('agencies.broker', [$agency, $b]) }}">{{ $b->name }}</a>@if ($b->specialization) <span class="text-sm text-muted-foreground">– {{ $b->specialization }}</span>@endif</li>@endforeach
            </ul>
        </section>
    @endif

    @include('agencies._reviews')

    @auth
        @if ($agency->isVerified() && $agency->claimed_by_user_id === auth()->id())
            <section class="card space-y-4 p-6">
                <h2 class="font-display text-xl font-bold">Správa profilu</h2>
                <form method="POST" action="{{ route('agencies.update', $agency) }}" class="grid gap-3 sm:grid-cols-3">
                    @csrf @method('PUT')
                    <input name="web" class="input" value="{{ $agency->web }}" placeholder="https://web-kancelare.cz" aria-label="Web">
                    <input name="phone" class="input" value="{{ $agency->phone }}" placeholder="Veřejný telefon" aria-label="Telefon">
                    <input name="email" class="input" value="{{ $agency->email }}" placeholder="Veřejný e-mail" aria-label="E-mail">
                    <button class="btn-primary sm:col-span-3">Uložit veřejné kontakty</button>
                </form>
                <form method="POST" action="{{ route('agencies.brokers.store', $agency) }}" class="grid gap-3 border-t pt-4">
                    @csrf
                    <input name="name" class="input" required placeholder="Jméno makléře" aria-label="Jméno makléře">
                    <input name="specialization" class="input" placeholder="Specializace" aria-label="Specializace">
                    <textarea name="bio" class="input" rows="2" placeholder="Krátké představení" aria-label="Představení"></textarea>
                    <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="consent" value="1" required class="mt-0.5"> Makléř souhlasí se zveřejněním svého profilu.</label>
                    <button class="btn-ghost">Přidat makléře</button>
                </form>
            </section>
        @elseif (! $agency->isVerified())
            <details class="card p-6">
                <summary class="cursor-pointer font-semibold">Jste z této kanceláře? Převezměte profil</summary>
                @if ($myClaim)
                    <p class="mt-3 text-sm">Vaše žádost: {{ ['pending' => 'čeká na ověření', 'approved' => 'schválena', 'rejected' => 'zamítnuta'][$myClaim->status] ?? $myClaim->status }}.</p>
                @else
                <form method="POST" action="{{ route('agencies.claim', $agency) }}" class="mt-3 space-y-3">
                    @csrf
                    <textarea name="message" rows="2" class="input" maxlength="1000" placeholder="Doplňte, jak můžeme ověřit vaši příslušnost ke kanceláři"></textarea>
                    <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="confirm" value="1" required class="mt-0.5"> Prohlašuji, že jsem oprávněn(a) jednat za tuto kancelář.</label>
                    <button class="btn-primary">Požádat o převzetí</button>
                    <p class="text-xs text-muted-foreground">Pokud máte u nás importní účet kanceláře s e-mailem na doméně jejího webu, ověření proběhne ihned; jinak žádost posoudí administrátor.</p>
                </form>
                @endif
            </details>
        @endif
    @endauth
</div>
@endsection
