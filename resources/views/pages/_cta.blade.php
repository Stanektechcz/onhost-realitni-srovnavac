<section class="container-page py-16 md:py-20">
    <div class="bg-space flex flex-col items-start gap-6 rounded-[calc(var(--radius)+4px)] p-8 md:flex-row md:items-center md:justify-between md:p-12">
        <div>
            <h2 class="text-3xl font-bold">{{ $ctaTitle ?? 'Začněte hned – bez registrace' }}</h2>
            <p class="mt-2 max-w-xl opacity-85">{{ $ctaText ?? 'Hledejte, porovnávejte a počítejte zdarma. Účet potřebujete jen pro přidání inzerátu.' }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('search') }}" class="btn-primary">Hledat nabídky</a>
            <a href="{{ route('listings.create') }}" class="btn inline-flex bg-white/10 ring-1 ring-white/20 hover:bg-white/20">Přidat inzerát zdarma</a>
        </div>
    </div>
</section>
