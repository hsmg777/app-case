<?php

use App\Http\Controllers\Product\ProductPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('producto-precios')->group(function () {

    Route::get('/', [ProductPriceController::class, 'index']);               // todos los precios
    Route::post('/bulk', [ProductPriceController::class, 'bulkUpsert']);     // crear/actualizar masivo
    Route::get('/{id}', [ProductPriceController::class, 'show']);            // precio por id
    Route::get('/producto/{productoId}', [ProductPriceController::class, 'showByProduct']); // precio por producto

    Route::post('/', [ProductPriceController::class, 'store']);              // crear
    Route::put('/{id}', [ProductPriceController::class, 'update']);          // actualizar
    Route::delete('/{id}', [ProductPriceController::class, 'destroy']);      // eliminar
});
