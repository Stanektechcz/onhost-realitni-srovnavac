@extends('layouts.app', ['title' => 'Audit log', 'noindex' => true])
@section('content')
<div class="mx-auto max-w-6xl px-4 py-6">
    @include('admin._nav')
    <h1 class="font-display text-2xl font-bold">Audit log</h1>
    <form method="GET" class="mt-4 flex flex-wrap items-end gap-2 text-sm">
        <label>Akce
            <select name="action" class="input">
                <option value="">vše</option>
                @foreach ($actions as $a)<option value="{{ $a }}" @selected(($f['action'] ?? '') === $a)>{{ $a }}</option>@endforeach
            </select>
        </label>
        <label>Uživatel (e-mail)<input name="user" class="input" value="{{ $f['user'] ?? '' }}"></label>
        <label>Typ objektu<input name="subject_type" class="input" placeholder="např. User, Listing" value="{{ $f['subject_type'] ?? '' }}"></label>
        <label>ID objektu<input name="subject_id" type="number" class="input w-28" value="{{ $f['subject_id'] ?? '' }}"></label>
        <label>Od<input name="from" type="date" class="input" value="{{ $f['from'] ?? '' }}"></label>
        <label>Do<input name="to" type="date" class="input" value="{{ $f['to'] ?? '' }}"></label>
        <button class="btn-primary">Filtrovat</button>
    </form>
    @include('admin._audit-table', ['logs' => $logs])
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
