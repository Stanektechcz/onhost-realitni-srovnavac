<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormSubmitted;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Veřejné informační stránky srovnávače (landing podstránky, kontakt, FAQ). */
class PagesController extends Controller
{
    /** Provozovatel – jediný pravdivý údaj o subjektu. */
    public const OPERATOR = ['name' => 'Adrian Staněk', 'form' => 'OSVČ', 'ico' => '08094616'];

    public const TOPICS = [
        'obecne' => 'Obecný dotaz',
        'inzerce' => 'Inzerce a účet',
        'rk' => 'Import pro realitní kanceláře',
        'data' => 'Data a srovnávač',
        'osobni-udaje' => 'Osobní údaje (GDPR)',
    ];

    /** Časté dotazy: [otázka, odpověď]. Jen ověřitelná tvrzení. */
    public const FAQ = [
        ['Kolik stojí inzerce?', 'Základní inzerát přidáte zdarma. Neúčtujeme provizi z ceny nemovitosti ani z nájemného. Pokud budou nabízeny volitelné placené služby, uvidíte jejich cenu předem a nic se neplatí automaticky.'],
        ['Potřebuji k používání účet ONhost ID?', 'Ne. Web funguje samostatně – vyhledávání, srovnávač, kalkulačky i průvodce používáte bez registrace. Pro přidání inzerátu stačí běžný účet s e-mailem. Přihlášení přes ONhost ID je jen volitelná možnost.'],
        ['Odkud pocházejí ceny ve srovnávači?', 'Ze dvou zdrojů: z inzerátů zveřejněných přímo na tomto webu (mediány inzerovaných cen za m²) a z veřejných otevřených dat, například Českého statistického úřadu. U čísel uvádíme zdroj a období. Data z jiných realitních portálů nepřebíráme ani nezobrazujeme.'],
        ['Jsou ceny za m² skutečné prodejní ceny?', 'Mediány z inzerátů jsou inzerované (nabídkové) ceny, ne ceny, za které se nemovitost skutečně prodala. Kupní ceny ukazujeme jen tam, kde je zveřejňuje ČSÚ, a vždy s odkazem na zdroj. Obojí berte jako orientační údaj.'],
        ['Jak porovnám několik nabídek?', 'U nabídky klikněte na „Porovnat“ a přidejte až 4 inzeráty. Na stránce porovnání je uvidíte vedle sebe – cenu, plochu, cenu za m², měsíční náklady, odhad energií a další parametry.'],
        ['Může realitní kancelář importovat nabídky automaticky?', 'Ano. Kanceláře mohou posílat nabídky přes JSON feed API nebo přes XML-RPC rozhraní (RPC2), které podporuje řada realitních CRM. Nabídky se zobrazují pod účtem kanceláře, která za jejich obsah odpovídá.'],
        ['Kdo web provozuje?', 'Web provozuje Adrian Staněk, OSVČ, IČO 08094616. Kontaktovat nás můžete přes kontaktní formulář.'],
        ['Jak nahlásím podezřelý inzerát?', 'Na detailu inzerátu použijte tlačítko „Nahlásit“. Hlášení posuzujeme podle zveřejněných pravidel moderace.'],
    ];

    public function howItWorks()
    {
        return view('pages.how');
    }

    public function owners()
    {
        return view('pages.owners');
    }

    public function agencies()
    {
        return view('pages.agencies');
    }

    public function feedDocs()
    {
        // Veřejný výtah z docs/feed-api.md – bez interních příkazů a konfigurace serveru.
        return view('pages.feed-docs');
    }

    public function comparator()
    {
        return view('pages.comparator');
    }

    public function about()
    {
        return view('pages.about');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function faq()
    {
        return view('pages.faq');
    }

    public function sendContact(Request $request)
    {
        // Honeypot: robot vyplní skryté pole – předstíráme úspěch, nic neukládáme.
        if (filled($request->input('website'))) {
            return redirect()->route('pages.contact')->with('status', 'Děkujeme, zpráva byla odeslána.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'topic' => ['required', 'in:'.implode(',', array_keys(self::TOPICS))],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
            'gdpr' => ['accepted'],
        ], [], ['name' => 'jméno', 'email' => 'e-mail', 'topic' => 'téma', 'body' => 'zpráva', 'gdpr' => 'souhlas']);

        $msg = ContactMessage::create($data + ['ip' => $request->ip()]);
        if ($to = config('reality.contact_email')) {
            Mail::to($to)->queue(new ContactFormSubmitted($msg));
        }

        return redirect()->route('pages.contact')->with('status', 'Děkujeme, zpráva byla odeslána. Odpovíme na uvedený e-mail.');
    }
}
