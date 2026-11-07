@extends('layouts.app', ['title' => $plan->exists ? 'Upravit tarif' : 'Nový tarif', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">{{ $plan->exists ? 'Upravit tarif' : 'Nový tarif' }}</h1>
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="card mt-4 grid max-w-2xl gap-3 p-4 text-sm">
        @csrf
        @if ($plan->exists) @method('PUT') @endif
        <label>Kód (malá písmena, číslice, - a _)<input name="code" class="input" required value="{{ old('code', $plan->code) }}"></label>
        <label>Název<input name="name" class="input" required value="{{ old('name', $plan->name) }}"></label>
        <label>Určeno pro
            <select name="audience" class="input">
                @foreach (['advertiser' => 'inzerenty', 'seeker' => 'hledající'] as $k => $v)<option value="{{ $k }}" @selected(old('audience', $plan->audience) === $k)>{{ $v }}</option>@endforeach
            </select>
        </label>
        <label>Cena v haléřích (celé číslo, 100 hal. = 1 Kč)<input name="price_halere" type="number" min="0" step="1" class="input" required value="{{ old('price_halere', $plan->price_halere ?? 0) }}"></label>
        <label>Délka období (dny)<input name="period_days" type="number" min="1" class="input" required value="{{ old('period_days', $plan->period_days) }}"></label>
        <label>Výhody (jedna na řádek)<textarea name="features" rows="4" class="input">{{ old('features', implode("\n", (array) $plan->features)) }}</textarea></label>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))> Aktivní (nabízí se uživatelům, pokud je zapnuté členství)</label>
        <div class="flex gap-2"><button class="btn-primary">Uložit</button><a href="{{ route('admin.plans.index') }}" class="btn-ghost">Zpět</a></div>
    </form>
</div>
@endsection
