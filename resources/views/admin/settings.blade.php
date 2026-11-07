@extends('layouts.app', ['title' => 'Nastavení', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Nastavení</h1>
    <p class="mt-1 text-sm text-gray-600">Jen ke čtení. Příznaky se mění proměnnými prostředí na serveru (soubor .env) a po změně je nutné obnovit cache konfigurace. Tajné údaje (klíče, hesla, client secret) se zde nikdy nezobrazují.</p>

    <div class="card mt-4 p-4 text-sm">
        <p class="font-semibold">Provozovatel</p>
        <p class="mt-1">{{ $legal }}</p>
        <p class="mt-1 text-xs text-gray-500">Nastavují proměnné <code>LEGAL_NAME</code>, <code>LEGAL_FORM</code> (osvc | sro | as), <code>LEGAL_ICO</code>, <code>LEGAL_DIC</code>, <code>LEGAL_VAT_PAYER</code>, <code>LEGAL_REGISTER</code>. Ceny se značí: {{ \App\Support\LegalEntity::priceNote() }}.</p>
    </div>
    <div class="card mt-4 p-4 text-sm">
        <p class="font-semibold">Platební poskytovatel</p>
        <p class="mt-1">{{ $provider }} @if ($providerCode)<span class="font-mono text-xs text-gray-500">({{ $providerCode }})</span>@endif</p>
        <p class="mt-1 text-xs text-gray-500">Nastavuje proměnná <code>REALITY_PAYMENT_PROVIDER</code> (stub | comgate). Platby přesto běží jen se zapnutým příznakem REALITY_FEATURE_PAYMENTS.</p>
    </div>

    @foreach ($groups as $group => $flags)
        <h2 class="mt-6 font-display text-xl font-bold">{{ $group }}</h2>
        <div class="card mt-2 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-plum-50"><tr><th class="p-2">Funkce</th><th class="p-2">Stav</th><th class="p-2">Proměnná prostředí</th></tr></thead>
                <tbody>
                    @foreach ($flags as $flag)
                        <tr class="border-t align-top">
                            <td class="p-2"><span class="font-mono text-xs text-gray-500">{{ $flag['key'] }}</span><br>{{ $flag['description'] }}</td>
                            <td class="p-2"><span @class(['chip', 'border-green-300 text-green-800' => $flag['on']])>{{ $flag['on'] ? 'zapnuto' : 'vypnuto' }}</span></td>
                            <td class="p-2"><code>{{ $flag['env'] }}=true</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection
