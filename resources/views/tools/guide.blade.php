@extends('layouts.app', ['title' => $guide['title'], 'description' => $guide['lead'], 'canonical' => route('guides.show', $slug)])
@section('content')
<article class="container-page max-w-3xl py-10">
    <nav class="text-sm text-muted-foreground"><a href="{{ route('guides.index') }}" class="hover:text-foreground">Průvodce</a> › {{ $guide['audience'] }}</nav>
    <h1 class="mt-2 text-4xl font-extrabold md:text-5xl">{{ $guide['title'] }}</h1>
    <p class="mt-4 text-lg text-muted-foreground">{{ $guide['lead'] }}</p>
    @if (! empty($guide['checklist']))
        <div class="card mt-8 p-6" x-data="checklist(@js($slug), {{ count($guide['items']) }})">
            <div class="flex items-center gap-3"><div class="h-2 flex-1 rounded-full bg-muted"><div class="bar-fill h-2 rounded-full transition-all" :style="'width:' + pct + '%'"></div></div><span class="text-sm font-semibold" x-text="pct + ' %'"></span></div>
            <ul class="mt-5 space-y-2">
                @foreach ($guide['items'] as $i => $item)
                    <li><label class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-muted"><input type="checkbox" class="h-5 w-5 accent-[var(--accent)]" :checked="done.includes({{ $i }})" @change="toggle({{ $i }})"> <span :class="done.includes({{ $i }}) && 'line-through text-muted-foreground'">{{ $item }}</span></label></li>
                @endforeach
            </ul>
            <button type="button" class="btn-outline btn-sm no-print mt-5" onclick="print()">Vytisknout</button>
        </div>
    @else
        <div class="prose-guide mt-6">
            @foreach ($guide['sections'] as [$h, $p])<h2>{{ $h }}</h2><p>{{ $p }}</p>@endforeach
        </div>
    @endif
    <div class="mt-12 grid gap-3 sm:grid-cols-2">
        <a href="{{ route('tools.calculators') }}" class="card flex items-center gap-3 p-4 text-sm hover:shadow-pop"><x-icon name="calc" class="h-5 w-5 text-accent" /> Kalkulačky bydlení</a>
        <a href="{{ route('insights.index') }}" class="card flex items-center gap-3 p-4 text-sm hover:shadow-pop"><x-icon name="chart" class="h-5 w-5 text-accent" /> Ceny v lokalitách</a>
    </div>
</article>
@endsection
