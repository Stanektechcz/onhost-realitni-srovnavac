@extends('layouts.app', ['title' => 'Recenze realitních kanceláří', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Recenze RK – moderace a nahlášení (DSA)</h1>
    @if (session('status'))<p class="mt-3 text-sm">{{ session('status') }}</p>@endif
    <div class="mt-4 space-y-4">
        @forelse ($reviews as $r)
            @php($target = $r->targetAgency())
            <div class="card p-4">
                <p class="text-xs text-gray-500">{{ $r->created_at->format('j. n. Y H:i') }} · {{ $r->user->email ?? '?' }} · <span class="chip">{{ $r->status }}</span> @if ($r->verified)<span class="chip">ověřený kontakt</span>@endif @if ($r->report_count)<span class="chip">nahlášeno {{ $r->report_count }}×</span>@endif</p>
                <p class="mt-1 font-semibold">{{ str_repeat('★', $r->rating) }} — {{ $r->broker ? $r->broker->name.' / ' : '' }}@if ($target)<a class="underline" href="{{ route('agencies.show', $target) }}">{{ $target->name }}</a>@endif</p>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $r->text }}</p>
                @foreach ($r->reports as $rep)
                    <p class="mt-1 text-xs text-gray-600">Nahlášení ({{ $rep->status }}): {{ config('reality.review_report_reasons')[$rep->reason] ?? $rep->reason }} – {{ $rep->description }}</p>
                @endforeach
                <form method="POST" action="{{ route('admin.agency-reviews.decide', $r) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                    @csrf
                    <textarea name="moderation_note" class="input" rows="2" placeholder="Odůvodnění (povinné při skrytí)"></textarea>
                    <div class="flex gap-2"><button name="status" value="published" class="btn-primary">Zveřejnit</button><button name="status" value="hidden" class="btn-ghost">Skrýt</button></div>
                </form>
            </div>
        @empty
            <p class="card p-6 text-gray-600">Nic ke kontrole.</p>
        @endforelse
    </div>
    {{ $reviews->links() }}
</div>
@endsection
