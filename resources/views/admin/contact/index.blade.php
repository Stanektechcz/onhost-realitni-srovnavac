@extends('layouts.app', ['title' => 'Kontaktní zprávy', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Kontaktní zprávy</h1>
    <form method="GET" class="mt-4 flex flex-wrap items-end gap-2 text-sm">
        <label>Stav
            <select name="stav" class="input">
                <option value="">vše</option>
                <option value="nove" @selected(($f['stav'] ?? '') === 'nove')>nevyřízené</option>
                <option value="vyrizene" @selected(($f['stav'] ?? '') === 'vyrizene')>vyřízené</option>
            </select>
        </label>
        <label>Téma
            <select name="tema" class="input">
                <option value="">vše</option>
                @foreach ($topics as $k => $label)<option value="{{ $k }}" @selected(($f['tema'] ?? '') === $k)>{{ $label }}</option>@endforeach
            </select>
        </label>
        <button class="btn-primary">Filtrovat</button>
    </form>
    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-plum-50"><tr><th class="p-2">#</th><th class="p-2">Přijato</th><th class="p-2">Od</th><th class="p-2">Téma</th><th class="p-2">Zpráva</th><th class="p-2">Stav</th></tr></thead>
            <tbody>
                @forelse ($messages as $m)
                    <tr class="border-t">
                        <td class="p-2">{{ $m->id }}</td>
                        <td class="p-2">{{ $m->created_at?->format('j. n. Y H:i') }}</td>
                        <td class="p-2">{{ $m->name }}<br><span class="text-gray-500">{{ $m->email }}</span></td>
                        <td class="p-2">{{ $topics[$m->topic] ?? $m->topic }}</td>
                        <td class="p-2"><a class="underline" href="{{ route('admin.contact.show', $m) }}">{{ \Illuminate\Support\Str::limit($m->body, 80) }}</a></td>
                        <td class="p-2"><span class="chip">{{ $m->handled_at ? 'vyřízeno' : 'nové' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-gray-500">Žádné zprávy.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</div>
@endsection
