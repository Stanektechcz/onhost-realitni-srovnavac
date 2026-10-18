<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\OwnerToolsController;
use App\Http\Controllers\ToolsController;
use App\Http\Controllers\ViewingController;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/hledat', [SearchController::class, 'index'])->name('search');
Route::get('/api/mapa', [SearchController::class, 'map'])->middleware('throttle:60,1')->name('search.map');
Route::get('/inzerat/{listing}', [ListingController::class, 'show'])->name('listings.show');
Route::post('/inzerat/{listing}/zprava', [ContactController::class, 'message'])->middleware('throttle:contact')->name('listings.message');
Route::post('/inzerat/{listing}/nahlasit', [ContactController::class, 'report'])->middleware('throttle:contact')->name('listings.report');
Route::get('/clenstvi', [PublicController::class, 'pricing'])->name('pricing');
Route::get('/info/{page}', [PublicController::class, 'legal'])->name('legal');
Route::get('/sitemap.xml', [PublicController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PublicController::class, 'robots']);

Route::middleware('guest')->group(function () {
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
    Route::delete('/moje/ucet', [AuthController::class, 'destroyAccount'])->middleware('password.confirm')->name('account.destroy');
    Route::get('/moje/oblibene', [AccountController::class, 'favorites'])->name('account.favorites');
    Route::post('/inzerat/{listing}/oblibene', [AccountController::class, 'toggleFavorite'])->name('listings.favorite');
    Route::get('/moje/hlidaci-psi', [AccountController::class, 'searches'])->name('account.searches');
    Route::post('/moje/hlidaci-psi', [AccountController::class, 'storeSearch'])->name('account.searches.store');
    Route::delete('/moje/hlidaci-psi/{search}', [AccountController::class, 'destroySearch'])->name('account.searches.destroy');
    Route::get('/moje/zpravy', [AccountController::class, 'messages'])->name('account.messages');

    Route::get('/moje/inzeraty', [ListingController::class, 'mine'])->name('listings.mine');
    Route::get('/pridat-inzerat', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/pridat-inzerat', [ListingController::class, 'store'])->name('listings.store');
    Route::get('/moje/inzeraty/{listing}/krok/{step}', [ListingController::class, 'step'])->whereNumber('step')->name('listings.step');
    Route::put('/moje/inzeraty/{listing}/krok/{step}', [ListingController::class, 'saveStep'])->whereNumber('step')->name('listings.step.save');
    Route::post('/moje/inzeraty/{listing}/fotky', [ListingController::class, 'uploadPhotos'])->middleware('throttle:30,1')->name('listings.photos');
    Route::delete('/moje/inzeraty/{listing}/fotky/{photo}', [ListingController::class, 'deletePhoto'])->name('listings.photos.destroy');
    Route::post('/moje/inzeraty/{listing}/odeslat', [ListingController::class, 'submit'])->name('listings.submit');
    Route::delete('/moje/inzeraty/{listing}', [ListingController::class, 'destroy'])->middleware('password.confirm')->name('listings.destroy');

    Route::post('/clenstvi/{plan}', [BillingController::class, 'subscribe'])->middleware('password.confirm')->name('pricing.subscribe');

    Route::prefix('admin')->name('admin.')->middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/moderace', [AdminController::class, 'moderation'])->name('moderation');
        Route::post('/moderace/{listing}', [AdminController::class, 'decide'])->middleware('password.confirm')->name('moderation.decide');
        Route::get('/nahlaseni', [AdminController::class, 'reports'])->name('reports');
        Route::post('/nahlaseni/{report}', [AdminController::class, 'resolveReport'])->middleware('password.confirm')->name('reports.resolve');
        Route::get('/statistiky', [AdminController::class, 'stats'])->name('stats');
    });
});

// --- Portal: analytics, tools, owner tools ---

Route::get('/lokality', [InsightsController::class, 'index'])->name('insights.index');
Route::get('/lokality/{slug}', [InsightsController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('insights.show');
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
