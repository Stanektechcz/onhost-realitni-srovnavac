Dobrý den,

pronajímatel {{ $contract->d('landlord.name') }} vám přes {{ config('app.name') }} posílá k podpisu nájemní smlouvu k bytu {{ $contract->d('property.address') }}.

Smlouvu si prohlédněte a podepište zde (je potřeba se přihlásit nebo si zdarma založit účet na tuto e-mailovou adresu):
{{ route('contracts.show', $contract) }}
