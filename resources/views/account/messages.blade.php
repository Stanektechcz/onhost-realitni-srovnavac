@extends('layouts.app', ['title' => 'Zprávy', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
    <h1 class="font-display text-3xl font-bold">Přijaté zprávy</h1>
    <div class="mt-4 space-y-3">
        @forelse ($messages as $m)
            <article class="card p-4">
                <p class="text-xs text-gray-500">{{ $m->created_at->format('j. n. Y H:i') }} · k inzerátu <a class="underline" href="{{ route('listings.show', $m->listing) }}">{{ $m->listing->displayTitle() }}</a></p>
                <p class="mt-1 font-semibold">{{ $m->sender_name }} <span class="font-normal text-gray-500">({{ \App\Models\Message::mask($m->sender_email) }})</span></p>
                <p class="mt-2 whitespace-pre-line text-sm">{{ $m->body }}</p>
                <a class="btn-ghost mt-3" href="mailto:{{ $m->relayAddress() }}">Odpovědět přes portál</a>
            </article>
        @empty
            <p class="card p-6 text-gray-600">Žádné zprávy.</p>
        @endforelse
    </div>
    {{ $messages->links() }}
</div>
@endsection
