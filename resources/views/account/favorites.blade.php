@extends('layouts.account', ['title' => 'Oblíbené'])
@section('account')
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl font-bold md:text-4xl">Oblíbené</h1>
    @if ($listings->count() > 1)<a href="{{ route('compare', ['l' => $listings->take(4)->pluck('slug')->join(',')]) }}" class="btn-outline btn-sm ml-auto"><x-icon name="compare" class="h-4 w-4" /> Porovnat první {{ min(4, $listings->count()) }}</a>@endif
</div>
<div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($listings as $l)
        <div class="flex flex-col gap-2">
            @include('partials.listing-card')
            <form method="POST" action="{{ route('account.favorites.note', $l) }}" class="flex gap-2" x-data="{ dirty: false }">
                @csrf @method('PATCH')
                <label class="sr-only" for="note-{{ $l->id }}">Poznámka</label>
                <input id="note-{{ $l->id }}" name="note" value="{{ $notes[$l->id] ?? '' }}" maxlength="1000" class="input py-2 text-sm" placeholder="Vaše poznámka…" @input="dirty = true">
                <button class="btn-dark btn-sm" x-show="dirty" x-cloak>Uložit</button>
            </form>
        </div>
    @empty
        <div class="card col-span-full p-10 text-center"><x-icon name="heart" class="mx-auto h-10 w-10 text-muted-foreground" /><p class="mt-3 text-muted-foreground">Zatím nic. Srdíčkem u nabídky si ji uložíte.</p><a href="{{ route('search') }}" class="btn-primary mt-4">Hledat</a></div>
    @endforelse
</div>
<div class="mt-6">{{ $listings->links() }}</div>
@endsection
