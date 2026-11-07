<section class="mt-6 grid gap-4 sm:grid-cols-2 text-sm">
    @foreach (['landlord' => 'Pronajímatel', 'tenant' => 'Nájemce'] as $r => $label)
        @php $s = $c->signatures->whereNull('invalidated_at')->firstWhere('role', $r); @endphp
        <div class="border-t pt-2">
            <div class="text-muted-foreground">{{ $label }}</div>
            @if ($s)
                <div class="font-semibold">Elektronicky podepsáno: {{ $s->typed_name }}</div>
                <div class="text-xs text-muted-foreground">{{ $s->signed_at->format('j. n. Y H:i:s') }} · verze {{ $s->version }} · SHA-256 {{ \Illuminate\Support\Str::limit($s->text_sha256, 16, '…') }}</div>
            @else
                <div class="text-muted-foreground">nepodepsáno</div>
            @endif
        </div>
    @endforeach
</section>
