@extends('layouts.account', ['title' => 'Můj účet'])
@section('account')
<h1 class="text-3xl font-bold md:text-4xl">Dobrý den, {{ auth()->user()->name }}</h1>
<p class="text-sm text-muted-foreground">Role: {{ ['owner' => 'majitel', 'seeker' => 'hledající', 'agency' => 'realitní kancelář', 'developer' => 'developer', 'admin' => 'administrátor'][auth()->user()->role] }}</p>
<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @foreach (array_filter([auth()->user()->canAdvertise() ? [route('listings.mine'), 'Moje inzeráty', $listingCount, 'doc'] : null, auth()->user()->canAdvertise() ? [route('listings.mine'), 'Zobrazení za 30 dní', $views30, 'chart'] : null, [route('account.messages'), 'Nové zprávy', $unread, 'mail'], [route('account.viewings'), 'Nadcházející prohlídky', $viewingCount, 'calendar'], [route('account.favorites'), 'Oblíbené', $favoriteCount, 'heart'], [route('account.searches'), 'Hlídací psi', $searchCount, 'bell']]) as [$href, $label, $n, $i])
        <a href="{{ $href }}" class="stat group transition hover:-translate-y-0.5 hover:shadow-pop"><x-icon :name="$i" class="h-5 w-5 text-accent" /><p class="kpi mt-3">{{ $n }}</p><p class="mt-1 text-sm text-muted-foreground">{{ $label }}</p></a>
    @endforeach
</div>

@if (auth()->user()->canAdvertise())
    <section class="card mt-8 p-6">
        <div class="flex items-center justify-between"><h2 class="font-display text-xl font-bold">Moje inzeráty</h2><a href="{{ route('listings.create') }}" class="btn-primary btn-sm">+ Nový inzerát</a></div>
        <div class="mt-4 divide-y">
            @forelse ($listings as $l)
                @php($q = \App\Support\ListingQuality::check($l))
                <div class="flex flex-wrap items-center gap-4 py-3">
                    <div class="min-w-0 flex-1"><a href="{{ route('owner.tools', $l) }}" class="font-semibold hover:text-accent">{{ $l->displayTitle() }}</a><p class="text-xs text-muted-foreground">{{ $l->views }} zobrazení · kvalita {{ $q['score'] }} %</p></div>
                    <a href="{{ route('owner.tools', $l) }}" class="btn-outline btn-sm">Nástroje</a>
                </div>
            @empty
                <p class="py-4 text-sm text-muted-foreground">Zatím žádné inzeráty. <a class="text-accent underline" href="{{ route('owner.estimate') }}">Zjistěte, za kolik pronajmout</a>.</p>
            @endforelse
        </div>
    </section>
@endif

<section class="mt-8 grid gap-4 md:grid-cols-3">
    @foreach ([[route('tools.calculators'), 'calc', 'Kalkulačky', 'Hypotéka, náklady, výnos, energie'], [route('insights.index'), 'chart', 'Ceny v lokalitách', 'Medián a rozpětí cen za m²'], [route('guides.index'), 'book', 'Průvodce', 'Nájem, koupě, předání bytu']] as [$h, $i, $t, $d])
        <a href="{{ $h }}" class="card flex items-start gap-3 p-5 transition hover:shadow-pop"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-secondary text-primary"><x-icon :name="$i" /></span><span><span class="block font-semibold">{{ $t }}</span><span class="text-sm text-muted-foreground">{{ $d }}</span></span></a>
    @endforeach
</section>

<div class="card mt-8 p-6">
    <h2 class="font-display text-xl font-bold text-destructive">Smazání účtu</h2>
    <p class="text-sm text-muted-foreground">Nevratně smaže účet, inzeráty, oblíbené a hlídací psy. Vyžaduje potvrzení heslem.</p>
    <form method="POST" action="{{ route('account.destroy') }}" class="mt-3" onsubmit="return confirm('Opravdu smazat účet?')">@csrf @method('DELETE')<button class="btn-ghost text-destructive">Smazat účet</button></form>
</div>
@endsection
