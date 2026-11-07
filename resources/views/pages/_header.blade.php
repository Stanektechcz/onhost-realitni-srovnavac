{{-- Záhlaví podstránky. Parametry: $eyebrow, $h1, $lead --}}
<section class="bg-space relative overflow-hidden">
    <div class="pointer-events-none absolute -right-40 -top-40 h-[28rem] w-[28rem] rounded-full bg-accent/30 blur-3xl" aria-hidden="true"></div>
    <div class="container-page relative py-14 md:py-20">
        <nav aria-label="Drobečková navigace" class="mb-4 text-sm opacity-80"><a href="{{ route('home') }}" class="underline">Úvod</a> <span aria-hidden="true">/</span> <span aria-current="page">{{ $h1 }}</span></nav>
        @isset($eyebrow)<p class="badge mb-4 bg-white/10 px-3 py-1 text-xs text-on-space ring-1 ring-white/15">{{ $eyebrow }}</p>@endisset
        <h1 class="max-w-3xl text-4xl font-extrabold md:text-6xl">{{ $h1 }}</h1>
        @isset($lead)<p class="mt-5 max-w-2xl text-lg opacity-85">{{ $lead }}</p>@endisset
    </div>
</section>
