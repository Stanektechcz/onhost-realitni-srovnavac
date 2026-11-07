{{-- Locality data: link-outs to official/open sources only (see App\Support\LocalityLinks for terms). Expects $localityLinks. --}}
<section class="mt-8" id="data-o-lokalite">
    <h2 class="font-display text-2xl font-bold">Data o lokalitě</h2>
    <p class="mt-1 text-sm text-muted-foreground">Odkazy do oficiálních a otevřených zdrojů. Otevírají se na webu poskytovatele; data nepřebíráme.</p>
    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ($localityLinks as $lnk)
            <li><a href="{{ $lnk['url'] }}" target="_blank" rel="noopener nofollow" data-locality="{{ $lnk['key'] }}" class="card block p-4 text-sm hover:shadow-pop">
                <span class="font-semibold">{{ $lnk['title'] }} ↗</span>
                <span class="mt-1 block text-muted-foreground">{{ $lnk['desc'] }}</span>
                <span class="mt-2 block text-xs text-muted-foreground">Zdroj: {{ $lnk['source'] }}</span>
            </a></li>
        @endforeach
    </ul>
    <p class="mt-2 text-xs text-muted-foreground">Zdroje ověřeny {{ \Illuminate\Support\Carbon::parse(config('reality_rules.verified'))->format('j. n. Y') }}.</p>
</section>
