@php
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateAgent',
        'name' => $broker->name,
        'url' => route('agencies.broker', [$agency, $broker]),
        'description' => $broker->bio,
        'parentOrganization' => ['@type' => 'RealEstateAgent', 'name' => $agency->name, 'url' => route('agencies.show', $agency)],
        'aggregateRating' => $broker->reviews_count ? ['@type' => 'AggregateRating', 'ratingValue' => round($broker->rating_avg, 2), 'reviewCount' => $broker->reviews_count, 'bestRating' => 5, 'worstRating' => 1] : null,
    ]);
@endphp
@extends('layouts.app', ['title' => $broker->name.' – makléř, '.$agency->name, 'description' => 'Profil makléře '.$broker->name.' z kanceláře '.$agency->name.'.', 'canonical' => route('agencies.broker', [$agency, $broker])])
@push('head')
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<div class="container-page space-y-6 py-6">
    <nav class="text-sm text-muted-foreground"><a href="{{ route('agencies.index') }}" class="hover:text-foreground">Realitní kanceláře</a> › <a class="hover:text-foreground" href="{{ route('agencies.show', $agency) }}">{{ $agency->name }}</a></nav>
    @if (session('status'))<p class="rounded-2xl border bg-plum-50 px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <header class="card p-6">
        <h1 class="font-display text-3xl font-extrabold">{{ $broker->name }}</h1>
        <p class="mt-1 text-muted-foreground">Makléř kanceláře <a class="underline" href="{{ route('agencies.show', $agency) }}">{{ $agency->name }}</a>@if ($broker->specialization) · {{ $broker->specialization }}@endif</p>
        @if ($broker->reviews_count)<p class="mt-3"><span class="chip">★ {{ number_format($broker->rating_avg, 1, ',', '') }} z 5 ({{ $broker->reviews_count }})</span></p>@endif
        @if ($broker->bio)<p class="mt-3 whitespace-pre-line">{{ $broker->bio }}</p>@endif
    </header>
    @include('agencies._reviews')
</div>
@endsection
