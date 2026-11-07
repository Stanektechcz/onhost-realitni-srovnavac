<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OnhostIdController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Ecosystem\EcosystemController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\OnhostCreditController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RememberEcosystemReferral;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\OwnerToolsController;
use App\Http\Controllers\ToolsController;
use App\Http\Controllers\ViewingController;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/hledat', [SearchController::class, 'index'])->name('search');
Route::get('/api/mapa', [SearchController::class, 'map'])->middleware('throttle:60,1')->name('search.map');
Route::get('/hlidaci-pes/{search}/odhlasit', \App\Http\Controllers\SavedSearchUnsubscribeController::class)->middleware(['signed', 'throttle:20,1'])->name('searches.unsubscribe');
Route::get('/api/okoli', \App\Http\Controllers\AmenitiesController::class)->middleware('throttle:30,1')->name('amenities');
Route::get('/inzerat/{listing}', [ListingController::class, 'show'])->name('listings.show');
Route::post('/inzerat/{listing}/zprava', [ContactController::class, 'message'])->middleware('throttle:contact')->name('listings.message');
Route::post('/inzerat/{listing}/nahlasit', [ContactController::class, 'report'])->middleware('throttle:contact')->name('listings.report');
Route::get('/clenstvi', [PublicController::class, 'pricing'])->middleware(RememberEcosystemReferral::class)->name('pricing');
Route::get('/info/{page}', [PublicController::class, 'legal'])->name('legal');
Route::get('/sitemap.xml', [PublicController::class, 'sitemap'])->name('sitemap');
Route::get('/sitemap-{page}.xml', [PublicController::class, 'sitemapPart'])->whereNumber('page')->name('sitemap.part');
Route::get('/robots.txt', [PublicController::class, 'robots']);

// ONhost ID (OIDC) – příznak ONHOST_OIDC_ENABLED (výchozí vypnuto → 404).
Route::get('/auth/onhost', [OnhostIdController::class, 'redirect'])->middleware(['guest', 'throttle:auth'])->name('oidc.redirect');
Route::get('/auth/onhost/callback', [OnhostIdController::class, 'callback'])->middleware('throttle:auth')->name('oidc.callback');
Route::get('/auth/onhost/registrace', [OnhostIdController::class, 'signup'])->middleware('guest')->name('oidc.signup');
Route::post('/auth/onhost/registrace', [OnhostIdController::class, 'completeSignup'])->middleware(['guest', 'throttle:auth'])->name('oidc.signup.store');
Route::post('/auth/onhost/backchannel-logout', [OnhostIdController::class, 'backchannelLogout'])
    ->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:60,1')->name('oidc.backchannel');

// Ekosystém ONhost (P1 katalog, P7 zdraví, P3 sběrnice) – každý za vlastním příznakem, výchozí vypnuto → 404.
Route::get('/ecosystem/catalog.json', [EcosystemController::class, 'catalog'])->middleware('throttle:60,1')->name('ecosystem.catalog');
Route::get('/health', [EcosystemController::class, 'health'])->middleware('throttle:120,1')->name('ecosystem.health');
Route::post('/bus/webhook', [EcosystemController::class, 'webhook'])
    ->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:120,1')->name('ecosystem.bus');

// Vlastní platební brána (Comgate) – za REALITY_FEATURE_PAYMENTS, výchozí vypnuto → 404.
Route::post('/platby/comgate/notifikace', [PaymentController::class, 'notify'])
    ->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:120,1')->name('payments.notify');
Route::get('/platby/{payment}/navrat', [PaymentController::class, 'back'])->middleware(['auth', 'throttle:30,1'])->name('payments.return');

Route::middleware('guest')->group(function () {
    Route::get('/auth/onhost/propojit', [OnhostIdController::class, 'showLink'])->name('onhost.link');
    Route::post('/auth/onhost/propojit', [OnhostIdController::class, 'link'])->middleware('throttle:auth');
    Route::get('/registrace', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registrace', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::get('/prihlaseni', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/prihlaseni', [AuthController::class, 'login'])->middleware('throttle:auth');
});

