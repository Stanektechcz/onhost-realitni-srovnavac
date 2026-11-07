@extends('layouts.app', ['title' => 'O nás', 'canonical' => route('pages.about'), 'description' => 'Kdo provozuje realitní srovnávač ONhost a na jakých zásadách stojí.'])
@section('content')
@include('pages._header', ['eyebrow' => 'O nás', 'h1' => 'O nás', 'lead' => 'Stavíme přehledné místo pro inzerci a srovnání nemovitostí – s daty, u kterých víte, odkud jsou.'])

<section class="container-page max-w-4xl py-16 md:py-20">
    <h2 class="text-3xl font-bold">Naše zásady</h2>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['Žádná provize z ceny', 'Neúčtujeme procenta z ceny nemovitosti ani z nájemného.'],
            ['Jen vlastní nabídky', 'Inzeráty zadávají majitelé a kanceláře přímo u nás. Obsah jiných portálů nepřebíráme.'],
            ['Data se zdrojem', 'U statistik uvádíme, odkud pocházejí – z inzerátů na webu, nebo z otevřených dat ČSÚ.'],
            ['Samostatný web', 'Pro používání nepotřebujete žádný další účet. '.config('oidc.brand_name').' je jen volitelné přihlášení.'],
        ] as [$t, $d])
            <li class="card p-6"><h3 class="text-lg font-bold">{{ $t }}</h3><p class="mt-1 text-sm text-muted-foreground">{{ $d }}</p></li>
        @endforeach
    </ul>

    <h2 class="mt-14 text-3xl font-bold">Provozovatel</h2>
    <div class="card mt-6 p-6">
        <dl class="grid gap-3 sm:grid-cols-[10rem_1fr]">
            <dt class="font-semibold">Provozovatel</dt><dd>{{ \App\Support\LegalEntity::name() }}</dd>
            <dt class="font-semibold">Právní forma</dt><dd>{{ \App\Support\LegalEntity::formLabel() }} ({{ \App\Support\LegalEntity::formDescription() }})</dd>
            @if (\App\Support\LegalEntity::publicAddress() !== '')<dt class="font-semibold">Sídlo</dt><dd>{{ \App\Support\LegalEntity::publicAddress() }}</dd>@endif
            <dt class="font-semibold">IČO</dt><dd>{{ \App\Support\LegalEntity::ico() }}</dd><dt class="font-semibold">DPH</dt><dd>{{ \App\Support\LegalEntity::vatStatus() }}</dd>
            <dt class="font-semibold">Kontakt</dt><dd><a href="{{ route('pages.contact') }}" class="underline">Kontaktní formulář</a></dd>
        </dl>
    </div>
</section>
@endsection
