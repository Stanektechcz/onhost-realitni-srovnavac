{{-- ONhost credit: balance + connect/disconnect. Rendered only while config('onhost.credit.enabled') is on. --}}
@if (config('onhost.credit.enabled'))
    <div class="card p-5" data-onhost-credit>
        <h2 class="font-display text-xl font-bold">Kredit ONhost</h2>
        @if ($onhostConnected ?? false)
            @if (isset($onhostBalance))
                <p class="mt-1 text-sm">Dostupný kredit: <strong>{{ \App\Models\Listing::formatCzk((int) ($onhostBalance['purchased']['minor'] ?? 0)) }}</strong>
                    @if (($onhostBalance['bonus']['balance']['minor'] ?? 0) > 0)
                        + bonus {{ \App\Models\Listing::formatCzk((int) $onhostBalance['bonus']['balance']['minor']) }} (lze použít až do {{ (int) ($onhostBalance['bonus']['cap_pct'] ?? 0) }} % ceny)
                    @endif
                </p>
            @else
                <p class="mt-1 text-sm text-gray-600">{{ $onhostError ?? 'Zůstatek se nepodařilo načíst.' }}</p>
            @endif
            <form method="POST" action="{{ route('onhost.disconnect') }}" class="mt-3">@csrf<button class="btn-ghost text-sm">Zrušit propojení s ONhost ID</button></form>
        @else
            <p class="mt-1 text-sm text-gray-600">Propojte účet s ONhost ID a plaťte služby portálu kreditem ONhost, který můžete použít ve všech projektech ONhost.</p>
            <a href="{{ route('onhost.connect') }}" class="btn-primary mt-3 inline-block">Propojit s ONhost ID</a>
        @endif
    </div>
@endif
