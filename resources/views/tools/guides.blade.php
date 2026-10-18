@extends('layouts.app', ['title' => 'Průvodce nájmem a koupí', 'canonical' => route('guides.index')])
@section('content')
<section class="bg-space"><div class="container-page py-12 md:py-16">
    <p class="eyebrow">Průvodce</p>
    <h1 class="text-4xl font-extrabold md:text-6xl">Vše, co potřebujete vědět</h1>
    <p class="mt-3 max-w-2xl opacity-85">Praktické návody pro hledající, kupující i pronajímatele. Informativní obsah, nejde o právní poradenství.</p>
</div></section>
<div class="container-page mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($guides as $slug => $g)
        <a href="{{ route('guides.show', $slug) }}" class="card group p-6 transition hover:-translate-y-1 hover:shadow-pop animate-in">
            <span class="badge-neutral">{{ $g['audience'] }} · {{ $g['minutes'] }} min</span>
            <h2 class="mt-3 text-2xl font-bold group-hover:text-accent">{{ $g['title'] }}</h2>
            <p class="mt-2 text-sm text-muted-foreground">{{ $g['lead'] }}</p>
        </a>
    @endforeach
</div>
@endsection
