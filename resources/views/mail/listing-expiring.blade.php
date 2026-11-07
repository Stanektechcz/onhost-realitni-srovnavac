Dobrý den,

váš inzerát „{{ $listing->displayTitle() }}“ vyprší {{ $listing->expires_at?->format('j. n. Y') }}.
@if ($renewUrl)
Pokud je nabídka stále aktuální, prodlužte ji jedním kliknutím (odkaz platí 7 dní): {{ $renewUrl }}
Případně ve svém účtu: {{ route('listings.show', $listing) }}
@else
Pokud je nabídka stále aktuální, prodlužte ji ve svém účtu: {{ route('listings.show', $listing) }}
@endif

{{ \App\Support\LegalEntity::displayLine() }}
