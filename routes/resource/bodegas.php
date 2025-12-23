<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Store\BodegaController;

Route::middleware(['auth', 'role:supervisor|admin'])
    ->prefix('inventario/bodegas')
    ->group(function () {

        // Vista unificada Bodegas + Perchas
        Route::get('/vista', [BodegaController::class, 'viewIndex'])
            ->name('inventario.bodegas_perchas');

        // API JSON
        Route::get('/', [BodegaController::class, 'index']);
        Route::get('/{id}', [BodegaController::class, 'show']);

        Route::post('/', [BodegaController::class, 'store']);
        Route::put('/{id}', [BodegaController::class, 'update']);

        Route::delete('/{id}', [BodegaController::class, 'destroy']);
    });
