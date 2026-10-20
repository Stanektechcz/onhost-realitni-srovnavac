<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OnhostIdController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\OnhostCreditController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

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

// ONhost ID (OIDC) – příznak ONHOST_OIDC_ENABLED (výchozí vypnuto → 404).
Route::get('/auth/onhost', [OnhostIdController::class, 'redirect'])->middleware(['guest', 'throttle:auth'])->name('oidc.redirect');
Route::get('/auth/onhost/callback', [OnhostIdController::class, 'callback'])->middleware('throttle:auth')->name('oidc.callback');
Route::post('/auth/onhost/backchannel-logout', [OnhostIdController::class, 'backchannelLogout'])
    ->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:60,1')->name('oidc.backchannel');

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
    Route::get('/moje/onhost-id/propojit', [OnhostIdController::class, 'link'])->middleware(['password.confirm', 'throttle:auth'])->name('oidc.link');
    Route::delete('/moje/onhost-id', [OnhostIdController::class, 'unlink'])->middleware('password.confirm')->name('oidc.unlink');
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
    });
});
