@extends('layouts.app', ['title' => 'Platby', 'noindex' => true])
@section('content')
@php($labels = ['created' => 'založená', 'paid' => 'zaplacená', 'failed' => 'neúspěšná', 'refunded' => 'vrácená'])
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
            <thead class="bg-plum-50"><tr><th class="p-2">#</th><th class="p-2">Čas</th><th class="p-2">Uživatel</th><th class="p-2">Služba</th><th class="p-2">Poskytovatel</th><th class="p-2">Částka</th><th class="p-2">Provize</th><th class="p-2">Stav</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr class="border-t">
                        <td class="p-2">{{ $p->id }}</td>
                        <td class="p-2">{{ $p->created_at->format('j. n. Y H:i') }}</td>
                        <td class="p-2">@if ($p->user)<a class="underline" href="{{ route('admin.users.show', $p->user) }}">{{ $p->user->email }}</a>@else — @endif</td>
                        <td class="p-2">{{ $p->service }}</td>
                        <td class="p-2">{{ $p->provider }}</td>
                        <td class="p-2">{{ \App\Models\Listing::formatCzk($p->amount_halere) }}</td>
                        <td class="p-2">{{ \App\Models\Listing::formatCzk($p->commission_halere) }}</td>
                        <td class="p-2"><span class="chip">{{ $labels[$p->status] ?? $p->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-4 text-gray-600">Žádné platby.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</div>
@endsection
