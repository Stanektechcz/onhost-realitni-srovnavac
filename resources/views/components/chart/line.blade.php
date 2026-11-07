@props(['series' => [], 'unit' => 'Kč/m²', 'label' => 'Vývoj'])
{{-- $series: [label => value|null]. Gaps are skipped. --}}
@php
    $vals = array_filter($series, fn ($v) => $v !== null);
    $labels = array_keys($series); $n = count($series);
    $min = $vals ? min($vals) * 0.95 : 0; $max = $vals ? max($vals) * 1.05 : 1; $span = max($max - $min, 1);
    $pts = [];
    foreach (array_values($series) as $i => $v) { if ($v !== null) { $pts[] = [round($i / max($n - 1, 1) * 100, 2), round(38 - ($v - $min) / $span * 34, 2), $v, $labels[$i]]; } }
    $path = collect($pts)->map(fn ($p, $i) => ($i ? 'L' : 'M').$p[0].' '.$p[1])->join(' ');
@endphp
<figure {{ $attributes->class('w-full') }}>
    @if (count($pts) < 2)
        <p class="grid h-40 place-items-center rounded-xl bg-muted text-sm text-muted-foreground">Málo dat pro vývoj v čase</p>
    @else
        <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="h-40 w-full overflow-visible" role="img" aria-label="{{ $label }}">
            <path d="{{ $path }} L{{ end($pts)[0] }} 40 L{{ $pts[0][0] }} 40 Z" fill="#F72585" fill-opacity="0.08"/>
            <path d="{{ $path }}" fill="none" stroke="#F72585" stroke-width="1.2" vector-effect="non-scaling-stroke" stroke-linejoin="round" style="stroke-width:2.5px"/>
            @foreach ($pts as $p)<circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="0.9" fill="#F72585"><title>{{ $p[3] }}: {{ number_format($p[2], 0, ',', ' ') }} {{ $unit }}</title></circle>@endforeach
        </svg>
        <figcaption class="mt-1 flex justify-between text-[10px] text-muted-foreground"><span>{{ $labels[0] }}</span><span>{{ end($labels) }}</span></figcaption>
    @endif
</figure>
