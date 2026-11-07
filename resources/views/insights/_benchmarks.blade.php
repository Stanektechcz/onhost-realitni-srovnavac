{{-- Official open-data benchmarks (ČSÚ). Expects $benchmarks (Collection) and optional $ours (median sale Kč/m² from own listings or null). --}}
<section class="card p-6" id="oficialni-data">
    <h2 class="font-display text-xl font-bold">Oficiální statistiky cen</h2>
    <p class="mt-1 text-sm text-muted-foreground">Skutečné kupní ceny z katastru nemovitostí zpracované Českým statistickým úřadem – pro srovnání s inzerovanými cenami.</p>
    @if ($benchmarks->isEmpty())
        <p class="mt-4 grid h-24 place-items-center rounded-xl bg-muted text-sm text-muted-foreground">Oficiální údaje zatím nejsou k dispozici.</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="table-clean min-w-[30rem]">
                <thead><tr><th>Oblast</th><th>Ukazatel</th><th>Období</th><th>Hodnota</th><th>Meziročně</th></tr></thead>
                <tbody>
                    @foreach ($benchmarks as $b)
                        <tr>
                            <td class="font-semibold">{{ $b->area }}</td>
                            <td>{{ $b->indicator }}</td>
                            <td>{{ $b->period }}</td>
                            <td>{{ number_format($b->value, $b->unit === '%' ? 1 : 0, ',', ' ') }} {{ $b->unit === '%' ? '' : $b->unit }}</td>
                            <td class="text-muted-foreground">{{ $b->change !== null ? ($b->change > 0 ? '+' : '').number_format($b->change, 1, ',', ' ').' %' : '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (! empty($ours))
            <p class="mt-3 text-sm">Medián inzerované prodejní ceny v našich nabídkách: <strong>{{ number_format($ours, 0, ',', ' ') }} Kč/m²</strong> (inzerované ceny bývají vyšší než transakční).</p>
        @endif
        <p class="mt-3 text-xs text-muted-foreground">
            Zdroj: Český statistický úřad, DataStat –
            @foreach ($benchmarks->unique('source') as $b)
                <a class="underline" href="{{ 'https://data.csu.gov.cz/datastat/info/SADA/'.substr($b->source, 4, 7) }}" rel="noopener" target="_blank">{{ \App\Services\Benchmarks\CsuBenchmarks::SOURCES[substr($b->source, 4)] ?? $b->source }}</a>@if (! $loop->last), @endif
            @endforeach.
            {{ $benchmarks->first()->license }}. Aktualizováno {{ $benchmarks->max('updated_at')?->format('j. n. Y') }}.
        </p>
    @endif
</section>
