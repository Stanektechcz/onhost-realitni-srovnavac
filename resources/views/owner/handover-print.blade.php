@extends('layouts.app', ['title' => 'Předávací protokol', 'noindex' => true])
@section('content')
<div class="container-page max-w-3xl py-8">
    <div class="no-print mb-4 flex gap-2"><a href="{{ route('owner.handover', $listing) }}" class="btn-ghost btn-sm">← Zpět</a><button type="button" class="btn-primary btn-sm ml-auto" onclick="print()">Vytisknout / PDF</button></div>
    <article class="card space-y-6 p-8 text-sm">
        <header class="border-b pb-4"><h1 class="text-3xl font-bold">Předávací protokol k bytu</h1><p class="mt-1 text-muted-foreground">{{ $listing->displayTitle() }}, {{ $listing->locationLabel() }}</p></header>
        <dl class="grid grid-cols-2 gap-3">
            <div><dt class="text-muted-foreground">Pronajímatel</dt><dd class="font-semibold">{{ $d['landlord_name'] }}</dd></div>
            <div><dt class="text-muted-foreground">Nájemce</dt><dd class="font-semibold">{{ $d['tenant_name'] }}</dd></div>
            <div><dt class="text-muted-foreground">Datum předání</dt><dd class="font-semibold">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('j. n. Y') }}</dd></div>
            <div><dt class="text-muted-foreground">Počet klíčů</dt><dd class="font-semibold">{{ $d['keys'] ?? '—' }}</dd></div>
        </dl>
        <section><h2 class="text-lg font-bold">Stavy měřičů</h2><table class="table-clean mt-2">@foreach (config('reality_content.handover_meters') as $k => $v)<tr><td>{{ $v }}</td><td class="font-semibold">{{ $d['meters'][$k] ?? '—' }}</td></tr>@endforeach</table></section>
        <section><h2 class="text-lg font-bold">Stav místností</h2><table class="table-clean mt-2">@foreach ($d['rooms'] ?? [] as $r => $v)<tr><td class="w-1/3">{{ $r }}</td><td>{{ $v ?: 'bez vad' }}</td></tr>@endforeach</table></section>
        <section><h2 class="text-lg font-bold">Soupis vybavení</h2><p class="mt-2 whitespace-pre-line">{{ $d['inventory'] ?? '—' }}</p></section>
        <section><h2 class="text-lg font-bold">Zjištěné vady</h2><p class="mt-2 whitespace-pre-line">{{ ($d['defects'] ?? null) ?: 'Žádné' }}</p></section>
        <section><h2 class="text-lg font-bold">Checklist</h2><ul class="mt-2 grid grid-cols-2 gap-1">@foreach ($d['checklist'] ?? [] as $c)<li>☑ {{ $c }}</li>@endforeach</ul></section>
        <footer class="grid grid-cols-2 gap-10 pt-12"><div class="border-t pt-2 text-center text-muted-foreground">Podpis pronajímatele</div><div class="border-t pt-2 text-center text-muted-foreground">Podpis nájemce</div></footer>
    </article>
</div>
@endsection
