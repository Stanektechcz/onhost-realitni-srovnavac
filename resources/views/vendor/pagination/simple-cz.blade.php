@if ($paginator->hasPages())
<nav role="navigation" aria-label="Stránkování" class="mt-8 flex items-center justify-between gap-2 text-sm">
    @if ($paginator->onFirstPage())<span class="btn-ghost opacity-40">← Předchozí</span>@else<a class="btn-ghost" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Předchozí</a>@endif
    <span class="text-gray-600">Strana {{ $paginator->currentPage() }}@if (method_exists($paginator, 'lastPage')) z {{ $paginator->lastPage() }}@endif</span>
    @if ($paginator->hasMorePages())<a class="btn-ghost" href="{{ $paginator->nextPageUrl() }}" rel="next">Další →</a>@else<span class="btn-ghost opacity-40">Další →</span>@endif
</nav>
@endif
