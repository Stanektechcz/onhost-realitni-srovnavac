@extends('layouts.app', ['title' => 'Časté dotazy', 'canonical' => route('pages.faq'), 'description' => 'Odpovědi na časté dotazy k inzerci, srovnávači, datům o cenách a importu pro realitní kanceláře.'])
@section('content')
@include('pages._header', ['eyebrow' => 'Nápověda', 'h1' => 'Časté dotazy', 'lead' => 'Nenašli jste odpověď? Napište nám přes kontaktní formulář.'])
<section class="container-page max-w-3xl py-16 md:py-20">
    @include('pages._faq')
    <a href="{{ route('pages.contact') }}" class="btn-outline mt-8">Zeptat se nás</a>
</section>
@endsection
