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
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-[#FBF8FE] font-sans text-ink antialiased">
<a href="#main" class="sr-only focus:not-sr-only">Přeskočit na obsah</a>
<header class="sticky top-0 z-30 bg-ink text-white" x-data="{ open: false }">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('home') }}" class="font-display text-2xl font-extrabold tracking-tight">ON<span class="text-pink">host</span> <span class="font-semibold text-plum-100">reality</span></a>
        <nav class="hidden items-center gap-1 text-sm md:flex">
            <a href="{{ route('search', ['offer_type' => 'rent']) }}" class="rounded-lg px-3 py-2 hover:bg-plum">Pronájem</a>
            <a href="{{ route('search', ['offer_type' => 'sale']) }}" class="rounded-lg px-3 py-2 hover:bg-plum">Prodej</a>
            @if (config('reality.features.memberships'))<a href="{{ route('pricing') }}" class="rounded-lg px-3 py-2 hover:bg-plum">Členství</a>@endif
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 hover:bg-plum">Můj účet</a>
                @if (auth()->user()->isAdmin())<a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-2 hover:bg-plum">Admin</a>@endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-2 hover:bg-plum">Odhlásit</button></form>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 hover:bg-plum">Přihlásit</a>
            @endauth
            <a href="{{ route('listings.create') }}" class="btn-primary ml-2">+ Přidat inzerát</a>
        </nav>
        <button class="rounded-lg p-2 md:hidden" @click="open = !open" :aria-expanded="open" aria-label="Menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <nav x-cloak x-show="open" class="border-t border-plum px-4 pb-4 md:hidden">
        <a href="{{ route('search', ['offer_type' => 'rent']) }}" class="block py-2">Pronájem</a>
        <a href="{{ route('search', ['offer_type' => 'sale']) }}" class="block py-2">Prodej</a>
        @auth
            <a href="{{ route('dashboard') }}" class="block py-2">Můj účet</a>
            @if (auth()->user()->isAdmin())<a href="{{ route('admin.dashboard') }}" class="block py-2">Admin</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="py-2">Odhlásit</button></form>
        @else
            <a href="{{ route('login') }}" class="block py-2">Přihlásit</a>
            <a href="{{ route('register') }}" class="block py-2">Registrace</a>
        @endauth
        <a href="{{ route('listings.create') }}" class="btn-primary mt-2 w-full">+ Přidat inzerát</a>
    </nav>
</header>

<main id="main">
    @if (session('status'))
        <div class="mx-auto max-w-7xl px-4 pt-4"><div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div></div>
    @endif
    @yield('content')
</main>

<footer class="mt-16 bg-ink text-sm text-plum-100">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:grid-cols-3">
        <div>
            <p class="font-display text-xl font-bold text-white">ON<span class="text-pink">host</span> reality</p>
            <p class="mt-2">Inzerce nemovitostí přímo od majitelů. Bez provize z ceny nemovitosti.</p>
        </div>
        <ul class="space-y-1">
            @foreach (\App\Http\Controllers\PublicController::LEGAL as $slug => $label)
                <li><a class="hover:text-white" href="{{ route('legal', $slug) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
        <p>Demo MVP. Veškeré inzeráty v ukázkových datech jsou fiktivní.</p>
    </div>
</footer>
@stack('scripts')
</body>
</html>
