@extends('layouts.app', ['title' => 'Cenové statistiky', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Cenové statistiky nájmů (importovaná data)</h1>
    <p class="text-sm text-gray-600">Pouze pro interní analýzu. Importované záznamy se nikdy nezobrazují veřejně.</p>
    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-plum-50"><tr><th class="p-2">Město</th><th class="p-2">Čtvrť</th><th class="p-2">Dispozice</th><th class="p-2 text-right">Počet</th><th class="p-2 text-right">Průměr</th><th class="p-2 text-right">Min</th><th class="p-2 text-right">Max</th><th class="p-2 text-right">Kč/m²</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr class="border-t"><td class="p-2">{{ $r->city }}</td><td class="p-2">{{ $r->district }}</td><td class="p-2">{{ $r->disposition }}</td><td class="p-2 text-right">{{ $r->n }}</td>
                    <td class="p-2 text-right">{{ \App\Models\Listing::formatCzk((int) $r->avg_price) }}</td><td class="p-2 text-right">{{ \App\Models\Listing::formatCzk((int) $r->min_price) }}</td><td class="p-2 text-right">{{ \App\Models\Listing::formatCzk((int) $r->max_price) }}</td>
                    <td class="p-2 text-right">{{ $r->avg_per_m2 ? \App\Models\Listing::formatCzk((int) $r->avg_per_m2) : '—' }}</td></tr>
            @empty
                <tr><td colspan="8" class="p-4 text-gray-600">Žádná importovaná data. Použijte <code>php artisan reality:import soubor.jsonl</code>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <h2 class="mt-8 font-display text-xl font-bold">Srovnání: veřejné inzeráty portálu (nájem)</h2>
    <div class="card mt-2 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-plum-50"><tr><th class="p-2">Dispozice</th><th class="p-2 text-right">Počet</th><th class="p-2 text-right">Průměr</th></tr></thead>
        <tbody>@foreach ($internal as $r)<tr class="border-t"><td class="p-2">{{ $r->disposition }}</td><td class="p-2 text-right">{{ $r->n }}</td><td class="p-2 text-right">{{ \App\Models\Listing::formatCzk((int) $r->avg_price) }}</td></tr>@endforeach</tbody></table></div>
</div>
@endsection
