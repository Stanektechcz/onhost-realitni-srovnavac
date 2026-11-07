Dobrý den,

týdenní souhrn portálu ONhost Reality k {{ now()->format('j. n. Y') }}:

@foreach ($kpi as $k => $v)
- {{ $k }}: {{ $v }}
@endforeach
@if ($heartbeatStale)

POZOR: denní údržba (reality:maintenance) neproběhla déle než 48 hodin – zkontrolujte plánovač (cron).
@endif

Administrace: {{ route('admin.dashboard') }}

{{ \App\Support\LegalEntity::displayLine() }}
