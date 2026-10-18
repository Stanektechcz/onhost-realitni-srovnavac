@extends('layouts.account', ['title' => 'Moje inzeráty'])
@section('account')
<div class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-3xl font-bold md:text-4xl">Moje inzeráty</h1><a href="{{ route('listings.create') }}" class="btn-primary">+ Nový inzerát</a></div>
<div class="mt-6 space-y-3">
    @forelse ($listings as $l)
        @php($q = \App\Support\ListingQuality::check($l))
        <div class="card flex flex-wrap items-center gap-4 p-4">
            <div class="h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-muted">@if ($p = $l->photos->first())<img src="{{ $p->thumbUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">@else<div class="bg-space grid h-full place-items-center font-display">{{ $l->disposition }}</div>@endif</div>
            <div class="min-w-0 flex-1">
                <a href="{{ route('owner.tools', $l) }}" class="font-semibold hover:text-accent">{{ $l->displayTitle() }}</a>
                <p class="text-sm text-muted-foreground">{{ $l->locationLabel() }} · {{ \App\Models\Listing::formatCzk($l->price_halere) }} · {{ $l->views }} zobrazení</p>
                <div class="mt-1 flex items-center gap-2 text-xs"><div class="h-1.5 w-24 rounded-full bg-muted"><div class="bar-fill h-1.5 rounded-full" style="width: {{ $q['score'] }}%"></div></div><span class="text-muted-foreground">kvalita {{ $q['score'] }} %</span></div>
                @if ($l->status === 'rejected' && $l->moderation_note)<p class="text-sm text-destructive">Zamítnuto: {{ $l->moderation_note }}</p>@endif
            </div>
            <span class="chip">{{ ['draft' => 'koncept', 'pending' => 'čeká na schválení', 'active' => 'aktivní', 'expired' => 'vypršelo', 'rejected' => 'zamítnuto'][$l->status] }}</span>
            <a class="btn-dark btn-sm" href="{{ route('owner.tools', $l) }}">Nástroje</a>
            <a class="btn-ghost btn-sm" href="{{ route('listings.step', [$l, 1]) }}">Upravit</a>
            <form method="POST" action="{{ route('listings.destroy', $l) }}" onsubmit="return confirm('Opravdu smazat inzerát?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-destructive">Smazat</button></form>
        </div>
    @empty
        <div class="card p-10 text-center"><x-icon name="doc" class="mx-auto h-10 w-10 text-muted-foreground" /><p class="mt-3 text-muted-foreground">Zatím nemáte žádné inzeráty.</p><a href="{{ route('owner.estimate') }}" class="btn-outline mt-4">Nejdřív odhad ceny</a></div>
    @endforelse
</div>
<div class="mt-6">{{ $listings->links() }}</div>
@endsection
