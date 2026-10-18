@extends('layouts.app', ['title' => 'Moderace', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Inzeráty ke schválení</h1>
    <div class="mt-4 space-y-4">
        @forelse ($listings as $l)
            <div class="card p-4" x-data="{ reject: false }">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <a href="{{ route('listings.show', $l) }}" class="font-semibold underline" target="_blank">{{ $l->displayTitle() }}</a>
                        <p class="text-sm text-gray-600">{{ $l->locationLabel() }} · {{ \App\Models\Listing::formatCzk($l->price_halere) }} · PENB {{ $l->penb }} · {{ $l->photos->count() }} fotek · {{ $l->user?->email }}</p>
                        <p class="mt-1 line-clamp-2 text-sm">{{ $l->description }}</p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.moderation.decide', $l) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-primary">Schválit</button></form>
                        <button class="btn-ghost" @click="reject = !reject">Zamítnout…</button>
                    </div>
                </div>
                <form x-cloak x-show="reject" method="POST" action="{{ route('admin.moderation.decide', $l) }}" class="mt-3 space-y-2">
                    @csrf<input type="hidden" name="decision" value="reject">
                    <label class="label" for="rr-{{ $l->id }}">Odůvodnění pro inzerenta (DSA čl. 17)</label>
                    <textarea id="rr-{{ $l->id }}" name="reason" class="input" rows="2" required></textarea>
                    <button class="btn-dark">Zamítnout s odůvodněním</button>
                </form>
            </div>
        @empty
            <p class="card p-6 text-gray-600">Nic ke schválení.</p>
        @endforelse
    </div>
    {{ $listings->links() }}
</div>
@endsection
