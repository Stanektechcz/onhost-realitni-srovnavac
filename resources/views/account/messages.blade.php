@extends('layouts.account', ['title' => 'Zprávy'])
@section('account')
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl font-bold md:text-4xl">Zprávy a dotazy</h1>
    <div class="pill-nav ml-auto">
        <a href="{{ route('account.messages') }}" class="pill {{ $box === 'inbox' ? 'is-active' : '' }}">Doručené</a>
        <a href="{{ route('account.messages', ['box' => 'archiv']) }}" class="pill {{ $box === 'archiv' ? 'is-active' : '' }}">Archiv</a>
    </div>
</div>
@if ($listings->count() > 1)
    <div class="mt-4 flex flex-wrap gap-1.5">
        <a href="{{ route('account.messages', ['box' => $box === 'archiv' ? 'archiv' : null]) }}" class="chip {{ request('listing') ? '' : '!bg-primary !text-primary-foreground' }}">Všechny inzeráty</a>
        @foreach ($listings as $li)<a href="{{ route('account.messages', array_filter(['box' => $box === 'archiv' ? 'archiv' : null, 'listing' => $li->slug])) }}" class="chip {{ request('listing') === $li->slug ? '!bg-primary !text-primary-foreground' : '' }}">{{ \Illuminate\Support\Str::limit($li->displayTitle(), 30) }} ({{ $li->messages_count }})</a>@endforeach
    </div>
@endif
<div class="mt-6 space-y-3">
    @forelse ($messages as $m)
        <article class="card p-5 {{ $unreadIds->contains($m->id) ? 'border-l-4 border-l-accent' : '' }}">
            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span>{{ $m->created_at->format('j. n. Y H:i') }}</span><span>·</span><a class="underline" href="{{ route('listings.show', $m->listing) }}">{{ $m->listing->displayTitle() }}</a>
                @if ($unreadIds->contains($m->id))<span class="badge bg-accent text-accent-foreground">nová</span>@endif
            </div>
            <p class="mt-2 font-semibold">{{ $m->sender_name }} <span class="font-normal text-muted-foreground">({{ \App\Models\Message::mask($m->sender_email) }}{{ $m->sender_phone ? ', tel. uveden' : '' }})</span></p>
            <p class="mt-2 whitespace-pre-line text-sm">{{ $m->body }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a class="btn-primary btn-sm" href="mailto:{{ $m->relayAddress() }}?subject={{ rawurlencode('Re: '.$m->listing->displayTitle()) }}"><x-icon name="mail" class="h-4 w-4" /> Odpovědět přes portál</a>
                @if ($m->sender_phone)<a class="btn-outline btn-sm" href="tel:{{ $m->sender_phone }}">Zavolat</a>@endif
                <form method="POST" action="{{ route('account.messages.archive', $m) }}">@csrf<button class="btn-ghost btn-sm">{{ $m->archived_at ? 'Obnovit' : 'Archivovat' }}</button></form>
            </div>
        </article>
    @empty
        <div class="card p-10 text-center"><x-icon name="mail" class="mx-auto h-10 w-10 text-muted-foreground" /><p class="mt-3 text-muted-foreground">Žádné zprávy.</p></div>
    @endforelse
</div>
<div class="mt-6">{{ $messages->links() }}</div>
@endsection
