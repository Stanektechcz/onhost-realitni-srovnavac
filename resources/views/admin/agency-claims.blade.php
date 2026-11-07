@extends('layouts.app', ['title' => 'Převzetí profilů RK', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Žádosti o převzetí profilu kanceláře</h1>
    @if (session('status'))<p class="mt-3 text-sm">{{ session('status') }}</p>@endif
    <div class="mt-4 space-y-4">
        @forelse ($claims as $c)
            <div class="card p-4">
                <p class="font-semibold"><a class="underline" href="{{ route('agencies.show', $c->agency) }}">{{ $c->agency->name }}</a> (IČO {{ $c->agency->ico }})</p>
                <p class="text-sm">{{ $c->user->name }} · {{ $c->user->email }} · role {{ $c->user->role }}</p>
                @if ($c->message)<p class="mt-1 whitespace-pre-line text-sm">{{ $c->message }}</p>@endif
                <form method="POST" action="{{ route('admin.agency-claims.decide', $c) }}" class="mt-3 flex gap-2">
                    @csrf
                    <button name="decision" value="approve" class="btn-primary">Schválit</button>
                    <button name="decision" value="reject" class="btn-ghost">Zamítnout</button>
                </form>
            </div>
        @empty
            <p class="card p-6 text-gray-600">Žádné čekající žádosti.</p>
        @endforelse
    </div>
    {{ $claims->links() }}
</div>
@endsection
