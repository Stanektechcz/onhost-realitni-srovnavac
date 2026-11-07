<div class="card mt-2 overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-plum-50"><tr><th class="p-2">Čas</th><th class="p-2">Uživatel</th><th class="p-2">Akce</th><th class="p-2">Objekt</th><th class="p-2">Údaje</th><th class="p-2">IP</th></tr></thead>
        <tbody>
            @forelse ($logs as $a)
                <tr class="border-t align-top">
                    <td class="whitespace-nowrap p-2">{{ $a->created_at?->format('j. n. Y H:i:s') }}</td>
                    <td class="p-2">{{ $a->user?->email ?? 'systém' }}</td>
                    <td class="p-2 font-mono">{{ $a->action }}</td>
                    <td class="p-2">{{ class_basename((string) $a->subject_type) }} {{ $a->subject_id }}</td>
                    <td class="max-w-sm break-words p-2 font-mono text-xs">{{ $a->data ? json_encode($a->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</td>
                    <td class="p-2">{{ $a->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-gray-600">Žádné záznamy.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
