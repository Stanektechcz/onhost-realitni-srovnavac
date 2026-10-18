@extends('layouts.app', ['title' => 'Moje inzeráty', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-5xl px-4 py-6">
    <div class="flex items-center justify-between"><h1 class="font-display text-3xl font-bold">Moje inzeráty</h1><a href="{{ route('listings.create') }}" class="btn-primary">+ Nový</a></div>
    <div class="mt-4 space-y-3">
        @forelse ($listings as $l)
            <div class="card flex flex-wrap items-center gap-4 p-4">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('listings.show', $l) }}" class="font-semibold hover:underline">{{ $l->displayTitle() }}</a>
                    <p class="text-sm text-gray-600">{{ $l->locationLabel() }} · {{ \App\Models\Listing::formatCzk($l->price_halere) }} · {{ $l->views }} zobrazení</p>
                    @if ($l->status === 'rejected' && $l->moderation_note)<p class="text-sm text-red-600">Zamítnuto: {{ $l->moderation_note }}</p>@endif
                </div>
                <span class="chip">{{ ['draft' => 'koncept', 'pending' => 'čeká na schválení', 'active' => 'aktivní', 'expired' => 'vypršelo', 'rejected' => 'zamítnuto'][$l->status] }}</span>
                <a class="btn-ghost" href="{{ route('listings.step', [$l, 1]) }}">Upravit</a>
                <form method="POST" action="{{ route('listings.destroy', $l) }}" onsubmit="return confirm('Opravdu smazat inzerát?')">@csrf @method('DELETE')<button class="btn-ghost text-red-600">Smazat</button></form>
            </div>
        @empty
            <p class="card p-6 text-gray-600">Zatím nemáte žádné inzeráty.</p>
        @endforelse
    </div>
    {{ $listings->links() }}
</div>
@endsection
