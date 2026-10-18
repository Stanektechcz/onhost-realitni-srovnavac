@extends('layouts.app', ['title' => 'Hlídací psi', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
    <h1 class="font-display text-3xl font-bold">Hlídací psi</h1>
    <p class="text-sm text-gray-600">Každé ráno vám pošleme e-mail s novými inzeráty. Nové hlídání uložíte z <a class="text-pink underline" href="{{ route('search') }}">vyhledávání</a>.</p>
    <div class="mt-4 space-y-3">
        @forelse ($searches as $s)
            <div class="card flex items-center gap-4 p-4">
                <div class="flex-1">
                    <a class="font-semibold hover:underline" href="{{ route('search', $s->filters) }}">{{ $s->name }}</a>
                    <p class="text-xs text-gray-500">{{ collect($s->filters)->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(', ', $v) : $v))->join(' · ') ?: 'bez filtrů' }}</p>
                </div>
                <form method="POST" action="{{ route('account.searches.destroy', $s) }}">@csrf @method('DELETE')<button class="btn-ghost text-red-600">Smazat</button></form>
            </div>
        @empty
            <p class="card p-6 text-gray-600">Nemáte žádného hlídacího psa.</p>
        @endforelse
    </div>
</div>
@endsection
