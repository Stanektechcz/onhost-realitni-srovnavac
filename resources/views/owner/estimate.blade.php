@extends('layouts.account', ['title' => 'Odhad ceny'])
@php($fmt = fn ($v) => number_format($v, 0, ',', ' '))
@section('account')
<h1 class="text-3xl font-bold md:text-4xl">Odhad ceny z porovnatelných nabídek</h1>
<p class="mt-1 text-sm text-muted-foreground">Vypočteno z mediánu ceny za m² v lokalitě (případně dispozici). Orientační, nejde o znalecký posudek.</p>
<form method="GET" class="card mt-6 grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
    <div><label class="label" for="e-offer">Nabídka</label><select id="e-offer" name="offer_type" class="input">@foreach (config('reality.offer_types') as $k => $v)<option value="{{ $k }}" @selected(($data['offer_type'] ?? 'rent') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label" for="e-district">Čtvrť</label><input id="e-district" name="district" list="districts" value="{{ $data['district'] ?? '' }}" class="input"><datalist id="districts">@foreach ($districts as $d)<option value="{{ $d }}">@endforeach</datalist></div>
    <x-field name="city" label="Město" :value="$data['city'] ?? 'Praha'" />
    <div><label class="label" for="e-disp">Dispozice</label><select id="e-disp" name="disposition" class="input"><option value="">Nerozhoduje</option>@foreach (config('reality.dispositions') as $d)<option @selected(($data['disposition'] ?? '') === $d)>{{ $d }}</option>@endforeach</select></div>
    <x-field name="area" label="Plocha m²" type="number" min="5" :value="$data['area'] ?? ''" required />
    <button class="btn-primary lg:col-start-5">Spočítat</button>
</form>
@if ($result)
    <section class="card mt-6 p-6 animate-in">
        @if ($result['enough'])
            <p class="text-sm text-muted-foreground">Podle {{ $result['n'] }} nabídek ({{ $result['scope'] }}) · medián {{ $fmt($result['ppm2']) }} Kč/m²</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-muted p-5"><p class="text-sm text-muted-foreground">Spodní kvartil</p><p class="kpi mt-1">{{ $fmt($result['low']) }} Kč</p></div>
                <div class="bg-space rounded-2xl p-5"><p class="text-sm opacity-80">Medián</p><p class="kpi mt-1">{{ $fmt($result['mid']) }} Kč</p></div>
                <div class="rounded-2xl bg-muted p-5"><p class="text-sm text-muted-foreground">Horní kvartil</p><p class="kpi mt-1">{{ $fmt($result['high']) }} Kč</p></div>
            </div>
            @if (($data['offer_type'] ?? 'rent') === 'rent')<p class="mt-3 text-sm">= měsíční nájem bez poplatků.</p>@endif
        @else
            <p class="text-muted-foreground">Málo dat – v zadané lokalitě zatím není dost porovnatelných nabídek (min. {{ \App\Services\MarketStats::MIN_SAMPLE }}).</p>
        @endif
    </section>
@endif
@endsection
