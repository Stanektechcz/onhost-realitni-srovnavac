@php($topic = old('topic', array_key_exists((string) request('tema'), \App\Http\Controllers\PagesController::TOPICS) ? request('tema') : 'obecne'))
@extends('layouts.app', ['title' => 'Kontakt', 'canonical' => route('pages.contact'), 'description' => 'Napište nám – dotazy k inzerci, srovnávači, osobním údajům nebo importu pro realitní kanceláře.', 'showErrorSummary' => false])
@section('content')
@include('pages._header', ['eyebrow' => 'Kontakt', 'h1' => 'Kontakt', 'lead' => 'Máte dotaz k inzerci, datům nebo importu? Napište nám, odpovíme e-mailem.'])

<section class="container-page grid gap-8 py-16 md:py-20 lg:grid-cols-[2fr_1fr]">
    <form method="POST" action="{{ route('pages.contact.send') }}" class="card space-y-5 p-6 md:p-8" novalidate>
        @csrf
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-destructive" role="alert">Formulář obsahuje chyby, opravte prosím označená pole.</div>
        @endif
        {{-- Honeypot: skryté pole pro roboty --}}
        <div class="hidden" aria-hidden="true"><label for="c-website">Web</label><input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="c-name" class="label">Jméno</label>
                <input id="c-name" name="name" class="input" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="c-name-err" @enderror>
                @error('name')<p id="c-name-err" class="mt-1 text-sm text-destructive">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="c-email" class="label">E-mail</label>
                <input id="c-email" name="email" type="email" class="input" value="{{ old('email') }}" required maxlength="190" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="c-email-err" @enderror>
                @error('email')<p id="c-email-err" class="mt-1 text-sm text-destructive">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="c-topic" class="label">Téma</label>
            <select id="c-topic" name="topic" class="input">
                @foreach (\App\Http\Controllers\PagesController::TOPICS as $k => $v)<option value="{{ $k }}" @selected($topic === $k)>{{ $v }}</option>@endforeach
            </select>
            @error('topic')<p class="mt-1 text-sm text-destructive">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="c-body" class="label">Zpráva</label>
            <textarea id="c-body" name="body" rows="6" class="input" required minlength="10" maxlength="3000" @error('body') aria-invalid="true" aria-describedby="c-body-err" @enderror>{{ old('body') }}</textarea>
            @error('body')<p id="c-body-err" class="mt-1 text-sm text-destructive">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="gdpr" value="1" class="mt-1" @checked(old('gdpr')) required>
                <span>Souhlasím se zpracováním uvedených údajů za účelem vyřízení dotazu podle <a href="{{ route('legal', 'ochrana-osobnich-udaju') }}" class="underline">zásad ochrany osobních údajů</a>.</span></label>
            @error('gdpr')<p class="mt-1 text-sm text-destructive">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary"><x-icon name="mail" class="h-4 w-4" /> Odeslat zprávu</button>
    </form>

    <aside class="space-y-4">
        <div class="card p-6">
            <h2 class="text-lg font-bold">Provozovatel</h2>
            <p class="mt-2 text-sm">{{ \App\Support\LegalEntity::name() }}, {{ \App\Support\LegalEntity::formLabel() }}<br>IČO {{ \App\Support\LegalEntity::ico() }}, {{ \App\Support\LegalEntity::vatStatus() }}@if (\App\Support\LegalEntity::register() !== '')<br>{{ \App\Support\LegalEntity::register() }}@endif</p>
        </div>
        <div class="card p-6 text-sm">
            <h2 class="text-lg font-bold">Než napíšete</h2>
            <ul class="mt-2 space-y-2">
                <li><a href="{{ route('pages.faq') }}" class="underline">Časté dotazy</a></li>
                <li><a href="{{ route('pages.agencies.docs') }}" class="underline">Dokumentace importu pro RK</a></li>
                <li>Podezřelý inzerát nahlaste přímo na jeho detailu.</li>
            </ul>
        </div>
    </aside>
</section>
@endsection
