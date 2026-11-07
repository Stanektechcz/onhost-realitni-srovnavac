@extends('layouts.app', ['title' => 'Upozornění odhlášeno'])
@section('content')
<div class="container-page py-16 text-center">
    <h1 class="text-3xl font-bold">Upozornění odhlášeno</h1>
    <p class="mt-3 text-muted-foreground">Na hledání „{{ $search->name }}“ už vám nebudeme posílat e-maily. Hledání zůstává uložené v účtu.</p>
    <a href="{{ route('home') }}" class="btn-primary mt-6">Zpět na úvod</a>
</div>
@endsection
