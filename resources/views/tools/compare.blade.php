@extends('layouts.app', ['title' => 'Porovnání nabídek', 'noindex' => true])
@php($fmt = fn ($v) => number_format($v, 0, ',', ' '))
@section('content')
<div class="container-page py-8">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold md:text-4xl">Porovnání nabídek</h1>
        <button type="button" class="btn-ghost btn-sm ml-auto" x-data @click="$store.compare.clear(); location.href = '{{ route('compare') }}'">Vyprázdnit</button>
    </div>
    @if ($listings->isEmpty())
        <div class="card mt-6 p-10 text-center"><x-icon name="compare" class="mx-auto h-10 w-10 text-muted-foreground" /><p class="mt-3 font-display text-xl font-bold">Zatím nic k porovnání</p><p class="mt-1 text-sm text-muted-foreground">U nabídek klikněte na ikonu porovnání (až 4 nabídky).</p><a href="{{ route('search') }}" class="btn-primary mt-5">Hledat nabídky</a></div>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="table-clean card min-w-[44rem] table-fixed">
                <thead><tr><th class="w-40"></th>@foreach ($listings as $l)<th class="normal-case tracking-normal">
                    <a href="{{ route('listings.show', $l) }}" class="block">
                        <div class="aspect-[4/3] overflow-hidden rounded-xl bg-muted">@if ($p = $l->photos->first())<img src="{{ $p->thumbUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">@else<div class="bg-space grid h-full place-items-center font-display text-2xl">{{ $l->disposition }}</div>@endif</div>
                        <p class="mt-2 text-sm font-semibold text-foreground">{{ $l->displayTitle() }}</p>
                    </a>
                    <button type="button" class="mt-1 text-xs text-muted-foreground underline" x-data @click="$store.compare.toggle('{{ $l->slug }}'); location.href = $store.compare.url">Odebrat</button>
                </th>@endforeach</tr></thead>
                <tbody>
                    @foreach ([
                        'Cena' => fn ($l) => \App\Models\Listing::formatCzk($l->price_halere).($l->offer_type === 'rent' ? ' / měs.' : ''),
                        'Cena/m²' => fn ($l) => $l->pricePerM2() ? $fmt($l->pricePerM2()).' Kč' : '—',
                        'Vs. trh' => fn ($l) => ($market[$l->id]['enough'] ?? false) ? (($market[$l->id]['diff_pct'] > 0 ? '+' : '').$market[$l->id]['diff_pct'].' %') : 'málo dat',
                        'Měsíční náklady' => fn ($l) => $l->monthlyCostHalere() ? \App\Models\Listing::formatCzk($l->monthlyCostHalere()) : '—',
                        'Energie (odhad)' => fn ($l) => ($e = \App\Support\Calculators::energyMonthly($l->penb, (float) $l->area_m2)) ? '~ '.$fmt($e).' Kč/měs.' : '—',
                        'Lokalita' => fn ($l) => $l->locationLabel(),
                        'Dispozice' => fn ($l) => $l->disposition ?? '—',
                        'Plocha' => fn ($l) => $l->area_m2 ? $fmt($l->area_m2).' m²' : '—',
                        'Patro' => fn ($l) => $l->floor ?? '—',
                        'PENB' => fn ($l) => $l->penb === 'none' ? 'nevydán' : $l->penb,
                        'Vybavení' => fn ($l) => config('reality.furnishing')[$l->furnishing] ?? '—',
                        'Balkon' => fn ($l) => $l->has_balcony ? '✓' : '—',
                        'Výtah' => fn ($l) => $l->has_elevator ? '✓' : '—',
                        'Parkování' => fn ($l) => $l->has_parking ? '✓' : '—',
                        'Mazlíčci' => fn ($l) => $l->pets_allowed ? '✓' : '—',
                        'Dostupné od' => fn ($l) => $l->available_from?->format('j. n. Y') ?? 'ihned',
                    ] as $label => $fn)
                        <tr><td class="font-medium text-muted-foreground">{{ $label }}</td>@foreach ($listings as $l)<td class="font-semibold">{{ $fn($l) }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
