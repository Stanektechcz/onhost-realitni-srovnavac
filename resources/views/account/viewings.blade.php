@extends('layouts.account', ['title' => 'Prohlídky'])
@section('account')
<h1 class="text-3xl font-bold md:text-4xl">Prohlídky</h1>
<section class="mt-6">
    <h2 class="font-display text-xl font-bold">Moje rezervace</h2>
    <div class="mt-3 space-y-2">
        @forelse ($mine as $s)
            <div class="card flex flex-wrap items-center gap-3 p-4">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-secondary text-center font-display text-sm font-bold leading-tight">{{ $s->starts_at->format('j.') }}<br>{{ $s->starts_at->format('n.') }}</span>
                <div class="min-w-0 flex-1"><p class="font-semibold">{{ $s->starts_at->isoFormat('dddd D. M. YYYY, H:mm') }} ({{ $s->duration_min }} min)</p><a href="{{ route('listings.show', $s->listing) }}" class="text-sm text-muted-foreground underline">{{ $s->listing->displayTitle() }}, {{ $s->listing->locationLabel() }}</a></div>
                @if ($s->starts_at->isFuture())<form method="POST" action="{{ route('viewings.cancel', $s) }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm">Zrušit</button></form>@endif
            </div>
        @empty
            <p class="card p-6 text-sm text-muted-foreground">Nemáte žádné rezervace. Termíny prohlídek najdete v detailu nabídky.</p>
        @endforelse
    </div>
</section>
@if (auth()->user()->canAdvertise())
<section class="mt-10">
    <h2 class="font-display text-xl font-bold">Termíny u mých inzerátů</h2>
    <p class="text-sm text-muted-foreground">Nové termíny vypisujete v Nástrojích u konkrétního inzerátu.</p>
    <div class="card mt-3 overflow-x-auto">
        <table class="table-clean min-w-[36rem]">
            <thead><tr><th>Termín</th><th>Inzerát</th><th>Stav</th><th></th></tr></thead>
            <tbody>
                @forelse ($own as $s)
                    <tr>
                        <td class="font-semibold">{{ $s->starts_at->isoFormat('dd D. M. H:mm') }}</td>
                        <td><a href="{{ route('owner.tools', $s->listing) }}" class="underline">{{ \Illuminate\Support\Str::limit($s->listing->displayTitle(), 40) }}</a></td>
                        <td>@if ($s->booked_by)<span class="badge-good">{{ $s->booked_name }}{{ $s->booked_phone ? ', '.$s->booked_phone : '' }}</span>@if ($s->booked_note)<p class="mt-1 text-xs text-muted-foreground">{{ $s->booked_note }}</p>@endif @else<span class="badge-neutral">volno</span>@endif</td>
                        <td class="text-right">@if ($s->booked_by)<form method="POST" action="{{ route('viewings.cancel', $s) }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm">Zrušit rezervaci</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted-foreground">Žádné vypsané termíny.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
