@props(['s', 'min', 'max', 'mark' => null, 'unit' => 'Kč/m²'])
{{-- Horizontal P25–P75 range with median tick; optional mark (e.g. this listing). --}}
@php($pos = fn ($v) => max(0, min(100, ($v - $min) / max($max - $min, 1) * 100)))
<div {{ $attributes->class('relative h-3 rounded-full bg-muted') }} role="img" aria-label="Rozpětí {{ number_format($s['p25'], 0, ',', ' ') }} až {{ number_format($s['p75'], 0, ',', ' ') }} {{ $unit }}, medián {{ number_format($s['median'], 0, ',', ' ') }}">
    <div class="bar-fill absolute inset-y-0 rounded-full opacity-80" style="left: {{ $pos($s['p25']) }}%; width: {{ max($pos($s['p75']) - $pos($s['p25']), 1.5) }}%"></div>
    <div class="absolute -inset-y-1 w-1 rounded bg-foreground" style="left: calc({{ $pos($s['median']) }}% - 2px)"></div>
    @if ($mark)<div class="absolute -top-1.5 h-6 w-6 -translate-x-1/2 rounded-full border-4 border-card bg-accent shadow" style="left: {{ $pos($mark) }}%"></div>@endif
</div>
