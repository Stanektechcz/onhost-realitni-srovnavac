<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? 'Pronájmy a prodej nemovitostí bez realitky. Ověřené inzeráty od majitelů, hlídací pes a mapa.' }}">
    <meta name="theme-color" content="#12001E">
    @if (! empty($canonical))<link rel="canonical" href="{{ $canonical }}">@endif
    @if (! empty($noindex))<meta name="robots" content="noindex,nofollow">@endif
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? 'Pronájmy a prodej nemovitostí bez realitky.' }}">
    @isset($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endisset
    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
<a href="#main" class="sr-only focus:not-sr-only">Přeskočit na obsah</a>
<header class="sticky top-0 z-40 border-b bg-background/85 backdrop-blur" x-data="{ open: false }">
    <div class="container-page flex h-16 items-center gap-6">
        <x-logo />
        <nav class="hidden items-center gap-1 md:flex" aria-label="Hlavní navigace">
            <a href="{{ route('search', ['offer_type' => 'rent']) }}" class="btn-ghost btn-sm">Pronájem</a>
            <a href="{{ route('search', ['offer_type' => 'sale']) }}" class="btn-ghost btn-sm">Prodej</a>
            @if (config('reality.features.memberships'))<a href="{{ route('pricing') }}" class="btn-ghost btn-sm">Členství</a>@endif
            @auth
                @if (auth()->user()->isAdmin())<a href="{{ route('admin.dashboard') }}" class="btn-ghost btn-sm">Admin</a>@endif
            @endauth
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" class="btn-ghost btn-sm" x-data="{ dark: document.documentElement.classList.contains('dark') }"
                @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); try { localStorage.setItem('theme', dark ? 'dark' : 'light') } catch (e) {}"
                :aria-label="dark ? 'Světlý režim' : 'Tmavý režim'" aria-label="Tmavý režim">
                <svg x-show="!dark" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg x-cloak x-show="dark" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-ghost btn-sm hidden sm:inline-flex">Můj účet</a>
                <form method="POST" action="{{ route('logout') }}" class="hidden md:block">@csrf<button class="btn-ghost btn-sm">Odhlásit</button></form>
            @else
                <a href="{{ route('login') }}" class="btn-ghost btn-sm hidden sm:inline-flex">Přihlásit</a>
            @endauth
            <a href="{{ route('listings.create') }}" class="btn-primary btn-sm hidden sm:inline-flex">+ Přidat inzerát</a>
            <button type="button" class="btn-ghost btn-sm md:hidden" @click="open = !open" :aria-expanded="open" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <nav x-cloak x-show="open" class="container-page flex flex-col gap-1 border-t py-3 md:hidden" aria-label="Mobilní navigace">
        <a href="{{ route('search', ['offer_type' => 'rent']) }}" class="btn-ghost justify-start">Pronájem</a>
        <a href="{{ route('search', ['offer_type' => 'sale']) }}" class="btn-ghost justify-start">Prodej</a>
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
        <div class="container-page pt-4"><div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div></div>
    @endif
    @yield('content')
</main>

<footer class="mt-24 border-t bg-card">
    <div class="container-page grid gap-10 py-12 md:grid-cols-4">
        <div class="md:col-span-2">
            <x-logo />
            <p class="mt-3 max-w-sm text-sm text-muted-foreground">Inzerce nemovitostí přímo od majitelů. Bez provize z ceny nemovitosti.</p>
        </div>
        <div>
            <h2 class="mb-3 font-sans text-sm font-semibold">Portál</h2>
            <ul class="space-y-2 text-sm text-muted-foreground">
                <li><a href="{{ route('search') }}" class="hover:text-foreground">Hledat nemovitost</a></li>
                <li><a href="{{ route('listings.create') }}" class="hover:text-foreground">Přidat inzerát</a></li>
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
        <div class="container-page py-5 text-xs text-muted-foreground">Demo MVP. Veškeré inzeráty v ukázkových datech jsou fiktivní.</div>
    </div>
</footer>
@stack('scripts')
</body>
</html>
