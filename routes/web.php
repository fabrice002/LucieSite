<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationTrackingController;
use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public
|--------------------------------------------------------------------------
*/

Route::view('/', 'public.accueil')->name('home');

// Dépôt de dossier
Route::get('deposer-mon-dossier', [ApplicationController::class, 'create'])->name('depot.create');
Route::post('deposer-mon-dossier', [ApplicationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('depot.store');
Route::get('dossier-recu', [ApplicationController::class, 'confirmation'])->name('depot.confirmation');

// Suivi de dossier
Route::get('suivre-mon-dossier', [ApplicationTrackingController::class, 'index'])->name('suivi.index');
Route::post('suivre-mon-dossier', [ApplicationTrackingController::class, 'show'])
    ->middleware('throttle:5,1')
    ->name('suivi.show');

/*
|--------------------------------------------------------------------------
| Espace authentifié
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Les documents ne sont jamais servis par une URL directe : cette route est
    // authentifiée et protégée par DocumentPolicy.
    Route::get('documents/{document}/telecharger', DocumentDownloadController::class)
        ->name('documents.download');
});

require __DIR__.'/settings.php';
