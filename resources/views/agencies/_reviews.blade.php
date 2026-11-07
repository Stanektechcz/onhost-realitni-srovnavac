<section class="card p-6" id="recenze">
    <h2 class="font-display text-2xl font-bold">Hodnocení klientů</h2>
    @forelse ($reviews as $r)
        <article class="mt-4 border-t pt-4" itemprop="review" itemscope itemtype="https://schema.org/Review">
            <p class="text-sm"><span class="font-semibold" itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating"><meta itemprop="ratingValue" content="{{ $r->rating }}">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</span>
                · <span itemprop="author">{{ \Illuminate\Support\Str::before($r->user->name ?? 'Uživatel', ' ') }}</span> · {{ $r->created_at->format('j. n. Y') }}
                @if ($r->verified)<span class="chip">Ověřený kontakt</span>@endif
                @if ($r->broker && ! isset($broker)) · makléř <a class="underline" href="{{ route('agencies.broker', [$agency, $r->broker]) }}">{{ $r->broker->name }}</a>@endif
            </p>
            <p class="mt-1 whitespace-pre-line text-sm" itemprop="reviewBody">{{ $r->text }}</p>
            <details class="mt-2 text-xs">
                <summary class="cursor-pointer text-muted-foreground">Nahlásit recenzi</summary>
                <form method="POST" action="{{ route('agency-reviews.report', $r) }}" class="mt-2 space-y-2">
                    @csrf
                    <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                    <select name="reason" class="input" required aria-label="Důvod">@foreach (config('reality.review_report_reasons') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                    <textarea name="description" rows="2" class="input" required minlength="10" placeholder="Odůvodnění"></textarea>
                    @guest <input name="email" type="email" class="input" required placeholder="Váš e-mail"> @endguest
                    <label class="flex items-start gap-2"><input type="checkbox" name="good_faith" value="1" required class="mt-0.5"> Prohlašuji, že informace uvádím v dobré víře a jsou podle mého vědomí přesné a úplné.</label>
                    <button class="btn-ghost">Odeslat nahlášení</button>
                </form>
            </details>
        </article>
    @empty
        <p class="mt-3 text-sm text-muted-foreground">Zatím žádné zveřejněné hodnocení.</p>
    @endforelse
    {{ $reviews->links() }}

    @auth
        @if ($agency->claimed_by_user_id !== auth()->id())
        <form method="POST" action="{{ route('agencies.review', $agency) }}" class="mt-6 space-y-3 border-t pt-4">
            @csrf
            @isset($broker)<input type="hidden" name="broker" value="{{ $broker->slug }}">@endisset
            <h3 class="font-semibold">Napsat hodnocení{{ isset($broker) ? ' makléře' : '' }}</h3>
            <select name="rating" class="input" required aria-label="Hodnocení">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'hvězdička' : ($i < 5 ? 'hvězdičky' : 'hvězdiček') }}</option>@endfor</select>
            <textarea name="text" rows="4" class="input" required minlength="20" maxlength="3000" placeholder="Popište svou zkušenost (bez osobních údajů třetích osob)">{{ old('text') }}</textarea>
            @error('text')<p class="err">{{ $message }}</p>@enderror
            <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="good_faith" value="1" required class="mt-0.5"> Hodnocení vychází z mé skutečné zkušenosti a nejsem v konkurenčním ani jiném střetu zájmů.</label>
            <button class="btn-primary">Odeslat k moderaci</button>
            <p class="text-xs text-muted-foreground">Recenze se zveřejní až po kontrole. Označení „Ověřený kontakt“ získá, pokud jste přes náš portál kontaktovali inzerát kanceláře nebo si rezervovali prohlídku.</p>
        </form>
        @endif
    @else
        <p class="mt-6 border-t pt-4 text-sm"><a class="text-accent underline" href="{{ route('login') }}">Přihlaste se</a> a napište hodnocení.</p>
    @endauth
</section>
