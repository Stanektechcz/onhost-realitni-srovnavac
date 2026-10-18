# Agent notes
- Tests: `php artisan test` (Pest, SQLite in-memory). Build: `npm run build`.
- Never show `source=imported` listings publicly; always use `Listing::public()`.
- Monetary features stay behind `config('reality.features.*')` flags (default off). Payments go through `PaymentProvider` contract only.
