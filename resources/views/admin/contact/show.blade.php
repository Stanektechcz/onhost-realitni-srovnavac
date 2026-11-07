@extends('layouts.app', ['title' => 'Kontaktní zpráva #'.$m->id, 'noindex' => true])
@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
    @include('admin._nav')
    <p class="text-sm"><a class="underline" href="{{ route('admin.contact.index') }}">← Kontaktní zprávy</a></p>
    <h1 class="font-display text-2xl font-bold">Zpráva #{{ $m->id }}</h1>
    @if (session('status'))<p class="mt-3 rounded-lg bg-plum-50 p-3 text-sm">{{ session('status') }}</p>@endif
    <div class="card mt-4 p-4 text-sm">
        <dl class="grid grid-cols-[10rem_1fr] gap-2">
            <dt class="text-gray-500">Jméno</dt><dd>{{ $m->name }}</dd>
            <dt class="text-gray-500">E-mail</dt><dd><a class="underline" href="mailto:{{ $m->email }}">{{ $m->email }}</a></dd>
            <dt class="text-gray-500">Téma</dt><dd>{{ $topics[$m->topic] ?? $m->topic }}</dd>
            <dt class="text-gray-500">Přijato</dt><dd>{{ $m->created_at?->format('j. n. Y H:i') }}</dd>
            <dt class="text-gray-500">Stav</dt><dd><span class="chip">{{ $m->handled_at ? 'vyřízeno '.$m->handled_at->format('j. n. Y H:i') : 'nové' }}</span></dd>
        </dl>
        <div class="mt-4 whitespace-pre-line border-t pt-4">{{ $m->body }}</div>
        <form method="POST" action="{{ route('admin.contact.status', $m) }}" class="mt-4">
            @csrf
            @if ($m->handled_at)
                <input type="hidden" name="action" value="open"><button class="btn-ghost">Vrátit mezi nevyřízené</button>
            @else
                <input type="hidden" name="action" value="handled"><button class="btn-primary">Označit jako vyřízené</button>
            @endif
        </form>
    </div>
</div>
@endsection
