@extends('layouts.app', ['title' => 'Předplatné', 'noindex' => true])
@section('content')
@php($labels = ['pending' => 'čeká na platbu', 'active' => 'aktivní', 'cancelled' => 'zrušené', 'expired' => 'vypršelé'])
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Platby a členství</h1>
    <div class="mt-4">@include('admin.billing._subnav')</div>
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2 text-sm">
        <label>Stav
            <select name="status" class="input">
                <option value="">vše</option>
                @foreach ($labels as $k => $v)<option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select>
        </label>
        <button class="btn-primary">Filtrovat</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-plum-50"><tr><th class="p-2">#</th><th class="p-2">Uživatel</th><th class="p-2">Tarif</th><th class="p-2">Stav</th><th class="p-2">Od</th><th class="p-2">Do</th></tr></thead>
            <tbody>
                @forelse ($subscriptions as $s)
                    <tr class="border-t">
                        <td class="p-2">{{ $s->id }}</td>
                        <td class="p-2">@if ($s->user)<a class="underline" href="{{ route('admin.users.show', $s->user) }}">{{ $s->user->email }}</a>@else — @endif</td>
                        <td class="p-2">{{ $s->plan?->name ?? '—' }}</td>
                        <td class="p-2"><span class="chip">{{ $labels[$s->status] ?? $s->status }}</span></td>
                        <td class="p-2">{{ $s->starts_at?->format('j. n. Y') ?? '—' }}</td>
                        <td class="p-2">{{ $s->ends_at?->format('j. n. Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-gray-600">Žádné předplatné.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $subscriptions->links() }}</div>
</div>
@endsection
