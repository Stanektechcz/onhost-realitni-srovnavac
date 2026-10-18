@php($photo = $l->photos->first())
<article class="card group relative flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-pop">
    <div class="relative aspect-[4/3] overflow-hidden bg-muted">
        @if ($photo)
            <img src="{{ $photo->thumbUrl() }}" alt="{{ $l->displayTitle() }}" loading="lazy" decoding="async" width="480" height="360" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="bg-space flex h-full items-center justify-center font-display text-3xl font-bold opacity-90">{{ $l->disposition ?? config('reality.property_types')[$l->property_type] }}</div>
        @endif
        <span class="absolute left-3 top-3 rounded-full bg-card/95 px-2.5 py-1 text-xs font-semibold text-foreground">{{ config('reality.offer_types')[$l->offer_type] ?? $l->offer_type }}</span>
    </div>
    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex items-center gap-2 text-xs text-muted-foreground">
            @if ($l->disposition)<span class="font-semibold text-foreground">{{ $l->disposition }}</span>@endif
            @if ($l->area_m2 ?? null)<span aria-hidden="true">·</span><span>{{ $l->area_m2 }} m²</span>@endif
            <span class="ml-auto"><x-penb :value="$l->penb" /></span>
        </div>
        <h3 class="line-clamp-2 text-lg font-semibold">
            <a href="{{ route('listings.show', $l) }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $l->displayTitle() }}</a>
        </h3>
        <p class="line-clamp-1 text-sm text-muted-foreground">{{ $l->locationLabel() }}</p>
        <div class="mt-auto flex flex-wrap items-end gap-1 pt-2">
            <p class="mr-auto font-display text-xl font-bold">{{ \App\Models\Listing::formatCzk($l->price_halere) }}@if ($l->offer_type === 'rent')<span class="text-sm font-medium text-muted-foreground"> / měsíc</span>@endif</p>
            @if ($l->has_balcony)<span class="chip">balkon</span>@endif
            @if ($l->pets_allowed)<span class="chip">mazlíčci OK</span>@endif
        </div>
    </div>
</article>
