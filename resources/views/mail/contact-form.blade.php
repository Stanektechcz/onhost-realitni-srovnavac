Nová zpráva z kontaktního formuláře

Jméno: {{ $msg->name }}
E-mail: {{ $msg->email }}
Téma: {{ \App\Http\Controllers\PagesController::TOPICS[$msg->topic] ?? $msg->topic }}

{{ $msg->body }}
