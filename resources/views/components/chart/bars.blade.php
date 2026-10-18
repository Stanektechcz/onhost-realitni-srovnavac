@props(['data' => [], 'labels' => [], 'unit' => '', 'height' => 160, 'label' => 'Graf'])
{{-- Lightweight responsive SVG bar chart. $data: list of numbers, $labels: list of strings. --}}
@php
    $vals = array_values($data); $labs = array_values($labels);
    $n = max(count($vals), 1); $max = max(array_merge([1], $vals));
    $w = 100 / $n;
@endphp
<figure {{ $attributes->class('w-full') }}>
    <svg viewBox="0 0 100 {{ $height / 4 }}" preserveAspectRatio="none" class="h-40 w-full" role="img" aria-label="{{ $label }}">
        @foreach ($vals as $i => $v)
            @php($h = $v / $max * ($height / 4 - 2))
            <rect x="{{ $i * $w + $w * 0.12 }}" y="{{ $height / 4 - $h }}" width="{{ $w * 0.76 }}" height="{{ max($h, 0.3) }}" rx="0.8" fill="url(#bargrad)"><title>{{ $labs[$i] ?? '' }}: {{ number_format($v, 0, ',', ' ') }} {{ $unit }}</title></rect>
        @endforeach
        <defs><linearGradient id="bargrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F72585"/><stop offset="1" stop-color="#7209b7"/></linearGradient></defs>
    </svg>
    @if ($labs)
        <figcaption class="mt-1 flex justify-between text-[10px] text-muted-foreground"><span>{{ $labs[0] }}</span><span>{{ $labs[intdiv(count($labs), 2)] ?? '' }}</span><span>{{ end($labs) }}</span></figcaption>
    @endif
</figure>
