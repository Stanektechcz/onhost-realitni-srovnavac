@php($photo = $l->photos->first())
<article class="card group overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md">
    <a href="{{ route('listings.show', $l) }}" class="block">
        <div class="aspect-[4/3] bg-plum-50">
            @if ($photo)
                <img src="{{ $photo->thumbUrl() }}" alt="{{ $l->displayTitle() }}" loading="lazy" decoding="async" width="480" height="360" class="h-full w-full object-cover">
            @else
                <div class="flex h-full items-center justify-center bg-gradient-to-br from-plum to-ink font-display text-3xl font-bold text-white/80">{{ $l->disposition ?? config('reality.property_types')[$l->property_type] }}</div>
            @endif
        </div>
        <div class="p-4">
            <p class="font-display text-xl font-bold text-plum">{{ \App\Models\Listing::formatCzk($l->price_halere) }}@if ($l->offer_type === 'rent')<span class="text-sm font-normal text-gray-500"> / měsíc</span>@endif</p>
            <h3 class="mt-1 line-clamp-1 font-sans text-sm font-semibold">{{ $l->displayTitle() }}</h3>
            <p class="line-clamp-1 text-sm text-gray-600">{{ $l->locationLabel() }}</p>
            <div class="mt-2 flex flex-wrap gap-1">
                <span class="chip">PENB {{ $l->penb === 'none' ? 'nevydán' : $l->penb }}</span>
                @if ($l->has_balcony)<span class="chip">balkon</span>@endif
                @if ($l->pets_allowed)<span class="chip">mazlíčci OK</span>@endif
            </div>
        </div>
    </a>
</article>
