@extends('layouts.app', ['title' => 'Nájemní smlouva', 'noindex' => true])
@section('content')
<style>
    .contract-doc h1{font-size:1.6rem;font-weight:700;text-align:center}.contract-doc h2{font-weight:700;margin-top:1rem}.contract-doc p{margin-top:.4rem}
    .contract-sub{text-align:center;font-size:.8rem}.contract-flag{border:1px dashed #b45309;padding:.4rem;font-size:.8rem}
    @media print{@page{size:A4;margin:18mm}.no-print,header,footer,nav{display:none!important}.card{box-shadow:none;border:0;padding:0}body{background:#fff}}
</style>
<div class="container-page max-w-3xl py-8">
    <div class="no-print mb-4 flex gap-2"><a href="{{ route('contracts.show', $c) }}" class="btn-ghost btn-sm">← Zpět</a><button type="button" class="btn-primary btn-sm ml-auto" onclick="print()">Vytisknout / uložit PDF</button></div>
    <article class="card p-8 text-sm">{!! $document !!}@include('contracts.signatures')</article>
</div>
@endsection
