{{-- Accordion časté dotazy + schema.org FAQPage. Parametr: $items = [[otázka, odpověď], …] --}}
@php($items = array_map(fn ($q) => str_replace([':brand', ':operator'], [config('oidc.brand_name'), \App\Support\LegalEntity::displayLine()], $q), $items ?? \App\Http\Controllers\PagesController::FAQ))
@push('head')
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $items)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
<div class="grid gap-3">
    @foreach ($items as [$q, $a])
        <details class="card group p-5" @if ($loop->first) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold">
                <span>{{ $q }}</span>
                <span class="shrink-0 text-muted-foreground transition group-open:rotate-45" aria-hidden="true">+</span>
            </summary>
            <p class="mt-3 text-sm text-muted-foreground">{{ $a }}</p>
        </details>
    @endforeach
</div>
