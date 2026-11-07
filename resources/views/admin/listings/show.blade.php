@extends('layouts.app', ['title' => 'Inzerát #'.$listing->id, 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <a href="{{ route('admin.listings.index') }}" class="text-sm underline">← Inzeráty</a>
    <h1 class="mt-2 font-display text-2xl font-bold">{{ $listing->displayTitle() }}
        @if ($listing->source === 'imported')<span class="chip align-middle">interní – nezobrazuje se veřejně</span>@endif
    </h1>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="card p-4 text-sm">
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                <dt class="text-gray-500">Stav</dt><dd>{{ $listing->status }}</dd>
                <dt class="text-gray-500">Zdroj</dt><dd>{{ $listing->source }}{{ $listing->source_name ? ' ('.$listing->source_name.')' : '' }}</dd>
                <dt class="text-gray-500">Nabídka</dt><dd>{{ config('reality.offer_types')[$listing->offer_type] ?? $listing->offer_type }}</dd>
                <dt class="text-gray-500">Lokalita</dt><dd>{{ $listing->locationLabel() ?: '—' }}</dd>
                <dt class="text-gray-500">Cena</dt><dd>{{ \App\Models\Listing::formatCzk($listing->price_halere) }}</dd>
                <dt class="text-gray-500">Inzerent</dt><dd>@if ($listing->user)<a class="underline" href="{{ route('admin.users.show', $listing->user) }}">{{ $listing->user->email }}</a>@else — @endif</dd>
                <dt class="text-gray-500">Zveřejněno</dt><dd>{{ $listing->published_at?->format('j. n. Y H:i') ?? '—' }}</dd>
                <dt class="text-gray-500">Platnost do</dt><dd>{{ $listing->expires_at?->format('j. n. Y H:i') ?? '—' }}</dd>
                <dt class="text-gray-500">Zobrazení</dt><dd>{{ $listing->views }}</dd>
                <dt class="text-gray-500">Nahlášení</dt><dd>{{ $listing->reports_count }}</dd>
                <dt class="text-gray-500">Poznámka moderace</dt><dd>{{ $listing->moderation_note ?: '—' }}</dd>
            </dl>
            @if ($listing->isPublic())
                <p class="mt-3"><a class="underline" href="{{ route('listings.show', $listing) }}">Veřejný detail →</a></p>
            @endif
        </div>
        <div class="space-y-4">
            <form method="POST" action="{{ route('admin.listings.extend', $listing->id) }}" class="card grid gap-2 p-4 text-sm">
                @csrf
                <p class="font-semibold">Prodloužit platnost</p>
                <label>Počet dní<input type="number" name="days" min="1" max="365" value="30" class="input" required></label>
                <textarea name="reason" rows="2" class="input" placeholder="Důvod" required></textarea>
                <button class="btn-primary">Prodloužit</button>
            </form>
            @if ($listing->status !== 'expired')
                <form method="POST" action="{{ route('admin.listings.deactivate', $listing->id) }}" class="card grid gap-2 p-4 text-sm">
                    @csrf
                    <p class="font-semibold">Deaktivovat inzerát</p>
                    <textarea name="reason" rows="2" class="input" placeholder="Odůvodnění (uvidí ho inzerent)" required></textarea>
                    <button class="btn-primary">Deaktivovat</button>
                </form>
            @endif
        </div>
    </div>
    <h2 class="mt-8 font-display text-xl font-bold">Audit log</h2>
    @include('admin._audit-table', ['logs' => $audit])
</div>
@endsection
