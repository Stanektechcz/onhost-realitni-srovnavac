{{-- Tři cesty: majitel / hledající / realitní kancelář, každá ve 3 krocích. --}}
@php($tracks = [
    ['home', 'Majitel', 'Pronajímám nebo prodávám', [
        ['Přidejte inzerát', 'Průvodce vás provede adresou, parametry a fotkami. Základní inzerát je zdarma.'],
        ['Nastavte cenu podle dat', 'Odhad z porovnatelných nabídek na webu vám pomůže s realistickou cenou.'],
        ['Domluvte prohlídky', 'Dotazy chodí přes chráněnou zprávu, prohlídky si zájemci rezervují online.'],
    ], route('pages.owners'), 'Více pro majitele'],
    ['search', 'Hledající', 'Hledám bydlení', [
        ['Hledejte na mapě', 'Filtrujte podle lokality, ceny a typu, nebo si oblast nakreslete.'],
        ['Porovnejte nabídky', 'Až 4 inzeráty vedle sebe a cena za m² proti mediánu lokality.'],
        ['Ozvěte se majiteli', 'Napište přímo inzerentovi nebo si zarezervujte prohlídku. Hlídací pes vám pošle novinky.'],
    ], route('search'), 'Začít hledat'],
    ['user', 'Realitní kancelář', 'Nabízím nemovitosti klientů', [
        ['Založte účet kanceláře', 'Účet s rolí realitní kancelář a profil v katalogu kanceláří.'],
        ['Napojte svůj software', 'Nabídky posílejte přes JSON feed API nebo XML-RPC rozhraní (RPC2).'],
        ['Sledujte stav importu', 'U každé nabídky vidíte, zda je zveřejněná, čeká na kontrolu, nebo proč byla zamítnuta.'],
    ], route('pages.agencies'), 'Více pro kanceláře'],
])
<div class="grid gap-5 lg:grid-cols-3">
    @foreach ($tracks as [$icon, $who, $title, $steps, $href, $cta])
        <div class="card flex flex-col p-6">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-secondary text-primary"><x-icon :name="$icon" class="h-5 w-5" /></span>
                <div><p class="eyebrow mb-0">{{ $who }}</p><h3 class="text-xl font-bold">{{ $title }}</h3></div>
            </div>
            <ol class="mt-5 grow space-y-4">
                @foreach ($steps as [$s, $d])
                    <li class="flex gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-sm font-bold text-primary-foreground" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div><p class="font-semibold"><span class="sr-only">Krok {{ $loop->iteration }}: </span>{{ $s }}</p><p class="text-sm text-muted-foreground">{{ $d }}</p></div>
                    </li>
                @endforeach
            </ol>
            <a href="{{ $href }}" class="btn-outline mt-6 self-start">{{ $cta }} <x-icon name="arrow" class="h-4 w-4" /></a>
        </div>
    @endforeach
</div>