Route::middleware('auth')->group(function () {
    Route::post('/odhlasit', [AuthController::class, 'logout'])->name('logout');
    Route::get('/potvrdit-heslo', [AuthController::class, 'showConfirm'])->name('password.confirm');
    Route::post('/potvrdit-heslo', [AuthController::class, 'confirm'])->middleware('throttle:auth');

    Route::get('/moje', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/moje/onhost-id/propojit', [OnhostIdController::class, 'link'])->middleware(['password.confirm', 'throttle:auth'])->name('oidc.link');
    Route::delete('/moje/onhost-id', [OnhostIdController::class, 'unlink'])->middleware('password.confirm')->name('oidc.unlink');
    Route::delete('/moje/ucet', [AuthController::class, 'destroyAccount'])->middleware('password.confirm')->name('account.destroy');
    Route::get('/moje/oblibene', [AccountController::class, 'favorites'])->name('account.favorites');
    Route::post('/inzerat/{listing}/oblibene', [AccountController::class, 'toggleFavorite'])->name('listings.favorite');
    Route::get('/moje/hlidaci-psi', [AccountController::class, 'searches'])->name('account.searches');
    Route::post('/moje/hlidaci-psi', [AccountController::class, 'storeSearch'])->name('account.searches.store');
    Route::patch('/moje/hlidaci-psi/{search}', [AccountController::class, 'toggleSearch'])->name('account.searches.toggle');
    Route::delete('/moje/hlidaci-psi/{search}', [AccountController::class, 'destroySearch'])->name('account.searches.destroy');
    Route::get('/moje/zpravy', [AccountController::class, 'messages'])->name('account.messages');

    Route::get('/moje/inzeraty', [ListingController::class, 'mine'])->name('listings.mine');
    Route::get('/pridat-inzerat', [ListingController::class, 'create'])->middleware(RememberEcosystemReferral::class)->name('listings.create');
    Route::post('/pridat-inzerat', [ListingController::class, 'store'])->name('listings.store');
    Route::get('/moje/inzeraty/{listing}/krok/{step}', [ListingController::class, 'step'])->whereNumber('step')->name('listings.step');
    Route::put('/moje/inzeraty/{listing}/krok/{step}', [ListingController::class, 'saveStep'])->whereNumber('step')->name('listings.step.save');
    Route::post('/moje/inzeraty/{listing}/fotky', [ListingController::class, 'uploadPhotos'])->middleware('throttle:30,1')->name('listings.photos');
    Route::delete('/moje/inzeraty/{listing}/fotky/{photo}', [ListingController::class, 'deletePhoto'])->name('listings.photos.destroy');
    Route::get('/api/adresa', [\App\Http\Controllers\ListingAssistController::class, 'addressSuggest'])->middleware('throttle:geocode')->name('assist.address');
    Route::get('/api/adresa/detail', [\App\Http\Controllers\ListingAssistController::class, 'addressResolve'])->middleware('throttle:geocode')->name('assist.address.resolve');
    Route::get('/api/odhad-ceny', [\App\Http\Controllers\ListingAssistController::class, 'priceSuggest'])->middleware('throttle:30,1')->name('assist.price');
    Route::post('/moje/inzeraty/{listing}/navrh-popisu', [\App\Http\Controllers\ListingAssistController::class, 'describe'])->middleware('throttle:30,1')->name('assist.describe');
    Route::post('/moje/inzeraty/{listing}/odeslat', [ListingController::class, 'submit'])->name('listings.submit');
    Route::delete('/moje/inzeraty/{listing}', [ListingController::class, 'destroy'])->middleware('password.confirm')->name('listings.destroy');

    Route::post('/clenstvi/{plan}', [BillingController::class, 'subscribe'])->middleware('password.confirm')->name('pricing.subscribe');

    // ONhost ID + kredit ONhost (404 while ONHOST_CREDIT_ENABLED is off)
    Route::get('/onhost/propojit', [OnhostCreditController::class, 'connect'])->middleware('throttle:10,1')->name('onhost.connect');
    Route::get('/onhost/callback', [OnhostCreditController::class, 'callback'])->middleware('throttle:10,1')->name('onhost.callback');
    Route::post('/onhost/odpojit', [OnhostCreditController::class, 'disconnect'])->name('onhost.disconnect');
    Route::post('/clenstvi/{plan}/kredit', [OnhostCreditController::class, 'payPlan'])->middleware(['password.confirm', 'throttle:10,1'])->name('onhost.pay.plan');

    Route::prefix('admin')->name('admin.')->middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/moderace', [AdminController::class, 'moderation'])->name('moderation');
        Route::post('/moderace/{listing}', [AdminController::class, 'decide'])->middleware('password.confirm')->name('moderation.decide');
        Route::get('/nahlaseni', [AdminController::class, 'reports'])->name('reports');
        Route::post('/nahlaseni/{report}', [AdminController::class, 'resolveReport'])->middleware('password.confirm')->name('reports.resolve');
        Route::get('/statistiky', [AdminController::class, 'stats'])->name('stats');
        Route::post('/platby/{payment}/vratit-kredit', [OnhostCreditController::class, 'refund'])->middleware('password.confirm')->name('payments.onhost_refund');
        Route::get('/recenze', [\App\Http\Controllers\Admin\AgencyAdminController::class, 'reviews'])->name('agency-reviews');
        Route::post('/recenze/{review}', [\App\Http\Controllers\Admin\AgencyAdminController::class, 'decideReview'])->middleware('password.confirm')->name('agency-reviews.decide');
        Route::get('/kancelare', [\App\Http\Controllers\Admin\AgencyAdminController::class, 'claims'])->name('agency-claims');
        Route::post('/kancelare/{claim}', [\App\Http\Controllers\Admin\AgencyAdminController::class, 'decideClaim'])->middleware('password.confirm')->name('agency-claims.decide');
    });
});

