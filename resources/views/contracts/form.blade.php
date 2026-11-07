@extends('layouts.account', ['title' => $contract ? 'Upravit nájemní smlouvu' : 'Nová nájemní smlouva'])
@section('account')
@php
    $val = fn ($k) => old($k, data_get($v, $k));
    $in = function ($k, $label, $type = 'text', $attrs = '') use ($val) {
        $id = 'c-'.str_replace('.', '-', $k);
        $name = preg_replace('/\.([^.]+)/', '[$1]', $k);
        $err = session('errors')?->first($k);
        return '<div><label class="label" for="'.$id.'">'.e($label).'</label><input id="'.$id.'" name="'.e($name).'" type="'.$type.'" value="'.e($val($k)).'" class="input" '.$attrs.'>'.($err ? '<p class="err">'.e($err).'</p>' : '').'</div>';
    };
@endphp
<a href="{{ $contract ? route('contracts.show', $contract) : route('owner.tools', $listing) }}" class="text-sm text-muted-foreground hover:text-foreground">← {{ $listing->displayTitle() }}</a>
<h1 class="mt-1 text-3xl font-bold md:text-4xl">{{ $contract ? 'Upravit nájemní smlouvu' : 'Nová nájemní smlouva' }}</h1>
@if ($contract && $contract->validSignatures()->exists())
    <p class="mt-2 rounded border border-amber-400 bg-amber-50 p-3 text-sm">Smlouva už je podepsaná. Uložením změn všechny podpisy pozbydou platnosti a smlouva se vrátí do konceptu.</p>
@endif
<form method="POST" action="{{ $contract ? route('contracts.update', $contract) : route('contracts.store', $listing) }}" class="mt-6 space-y-6" x-data="{ term: @js($val('terms.term') ?? 'fixed') }">
    @csrf @if ($contract) @method('PUT') @endif
    @foreach (['landlord' => 'Pronajímatel', 'tenant' => 'Nájemce'] as $p => $label)
    <section class="card grid gap-4 p-6 sm:grid-cols-2">
        <h2 class="font-display text-xl font-bold sm:col-span-2">{{ $label }}</h2>
        {!! $in("$p.name", 'Jméno a příjmení / název', 'text', 'required maxlength=160') !!}
        {!! $in("$p.address", 'Trvalé bydliště / sídlo', 'text', 'required maxlength=255') !!}
        {!! $in("$p.birth_or_ico", 'Datum narození nebo IČO', 'text', 'required maxlength=40') !!}
        {!! $in("$p.email", 'E-mail'.($p === 'tenant' ? ' (na něj pošleme smlouvu k podpisu)' : ''), 'email', 'required') !!}
        {!! $in("$p.phone", 'Telefon', 'tel', 'maxlength=30') !!}
    </section>
    @endforeach
    <section class="card grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
        <h2 class="font-display text-xl font-bold sm:col-span-2 lg:col-span-4">Byt</h2>
        {!! $in('property.address', 'Adresa', 'text', 'required') !!}
        {!! $in('property.unit_no', 'Číslo jednotky') !!}
        {!! $in('property.disposition', 'Dispozice') !!}
        {!! $in('property.area_m2', 'Plocha (m²)', 'number', 'step=0.01 min=1') !!}
    </section>
    <section class="card grid gap-4 p-6 sm:grid-cols-3">
        <h2 class="font-display text-xl font-bold sm:col-span-3">Platby</h2>
        {!! $in('rent', 'Nájemné (Kč/měsíc)', 'number', 'required min=1') !!}
        {!! $in('fees', 'Zálohy na služby (Kč/měsíc)', 'number', 'min=0') !!}
        {!! $in('deposit', 'Kauce (Kč, max. 3× nájemné)', 'number', 'min=0') !!}
        {!! $in('terms.payment_day', 'Den splatnosti', 'number', 'required min=1 max=28') !!}
        {!! $in('terms.account', 'Číslo účtu pronajímatele / IBAN', 'text', 'required') !!}
    </section>
    <section class="card grid gap-4 p-6 sm:grid-cols-3">
        <h2 class="font-display text-xl font-bold sm:col-span-3">Podmínky</h2>
        {!! $in('terms.start_date', 'Začátek nájmu', 'date', 'required') !!}
        <div><label class="label" for="c-term">Doba nájmu</label>
            <select id="c-term" name="terms[term]" class="input" x-model="term"><option value="fixed">Určitá</option><option value="indefinite">Neurčitá</option></select></div>
        <div x-show="term === 'fixed'">{!! $in('terms.end_date', 'Konec nájmu', 'date') !!}</div>
        {!! $in('terms.notice_months', 'Výpovědní doba (měsíce, min. 3)', 'number', 'required min=3 max=12') !!}
        {!! $in('terms.occupants', 'Počet osob', 'number', 'required min=1 max=20') !!}
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="terms[pets]" value="0"><input type="checkbox" name="terms[pets]" value="1" @checked($val('terms.pets'))> Zvířata povolena</label>
        <div class="sm:col-span-3"><label class="label" for="c-meters">Stavy měřidel (lze doplnit v předávacím protokolu)</label><textarea id="c-meters" name="terms[meter_readings]" rows="2" class="input">{{ $val('terms.meter_readings') }}</textarea></div>
        <div class="sm:col-span-3"><label class="label" for="c-special">Zvláštní ujednání</label><textarea id="c-special" name="terms[special_terms]" rows="4" class="input">{{ $val('terms.special_terms') }}</textarea>@error('terms.special_terms')<p class="err">{{ $message }}</p>@enderror</div>
    </section>
    <button class="btn-primary">Uložit a zobrazit náhled</button>
</form>
@endsection
