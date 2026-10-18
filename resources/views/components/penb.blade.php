@props(['value', 'size' => 'sm'])
@if ($value === 'none' || $value === null)
    <span {{ $attributes->class(['penb penb-none', 'penb-lg' => $size === 'lg']) }}>PENB nevydán</span>
@else
    <span title="Energetická třída {{ $value }}" {{ $attributes->class(['penb penb-'.$value, 'penb-lg' => $size === 'lg']) }}><span class="sr-only">PENB </span>{{ $value }}</span>
@endif
