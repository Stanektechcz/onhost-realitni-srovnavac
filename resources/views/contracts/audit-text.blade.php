Auditní záznam – nájemní smlouva {{ $c->uuid }}
Verze textu: {{ $c->version }}, stav: {{ $c->statusLabel() }}
@foreach ($c->signatures as $s)

[{{ $s->role === 'landlord' ? 'Pronajímatel' : 'Nájemce' }}]{{ $s->invalidated_at ? ' (NEPLATNÝ – '.$s->invalidated_reason.')' : '' }}
Uživatel ID: {{ $s->user_id }}
Vepsané jméno: {{ $s->typed_name }}
Čas podpisu: {{ $s->signed_at->toIso8601String() }}
IP adresa: {{ $s->ip }}
Prohlížeč: {{ $s->user_agent }}
Verze: {{ $s->version }}
SHA-256 textu: {{ $s->text_sha256 }}
@endforeach
