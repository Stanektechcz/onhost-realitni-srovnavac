<?php

use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

// Trasy vlny 1 – pages (veřejné informační stránky)
Route::get('/jak-to-funguje', [PagesController::class, 'howItWorks'])->name('pages.how');
Route::get('/pro-majitele', [PagesController::class, 'owners'])->name('pages.owners');
Route::get('/pro-realitni-kancelare', [PagesController::class, 'agencies'])->name('pages.agencies');
Route::get('/pro-realitni-kancelare/dokumentace', [PagesController::class, 'feedDocs'])->name('pages.agencies.docs');
Route::get('/srovnavac', [PagesController::class, 'comparator'])->name('pages.comparator');
Route::get('/o-nas', [PagesController::class, 'about'])->name('pages.about');
Route::get('/kontakt', [PagesController::class, 'contact'])->name('pages.contact');
Route::post('/kontakt', [PagesController::class, 'sendContact'])->middleware('throttle:contact')->name('pages.contact.send');
Route::get('/caste-dotazy', [PagesController::class, 'faq'])->name('pages.faq');
