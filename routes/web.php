<?php

use App\Http\Controllers\ConsultaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/consulta')->name('home');

Route::get('consulta', ConsultaController::class)
    ->middleware('throttle:djen-consulta')
    ->name('consulta.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
