Dobrý den,

k vašemu inzerátu „{{ $msg->listing->displayTitle() }}“ přišel nový dotaz přes {{ config('app.name') }}.

Od: {{ $msg->sender_name }} ({{ \App\Models\Message::mask($msg->sender_email) }})

{{ $msg->body }}

Odpovězte přímo na tento e-mail — odpověď doručíme přes portál, vaše adresa zůstane skrytá.
Zprávy najdete také zde: {{ route('account.messages') }}
