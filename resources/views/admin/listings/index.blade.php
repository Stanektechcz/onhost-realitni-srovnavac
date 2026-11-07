@extends('layouts.app', ['title' => 'Inzeráty', 'noindex' => true])
@section('content')
@php($sources = ['internal' => 'vlastní', 'gov' => 'státní', 'imported' => 'importovaný'])
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Inzeráty</h1>
    <p class="mt-1 text-sm text-gray-600">Importované inzeráty jsou <strong>interní</strong> – nikdy se nezobrazují veřejně, slouží jen pro statistiky.</p>
    <form method="GET" class="mt-4 flex flex-wrap items-end gap-2 text-sm">
        @if (! empty($f['user']))<input type="hidden" name="user" value="{{ $f['user'] }}">@endif
        <label class="grow">Hledat (název, město)<input name="q" class="input" value="{{ $f['q'] ?? '' }}"></label>
        <label>Stav
            <select name="status" class="input">
                <option value="">vše</option>
                @foreach (\App\Models\Listing::STATUSES as $s)<option value="{{ $s }}" @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>@endforeach
            </select>
        </label>
        <label>Zdroj
            <select name="source" class="input">
                <option value="">vše</option>
                @foreach ($sources as $k => $v)<option value="{{ $k }}" @selected(($f['source'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select>
        </label>
        <label>Typ nabídky
            <select name="offer_type" class="input">
                <option value="">vše</option>
                @foreach (config('reality.offer_types') as $k => $v)<option value="{{ $k }}" @selected(($f['offer_type'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select>
        </label>
        <button class="btn-primary">Filtrovat</button>
    </form>
    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-plum-50"><tr><th class="p-2">#</th><th class="p-2">Inzerát</th><th class="p-2">Inzerent</th><th class="p-2">Zdroj</th><th class="p-2">Stav</th><th class="p-2">Cena</th><th class="p-2">Platnost do</th></tr></thead>
            <tbody>
                @forelse ($listings as $l)
                    <tr class="border-t">
                        <td class="p-2">{{ $l->id }}</td>
                        <td class="p-2"><a class="underline" href="{{ route('admin.listings.show', $l->id) }}">{{ $l->displayTitle() }}</a><br><span class="text-xs text-gray-500">{{ $l->locationLabel() }}</span></td>
                        <td class="p-2">{{ $l->user?->email ?? ($l->source_name ?: '—') }}</td>
                        <td class="p-2">{{ $sources[$l->source] ?? $l->source }} @if ($l->source === 'imported')<span class="chip">interní</span>@endif</td>
                        <td class="p-2"><span class="chip">{{ $l->status }}</span></td>
                        <td class="p-2">{{ \App\Models\Listing::formatCzk($l->price_halere) }}</td>
                        <td class="p-2">{{ $l->expires_at?->format('j. n. Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-gray-600">Žádné inzeráty.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $listings->links() }}</div>
</div>
@endsection
