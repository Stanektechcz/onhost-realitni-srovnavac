<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? 'Pronájmy a prodej nemovitostí bez realitky. Mapa, analýzy cen lokalit, kalkulačky a nástroje pro majitele.' }}">
    <meta name="theme-color" content="#12001E">
    <meta name="view-transition" content="same-origin">
    @if (! empty($canonical))<link rel="canonical" href="{{ $canonical }}">@endif
    @if (! empty($noindex))<meta name="robots" content="noindex,nofollow">@endif
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? 'Pronájmy a prodej nemovitostí bez realitky.' }}">
    @isset($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endisset
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
<a href="#main" class="sr-only focus:not-sr-only">Přeskočit na obsah</a>
@php($nav = [
    [route('search', ['offer_type' => 'rent']), 'Pronájem', request()->routeIs('search') && request('offer_type') === 'rent'],
    [route('search', ['offer_type' => 'sale']), 'Prodej', request()->routeIs('search') && request('offer_type') === 'sale'],
    [route('insights.index'), 'Ceny a lokality', request()->routeIs('insights.*')],
    [route('tools.calculators'), 'Kalkulačky', request()->routeIs('tools.calculators')],
    [route('guides.index'), 'Průvodce', request()->routeIs('guides.*')],
])
<header class="glass sticky top-0 z-40 border-b" x-data="{ open: false }" style="view-transition-name: header">
    <div class="container-page flex h-16 items-center gap-4">
        <x-logo />
        <nav class="ml-4 hidden items-center gap-0.5 lg:flex" aria-label="Hlavní navigace">
            @foreach ($nav as [$href, $label, $active])
                <a href="{{ $href }}" class="rounded-full px-3.5 py-2 text-sm font-medium transition hover:bg-secondary {{ $active ? 'bg-secondary text-foreground' : 'text-muted-foreground' }}" @if ($active) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-1.5">
            <a href="{{ route('compare') }}" x-cloak x-show="$store.compare.items.length" :href="$store.compare.url" class="btn-ghost btn-sm hidden sm:inline-flex" aria-label="Porovnání">
                <x-icon name="compare" class="h-4 w-4" /> <span x-text="$store.compare.items.length"></span>
            </a>
            <button type="button" class="btn-ghost btn-sm" x-data="{ dark: document.documentElement.classList.contains('dark') }"
                @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); try { localStorage.setItem('theme', dark ? 'dark' : 'light') } catch (e) {}"
                :aria-label="dark ? 'Světlý režim' : 'Tmavý režim'" aria-label="Tmavý režim">
                <svg x-show="!dark" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg x-cloak x-show="dark" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
            @auth
                <a href="{{ route('account.favorites') }}" class="btn-ghost btn-sm hidden sm:inline-flex" aria-label="Oblíbené"><x-icon name="heart" class="h-4 w-4" /></a>
                <a href="{{ route('dashboard') }}" class="btn-outline btn-sm hidden sm:inline-flex"><x-icon name="user" class="h-4 w-4" /> Můj účet</a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost btn-sm hidden sm:inline-flex">Přihlásit</a>
            @endauth
            <a href="{{ route('listings.create') }}" class="btn-primary btn-sm hidden sm:inline-flex">+ Přidat inzerát</a>
            <button type="button" class="btn-ghost btn-sm lg:hidden" @click="open = !open" :aria-expanded="open" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <nav x-cloak x-show="open" x-transition.origin.top class="container-page flex flex-col gap-1 border-t py-3 lg:hidden" aria-label="Mobilní navigace">
        @foreach ($nav as [$href, $label])<a href="{{ $href }}" class="btn-ghost justify-start">{{ $label }}</a>@endforeach
        @if (config('reality.features.memberships'))<a href="{{ route('pricing') }}" class="btn-ghost justify-start">Členství</a>@endif
        @auth
            <a href="{{ route('dashboard') }}" class="btn-ghost justify-start">Můj účet</a>
            @if (auth()->user()->isAdmin())<a href="{{ route('admin.dashboard') }}" class="btn-ghost justify-start">Admin</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost w-full justify-start">Odhlásit</button></form>
        @else
            <a href="{{ route('login') }}" class="btn-ghost justify-start">Přihlásit</a>
            <a href="{{ route('register') }}" class="btn-ghost justify-start">Registrace</a>
        @endauth
        <a href="{{ route('listings.create') }}" class="btn-primary mt-2">+ Přidat inzerát zdarma</a>
    </nav>
