@php($photo = $l->photos->first())
@php($ppm2 = $l->pricePerM2())
<article class="card group relative flex flex-col overflow-hidden border-transparent transition duration-300 hover:-translate-y-1 hover:shadow-pop animate-in"
    x-data="{ hl: false }" @mouseenter="$dispatch('card-hover', '{{ $l->slug }}')" @mouseleave="$dispatch('card-hover', null)"
    @pin-hover.window="hl = $event.detail === '{{ $l->slug }}'" :class="hl && 'card-hl'" data-slug="{{ $l->slug }}">
    <div class="relative aspect-[4/3] overflow-hidden bg-muted">
        @if ($photo)
            <img src="{{ $photo->thumbUrl() }}" alt="{{ $l->displayTitle() }}" loading="lazy" decoding="async" width="480" height="360" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
        @else
            <div class="bg-space flex h-full items-center justify-center font-display text-3xl font-bold opacity-90">{{ $l->disposition ?? config('reality.property_types')[$l->property_type] }}</div>
        @endif
        <div class="absolute left-3 top-3 flex gap-1.5">
            <span class="rounded-full bg-card/95 px-2.5 py-1 text-xs font-semibold text-foreground shadow-sm">{{ config('reality.offer_types')[$l->offer_type] ?? $l->offer_type }}</span>
            @if ($l->isBoosted())<span class="rounded-full bg-accent px-2.5 py-1 text-xs font-semibold text-accent-foreground shadow-sm">Top</span>@endif
            @if ($l->published_at && $l->published_at->gt(now()->subDays(2)))<span class="rounded-full bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground shadow-sm">Nové</span>@endif
        </div>
        <button type="button" class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-card/95 text-foreground shadow-sm transition hover:scale-110"
            x-data @click.prevent.stop="$store.compare.toggle('{{ $l->slug }}')" :class="$store.compare.has('{{ $l->slug }}') && '!bg-accent !text-accent-foreground'"
            :aria-pressed="$store.compare.has('{{ $l->slug }}')" aria-label="Přidat k porovnání" title="Porovnat">
            <x-icon name="compare" class="h-4 w-4" />
        </button>
    </div>
    <div class="flex flex-1 flex-col gap-1.5 p-4">
        <div class="flex items-baseline gap-2">
            <p class="font-display text-xl font-bold tracking-tight">{{ \App\Models\Listing::formatCzk($l->price_halere) }}@if ($l->offer_type === 'rent')<span class="text-sm font-medium text-muted-foreground"> / měs.</span>@endif</p>
            @if ($ppm2)<span class="ml-auto text-xs text-muted-foreground">{{ number_format($ppm2, 0, ',', ' ') }} Kč/m²</span>@endif
        </div>
        <h3 class="line-clamp-1 text-[15px] font-semibold">
            <a href="{{ route('listings.show', $l) }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $l->displayTitle() }}</a>
        </h3>
        <p class="line-clamp-1 flex items-center gap-1 text-sm text-muted-foreground"><x-icon name="pin" class="h-3.5 w-3.5 shrink-0" /> {{ $l->locationLabel() }}</p>
        <div class="mt-auto flex flex-wrap items-center gap-1 pt-2">
            <x-penb :value="$l->penb" />
            @if ($l->has_balcony)<span class="chip">balkon</span>@endif
            @if ($l->has_parking)<span class="chip">parkování</span>@endif
            @if ($l->pets_allowed)<span class="chip">mazlíčci</span>@endif
        </div>
    </div>
</article>
