<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BillingAdminController;
use App\Http\Controllers\Admin\ContactMessageAdminController;
use App\Http\Controllers\Admin\ListingAdminController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Trasy vlny 1 – admin
Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/uzivatele', [UserAdminController::class, 'index'])->name('users.index');
    Route::get('/uzivatele/{user}', [UserAdminController::class, 'show'])->name('users.show');
    Route::post('/uzivatele/{user}/role', [UserAdminController::class, 'role'])->middleware('password.confirm')->name('users.role');
    Route::post('/uzivatele/{user}/blokace', [UserAdminController::class, 'block'])->middleware('password.confirm')->name('users.block');

    Route::get('/inzeraty', [ListingAdminController::class, 'index'])->name('listings.index');
    Route::get('/inzeraty/{listing:id}', [ListingAdminController::class, 'show'])->name('listings.show');
    Route::post('/inzeraty/{listing:id}/deaktivovat', [ListingAdminController::class, 'deactivate'])->middleware('password.confirm')->name('listings.deactivate');
    Route::post('/inzeraty/{listing:id}/prodlouzit', [ListingAdminController::class, 'extend'])->middleware('password.confirm')->name('listings.extend');

    Route::get('/platby', [BillingAdminController::class, 'payments'])->name('billing.payments');
    Route::get('/predplatne', [BillingAdminController::class, 'subscriptions'])->name('billing.subscriptions');
    Route::get('/tarify', [BillingAdminController::class, 'plans'])->name('plans.index');
    Route::get('/tarify/novy', [BillingAdminController::class, 'createPlan'])->name('plans.create');
    Route::post('/tarify', [BillingAdminController::class, 'storePlan'])->middleware('password.confirm')->name('plans.store');
    Route::get('/tarify/{plan}/upravit', [BillingAdminController::class, 'editPlan'])->name('plans.edit');
    Route::put('/tarify/{plan}', [BillingAdminController::class, 'updatePlan'])->middleware('password.confirm')->name('plans.update');
    Route::delete('/tarify/{plan}', [BillingAdminController::class, 'destroyPlan'])->middleware('password.confirm')->name('plans.destroy');

    Route::get('/kontakt', [ContactMessageAdminController::class, 'index'])->name('contact.index');
    Route::get('/kontakt/{message}', [ContactMessageAdminController::class, 'show'])->name('contact.show');
    Route::post('/kontakt/{message}/stav', [ContactMessageAdminController::class, 'status'])->name('contact.status');

    Route::get('/nastaveni', [SettingsController::class, 'index'])->name('settings');
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit');
});
