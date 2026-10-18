@extends('layouts.app', ['title' => 'Nahlášení', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Nahlášení (DSA)</h1>
    <div class="mt-4 space-y-4">
        @forelse ($reports as $r)
            <div class="card p-4">
                <p class="text-xs text-gray-500">{{ $r->created_at->format('j. n. Y H:i') }} · {{ $r->reporter_email ?? 'anonym' }} · <span class="chip">{{ $r->status }}</span></p>
                <p class="mt-1 font-semibold">{{ config('reality.report_reasons')[$r->reason] ?? $r->reason }} — <a class="underline" href="{{ route('listings.show', $r->listing) }}">{{ $r->listing->displayTitle() }}</a></p>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $r->description }}</p>
                @if ($r->status === 'open')
                    <form method="POST" action="{{ route('admin.reports.resolve', $r) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                        @csrf
                        <textarea name="decision_reason" class="input" rows="2" placeholder="Odůvodnění rozhodnutí" required></textarea>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="take_down" value="1"> Stáhnout inzerát</label>
                            <div class="flex gap-2"><button name="status" value="upheld" class="btn-primary">Oprávněné</button><button name="status" value="dismissed" class="btn-ghost">Zamítnout</button></div>
                        </div>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-600">Rozhodnutí: {{ $r->decision_reason }}</p>
                @endif
            </div>
        @empty
            <p class="card p-6 text-gray-600">Žádná nahlášení.</p>
        @endforelse
    </div>
    {{ $reports->links() }}
</div>
@endsection
