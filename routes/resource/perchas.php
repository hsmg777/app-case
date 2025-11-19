<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Store\PerchaController;

Route::prefix('inventario/perchas')->group(function () {

    // Vista unificada (usa la misma vista de bodega)
    Route::get('/vista', [PerchaController::class, 'viewIndex'])
        ->name('inventario.perchas.vista');

    // API JSON
    Route::get('/', [PerchaController::class, 'index']);                 
    Route::get('/{id}', [PerchaController::class, 'show']);              

    // Perchas por bodega
    Route::get('/bodega/{bodegaId}', [PerchaController::class, 'getByBodega']); 

    Route::post('/', [PerchaController::class, 'store']);                
    Route::put('/{id}', [PerchaController::class, 'update']);            
    Route::delete('/{id}', [PerchaController::class, 'destroy']);        
});
