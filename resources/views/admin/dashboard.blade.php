@extends('layouts.app', ['title' => 'Admin', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    @if (config('reality.automation.enabled') && $heartbeat->stale())
        <div class="card mb-4 border-destructive p-4 text-destructive" role="alert">
            <strong>Plánovač neběží:</strong> denní údržba (reality:maintenance) neproběhla
            {{ $heartbeat->last() ? 'od '.$heartbeat->last()->format('j. n. Y H:i') : 'ani jednou' }} (limit {{ config('reality.automation.heartbeat_max_hours') }} h). Zkontrolujte cron <code>schedule:run</code>.
        </div>
    @endif
    <div class="grid gap-4 sm:grid-cols-5">
        @foreach (['Ke schválení' => $pending, 'Veřejné' => $active, 'Otevřená nahlášení' => $openReports, 'Uživatelé' => $users, 'Importováno (interní)' => $imported] as $k => $v)
            <div class="card p-4"><p class="font-display text-3xl font-bold text-pink">{{ $v }}</p><p class="text-sm">{{ $k }}</p></div>
        @endforeach
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
        @foreach ($kpi as $k => $v)
            <div class="card p-4"><p class="font-display text-2xl font-bold">{{ $v }}</p><p class="text-sm">{{ $k }}</p></div>
        @endforeach
    </div>
    <h2 class="mt-8 font-display text-xl font-bold">Audit log</h2>
    <div class="card mt-2 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-plum-50"><tr><th class="p-2">Čas</th><th class="p-2">Uživatel</th><th class="p-2">Akce</th><th class="p-2">Objekt</th></tr></thead>
            <tbody>@foreach ($audit as $a)<tr class="border-t"><td class="p-2">{{ $a->created_at }}</td><td class="p-2">{{ $a->user?->email ?? 'systém' }}</td><td class="p-2">{{ $a->action }}</td><td class="p-2">{{ class_basename((string) $a->subject_type) }} {{ $a->subject_id }}</td></tr>@endforeach</tbody>
        </table>
    </div>
</div>
@endsection