// --- Portal: analytics, tools, owner tools ---

Route::get('/lokality', [InsightsController::class, 'index'])->name('insights.index');
Route::get('/lokality/{slug}', [InsightsController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('insights.show');
Route::get('/pronajem/{slug}', [InsightsController::class, 'offer'])->where('slug', '[a-z0-9-]+')->name('localities.rent');
Route::get('/prodej/{slug}', [InsightsController::class, 'offer'])->where('slug', '[a-z0-9-]+')->name('localities.sale');
Route::get('/api/lokality/heat', [InsightsController::class, 'heat'])->middleware('throttle:60,1')->name('insights.heat');
Route::get('/kalkulacky', [ToolsController::class, 'calculators'])->name('tools.calculators');
Route::get('/pruvodce', [ToolsController::class, 'guides'])->name('guides.index');
Route::get('/pruvodce/{slug}', [ToolsController::class, 'guide'])->where('slug', '[a-z0-9-]+')->name('guides.show');
Route::get('/porovnat', [ToolsController::class, 'compare'])->name('compare');

Route::middleware('auth')->group(function () {
    Route::patch('/moje/oblibene/{listing}/poznamka', [AccountController::class, 'favoriteNote'])->name('account.favorites.note');
    Route::post('/moje/zpravy/{message}/archiv', [AccountController::class, 'archiveMessage'])->name('account.messages.archive');
    Route::get('/moje/prohlidky', [ViewingController::class, 'index'])->name('account.viewings');
    Route::post('/prohlidka/{slot}', [ViewingController::class, 'book'])->middleware('throttle:10,1')->name('viewings.book');
    Route::delete('/prohlidka/{slot}', [ViewingController::class, 'cancel'])->name('viewings.cancel');

    Route::get('/moje/odhad-ceny', [OwnerToolsController::class, 'estimate'])->name('owner.estimate');
    Route::get('/moje/smlouvy', [OwnerToolsController::class, 'contracts'])->name('owner.contracts');
    Route::get('/moje/smlouvy/{slug}', [OwnerToolsController::class, 'contract'])->where('slug', '[a-z0-9-]+')->name('owner.contract');
    Route::get('/moje/inzeraty/{listing}/nastroje', [OwnerToolsController::class, 'tools'])->name('owner.tools');
    Route::post('/moje/inzeraty/{listing}/obnovit', [OwnerToolsController::class, 'renew'])->name('owner.renew');
    Route::post('/moje/inzeraty/{listing}/topovat', [OwnerToolsController::class, 'boost'])->middleware('password.confirm')->name('owner.boost');
    Route::post('/moje/inzeraty/{listing}/terminy', [OwnerToolsController::class, 'storeSlots'])->name('owner.slots.store');
    Route::delete('/moje/inzeraty/{listing}/terminy/{slot}', [OwnerToolsController::class, 'destroySlot'])->name('owner.slots.destroy');
    Route::get('/moje/inzeraty/{listing}/predavaci-protokol', [OwnerToolsController::class, 'handover'])->name('owner.handover');
    Route::post('/moje/inzeraty/{listing}/predavaci-protokol', [OwnerToolsController::class, 'storeHandover'])->name('owner.handover.store');
    Route::get('/moje/inzeraty/{listing}/predavaci-protokol/{protocol}', [OwnerToolsController::class, 'printHandover'])->name('owner.handover.print');
});

// --- Agency import feed (push API, token auth, no session/CSRF) ---
Route::prefix('api/feed/v1')->middleware([\App\Http\Middleware\AuthenticateFeed::class, 'throttle:120,1'])
    ->group(function () {
        Route::get('/listings', [\App\Http\Controllers\Api\AgencyFeedController::class, 'index']);
        Route::post('/listings', [\App\Http\Controllers\Api\AgencyFeedController::class, 'upsert']);
        Route::delete('/listings/{externalId}', [\App\Http\Controllers\Api\AgencyFeedController::class, 'destroy'])->where('externalId', '[A-Za-z0-9._\-]+');
    });

// --- Sreality-compatible XML-RPC import for agency CRMs (session hash auth, no CSRF) ---
Route::post('/import/RPC2', \App\Http\Controllers\Api\SrealityImportController::class)->middleware('throttle:300,1')->name('import.rpc2');

// --- In-system rental contract (feature flag reality.features.contracts, default off) ---
Route::middleware(['auth', \App\Http\Middleware\EnsureFeature::class.':contracts'])->group(function () {
    Route::get('/najemni-smlouvy', [\App\Http\Controllers\RentalContractController::class, 'index'])->name('contracts.index');
    Route::get('/moje/inzeraty/{listing}/najemni-smlouva', [\App\Http\Controllers\RentalContractController::class, 'create'])->name('contracts.create');
    Route::post('/moje/inzeraty/{listing}/najemni-smlouva', [\App\Http\Controllers\RentalContractController::class, 'store'])->name('contracts.store');
    Route::get('/najemni-smlouvy/{contract}', [\App\Http\Controllers\RentalContractController::class, 'show'])->name('contracts.show');
    Route::get('/najemni-smlouvy/{contract}/upravit', [\App\Http\Controllers\RentalContractController::class, 'edit'])->name('contracts.edit');
    Route::put('/najemni-smlouvy/{contract}', [\App\Http\Controllers\RentalContractController::class, 'update'])->name('contracts.update');
    Route::get('/najemni-smlouvy/{contract}/tisk', [\App\Http\Controllers\RentalContractController::class, 'printable'])->name('contracts.print');
    Route::get('/najemni-smlouvy/{contract}/podpis', [\App\Http\Controllers\RentalContractController::class, 'signForm'])->middleware('password.confirm')->name('contracts.sign');
    Route::post('/najemni-smlouvy/{contract}/kod', [\App\Http\Controllers\RentalContractController::class, 'sendCode'])->middleware(['password.confirm', 'throttle:contract-code'])->name('contracts.code');
    Route::post('/najemni-smlouvy/{contract}/podpis', [\App\Http\Controllers\RentalContractController::class, 'sign'])->middleware('password.confirm')->name('contracts.sign.store');
    Route::post('/najemni-smlouvy/{contract}/odeslat', [\App\Http\Controllers\RentalContractController::class, 'send'])->name('contracts.send');
    Route::post('/najemni-smlouvy/{contract}/zrusit', [\App\Http\Controllers\RentalContractController::class, 'cancel'])->name('contracts.cancel');
    Route::post('/najemni-smlouvy/{contract}/platba', [\App\Http\Controllers\RentalContractController::class, 'pay'])->middleware('password.confirm')->name('contracts.pay');
    Route::get('/najemni-smlouvy/{contract}/audit', [\App\Http\Controllers\RentalContractController::class, 'audit'])->name('contracts.audit');
});

// --- Directory of real-estate agencies (ARES open data, claims, moderated reviews) ---
Route::get('/realitni-kancelare', [\App\Http\Controllers\AgencyController::class, 'index'])->name('agencies.index');
Route::get('/realitni-kancelare/{agency}', [\App\Http\Controllers\AgencyController::class, 'show'])->name('agencies.show');
Route::get('/realitni-kancelare/{agency}/makler/{broker}', [\App\Http\Controllers\AgencyController::class, 'broker'])->name('agencies.broker');
Route::post('/recenze/{review}/nahlasit', [\App\Http\Controllers\AgencyController::class, 'reportReview'])->middleware('throttle:contact')->name('agency-reviews.report');
Route::middleware('auth')->group(function () {
    Route::post('/realitni-kancelare/{agency}/recenze', [\App\Http\Controllers\AgencyController::class, 'storeReview'])->middleware('throttle:10,1')->name('agencies.review');
    Route::post('/realitni-kancelare/{agency}/prevzit', [\App\Http\Controllers\AgencyController::class, 'claim'])->middleware('throttle:10,1')->name('agencies.claim');
    Route::put('/realitni-kancelare/{agency}', [\App\Http\Controllers\AgencyController::class, 'update'])->name('agencies.update');
    Route::post('/realitni-kancelare/{agency}/makleri', [\App\Http\Controllers\AgencyController::class, 'storeBroker'])->name('agencies.brokers.store');
    Route::delete('/realitni-kancelare/{agency}/makleri/{broker}', [\App\Http\Controllers\AgencyController::class, 'destroyBroker'])->name('agencies.brokers.destroy');
});

// Vlna 1 (agentní tým): oddělené soubory, aby paralelní práce nekolidovala.
require __DIR__.'/pages.php';
require __DIR__.'/admin.php';
