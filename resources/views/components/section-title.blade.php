@props(['eyebrow' => null, 'title'])
<div {{ $attributes->class('mb-8 max-w-2xl') }}>
    @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
    <h2 class="text-3xl font-bold md:text-4xl">{{ $title }}</h2>
    @if ($slot->isNotEmpty())<p class="mt-3 text-muted-foreground">{{ $slot }}</p>@endif
</div>