</header>

<main id="main">
    @if (session('status'))
        <div class="container-page pt-4 animate-in"><div class="flex items-center gap-2 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status"><x-icon name="check" class="h-4 w-4" /> {{ session('status') }}</div></div>
    @endif
    @if ($errors->any() && ! empty($showErrorSummary ?? true))
        <div class="container-page pt-4"><div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-destructive" role="alert">{{ $errors->first() }}</div></div>
    @endif
    @yield('content')
</main>

<div x-data x-cloak x-show="$store.compare.items.length > 1" x-transition class="no-print fixed inset-x-0 bottom-4 z-40 flex justify-center px-4">
    <div class="glass flex items-center gap-3 rounded-full border px-4 py-2 shadow-pop">
        <x-icon name="compare" class="h-4 w-4 text-accent" />
        <span class="text-sm font-semibold"><span x-text="$store.compare.items.length"></span> / 4 k porovnání</span>
        <a :href="$store.compare.url" class="btn-primary btn-sm">Porovnat</a>
        <button type="button" class="btn-ghost btn-sm" @click="$store.compare.clear()" aria-label="Vyprázdnit porovnání"><x-icon name="x" class="h-4 w-4" /></button>
    </div>
</div>

<footer class="mt-24 border-t bg-card">
    <div class="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <x-logo />
            <p class="mt-3 max-w-sm text-sm text-muted-foreground">Inzerce nemovitostí přímo od majitelů. Mapa, analýzy cen lokalit, kalkulačky a nástroje pro pronajímatele i hledající.</p>
        </div>
        <div>
            <h2 class="mb-3 font-sans text-sm font-semibold">Hledám bydlení</h2>
            <ul class="space-y-2 text-sm text-muted-foreground">
                <li><a href="{{ route('search', ['offer_type' => 'rent']) }}" class="hover:text-foreground">Pronájmy</a></li>
                <li><a href="{{ route('search', ['offer_type' => 'sale']) }}" class="hover:text-foreground">Prodej</a></li>
                <li><a href="{{ route('insights.index') }}" class="hover:text-foreground">Ceny a lokality</a></li>
                <li><a href="{{ route('tools.calculators') }}" class="hover:text-foreground">Hypoteční kalkulačka</a></li>
                <li><a href="{{ route('guides.show', 'checklist-prohlidky') }}" class="hover:text-foreground">Checklist prohlídky</a></li>
            </ul>
        </div>
        <div>
            <h2 class="mb-3 font-sans text-sm font-semibold">Pronajímám a prodávám</h2>
            <ul class="space-y-2 text-sm text-muted-foreground">
                <li><a href="{{ route('listings.create') }}" class="hover:text-foreground">Přidat inzerát</a></li>
                <li><a href="{{ route('owner.estimate') }}" class="hover:text-foreground">Odhad ceny</a></li>
                <li><a href="{{ route('tools.calculators', ['tab' => 'vynos']) }}" class="hover:text-foreground">Výnos z pronájmu</a></li>
                <li><a href="{{ route('guides.show', 'predani-bytu') }}" class="hover:text-foreground">Předání bytu</a></li>
                @if (config('reality.features.memberships'))<li><a href="{{ route('pricing') }}" class="hover:text-foreground">Členství</a></li>@endif
            </ul>
        </div>
        <div>
            <h2 class="mb-3 font-sans text-sm font-semibold">Právní informace</h2>
            <ul class="space-y-2 text-sm text-muted-foreground">
                @foreach (\App\Http\Controllers\PublicController::LEGAL as $slug => $label)
                    <li><a class="hover:text-foreground" href="{{ route('legal', $slug) }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="border-t">
        <div class="container-page flex flex-wrap justify-between gap-2 py-5 text-xs text-muted-foreground">
            <span>© {{ date('Y') }} ONhost Reality. Cenové analýzy jsou orientační a vycházejí z inzerovaných (nikoli prodejních) cen.</span>
            <span>Mapy © <a class="underline" href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · OpenFreeMap</span>
        </div>
    </div>
</footer>
@stack('scripts')
</body>
</html>
