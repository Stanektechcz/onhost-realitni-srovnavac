@extends('layouts.app', ['title' => $title ?? 'Můj účet', 'noindex' => true])
@section('content')
@php($u = auth()->user())
@php($links = array_filter([
    ['dashboard', 'home', 'Přehled'],
    $u->canAdvertise() ? ['listings.mine', 'doc', 'Moje inzeráty'] : null,
    ['account.messages', 'mail', 'Zprávy'],
    ['account.viewings', 'calendar', 'Prohlídky'],
    ['account.favorites', 'heart', 'Oblíbené'],
    ['account.searches', 'bell', 'Hlídací psi'],
    $u->canAdvertise() ? ['owner.estimate', 'tag', 'Odhad ceny'] : null,
    $u->canAdvertise() ? ['owner.contracts', 'clipboard', 'Smlouvy a protokoly'] : null,
]))
<div class="container-page grid gap-8 py-8 lg:grid-cols-[230px_minmax(0,1fr)]">
    <aside class="no-print lg:sticky lg:top-24 lg:self-start">
        <p class="mb-3 hidden px-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground lg:block">{{ $u->name }}</p>
        <nav class="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Účet">
            @foreach ($links as [$r, $i, $t])
                <a href="{{ route($r) }}" class="side-link shrink-0" @if (request()->routeIs($r)) aria-current="page" @endif><x-icon :name="$i" class="h-4 w-4" /> {{ $t }}</a>
            @endforeach
            <a href="{{ route('tools.calculators') }}" class="side-link shrink-0"><x-icon name="calc" class="h-4 w-4" /> Kalkulačky</a>
            @if ($u->isAdmin())<a href="{{ route('admin.dashboard') }}" class="side-link shrink-0"><x-icon name="shield" class="h-4 w-4" /> Administrace</a>@endif
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">@csrf<button class="side-link w-full">Odhlásit</button></form>
        </nav>
    </aside>
    <div class="min-w-0">@yield('account')</div>
</div>
@endsection
