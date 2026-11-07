Dobrý den,

váš hlídací pes „{{ $search->name }}“ našel {{ $total }} {{ $total === 1 ? 'nový inzerát' : ($total < 5 ? 'nové inzeráty' : 'nových inzerátů') }}:

@foreach ($listings as $l)
- {{ $l->displayTitle() }} — {{ \App\Models\Listing::formatCzk($l->price_halere) }} — {{ $l->locationLabel() }}
  {{ route('listings.show', $l) }}
@endforeach
@if ($total > $listings->count())

Všechny výsledky: {{ route('search', $search->filters ?? []) }}
@endif

Hlídací psy spravujete zde: {{ route('account.searches') }}
Odhlásit upozornění na toto hledání: {{ $unsubscribeUrl }}
