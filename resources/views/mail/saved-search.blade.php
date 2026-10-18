Dobrý den,

váš hlídací pes „{{ $search->name }}“ našel nové inzeráty:

@foreach ($listings as $l)
- {{ $l->displayTitle() }} — {{ \App\Models\Listing::formatCzk($l->price_halere) }} — {{ $l->locationLabel() }}
  {{ route('listings.show', $l) }}
@endforeach

Hlídací psy spravujete zde: {{ route('account.searches') }}
