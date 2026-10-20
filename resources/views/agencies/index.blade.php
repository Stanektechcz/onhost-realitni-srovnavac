@extends('layouts.app', ['title' => 'Realitní kanceláře – katalog a hodnocení', 'description' => 'Katalog realitních kanceláří v Česku podle veřejných údajů ARES: hodnocení klientů, aktuální nabídky a lokality.', 'canonical' => route('agencies.index')])
@section('content')
<section class="bg-space">
    <div class="container-page py-14 md:py-20">
        <p class="eyebrow">Katalog</p>
        <h1 class="max-w-3xl text-4xl font-extrabold md:text-6xl">Realitní kanceláře</h1>
        <p class="mt-4 max-w-2xl opacity-85">Kanceláře s předmětem činnosti realitní zprostředkování podle veřejného rejstříku ARES. Hodnocení píší přihlášení uživatelé a před zveřejněním je kontroluje moderátor.</p>
    </div>
</section>
<div class="container-page -mt-10 space-y-6 pb-14">
    <form method="GET" action="{{ route('agencies.index') }}" class="card grid gap-3 p-4 md:grid-cols-[1fr_auto_auto_auto]">
        <input name="q" value="{{ $f['q'] ?? '' }}" class="input" placeholder="Název nebo IČO kanceláře" aria-label="Hledat kancelář">
        <select name="kraj" class="input" aria-label="Kraj">
            <option value="">Všechny kraje</option>
            @foreach ($regions as $r)<option value="{{ $r }}" @selected(($f['kraj'] ?? '') === $r)>{{ $r }}</option>@endforeach
        </select>
        <select name="razeni" class="input" aria-label="Řazení">
            @foreach (['hodnoceni' => 'Nejlépe hodnocené', 'nabidky' => 'Nejvíce nabídek', 'nazev' => 'Podle názvu'] as $k => $v)<option value="{{ $k }}" @selected(($f['razeni'] ?? 'hodnoceni') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="btn-primary">Hledat</button>
    </form>

    @if ($agencies->isEmpty())
        <p class="card p-10 text-center text-muted-foreground">Žádná kancelář neodpovídá zadání.</p>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($agencies as $a)
                <a href="{{ route('agencies.show', $a) }}" class="card block p-5 transition hover:shadow-pop">
                    <p class="font-display text-lg font-bold">{{ $a->name }}</p>
                    <p class="text-sm text-muted-foreground">{{ collect([$a->city, $a->region])->filter()->unique()->join(', ') }} · IČO {{ $a->ico }}</p>
                    <p class="mt-3 flex flex-wrap gap-2 text-sm">
                        @if ($a->reviews_count)<span class="chip">★ {{ number_format($a->rating_avg, 1, ',', '') }} ({{ $a->reviews_count }})</span>@else<span class="chip">Bez hodnocení</span>@endif
                        <span class="chip">Nabídek: {{ (int) $a->offers_count }}</span>
                        @if ($a->isVerified())<span class="chip">Ověřená</span>@endif
                    </p>
                </a>
            @endforeach
        </div>
        {{ $agencies->links() }}
    @endif
</div>
@endsection
