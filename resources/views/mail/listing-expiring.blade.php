Dobrý den,

váš inzerát „{{ $listing->displayTitle() }}“ vyprší {{ $listing->expires_at?->format('j. n. Y') }}.
Pokud je nabídka stále aktuální, prodlužte ji ve svém účtu: {{ route('listings.show', $listing) }}

{{ \App\Support\LegalEntity::displayLine() }}
