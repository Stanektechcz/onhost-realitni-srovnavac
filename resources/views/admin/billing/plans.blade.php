@extends('layouts.app', ['title' => 'Tarify', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-display text-2xl font-bold">Platby a členství</h1>
        <a href="{{ route('admin.plans.create') }}" class="btn-primary">Nový tarif</a>
    </div>
    <div class="mt-4">@include('admin.billing._subnav')</div>
    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-plum-50"><tr><th class="p-2">Kód</th><th class="p-2">Název</th><th class="p-2">Pro</th><th class="p-2">Cena</th><th class="p-2">Období</th><th class="p-2">Stav</th><th class="p-2"></th></tr></thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr class="border-t">
                        <td class="p-2 font-mono">{{ $plan->code }}</td>
                        <td class="p-2">{{ $plan->name }}</td>
                        <td class="p-2">{{ $plan->audience === 'seeker' ? 'hledající' : 'inzerenty' }}</td>
                        <td class="p-2">{{ \App\Models\Listing::formatCzk($plan->price_halere) }} <span class="text-xs text-gray-500">({{ $plan->price_halere }} hal.)</span></td>
                        <td class="p-2">{{ $plan->period_days }} dní</td>
                        <td class="p-2"><span class="chip">{{ $plan->is_active ? 'aktivní' : 'vypnutý' }}</span></td>
                        <td class="p-2 text-right">
                            <a class="underline" href="{{ route('admin.plans.edit', $plan) }}">Upravit</a>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Opravdu smazat tarif?')">@csrf @method('DELETE')<button class="ml-2 text-destructive underline">Smazat</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-gray-600">Žádné tarify.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
