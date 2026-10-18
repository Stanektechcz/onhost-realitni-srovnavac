@extends('layouts.account', ['title' => 'Hlídací psi'])
@section('account')
<h1 class="text-3xl font-bold md:text-4xl">Hlídací psi</h1>
<p class="mt-1 text-sm text-muted-foreground">Nové nabídky odpovídající uloženému hledání vám pošleme e-mailem. Nové hledání uložíte tlačítkem „Hlídat“ ve výsledcích.</p>
<div class="mt-6 space-y-3">
    @forelse ($searches as $s)
        <div class="card flex flex-wrap items-center gap-4 p-5">
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $s->name }}</p>
                <p class="mt-1 flex flex-wrap gap-1 text-xs">@foreach ($s->filters as $k => $v)<span class="chip">{{ $k }}: {{ is_array($v) ? implode(', ', $v) : $v }}</span>@endforeach</p>
            </div>
            <a class="btn-outline btn-sm" href="{{ route('search', $s->filters) }}">Zobrazit</a>
            <form method="POST" action="{{ route('account.searches.destroy', $s) }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-destructive">Smazat</button></form>
        </div>
    @empty
        <div class="card p-10 text-center"><x-icon name="bell" class="mx-auto h-10 w-10 text-muted-foreground" /><p class="mt-3 text-muted-foreground">Zatím žádní hlídací psi.</p><a href="{{ route('search') }}" class="btn-primary mt-4">Nastavit hledání</a></div>
    @endforelse
</div>
@endsection
