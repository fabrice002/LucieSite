<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationTrackingController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\TemporaryUploadController;
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
    ->middleware('throttle:depot')
    ->name('depot.store');
Route::get('dossier-recu', [ApplicationController::class, 'confirmation'])->name('depot.confirmation');

// Téléversement par tranches (protocole FilePond). Les fichiers atterrissent sur
// le disque privé sous un jeton lié à la session, avant validation du formulaire.
Route::controller(TemporaryUploadController::class)->prefix('televersement')->group(function () {
    Route::post('/', 'store')->middleware('throttle:televersement')->name('televersement.store');
    Route::patch('/', 'patch')->middleware('throttle:televersement')->name('televersement.patch');
    Route::get('/', 'head')->middleware('throttle:televersement')->name('televersement.head');
    Route::delete('/', 'destroy')->middleware('throttle:televersement')->name('televersement.destroy');
});

// Suivi de dossier
Route::get('suivre-mon-dossier', [ApplicationTrackingController::class, 'index'])->name('suivi.index');
Route::post('suivre-mon-dossier', [ApplicationTrackingController::class, 'show'])
    ->middleware('throttle:suivi')
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
