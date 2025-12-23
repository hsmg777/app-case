<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Inventory\InventoryController;

/*
|--------------------------------------------------------------------------
| INVENTARIO (solo supervisor|admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:supervisor|admin'])
    ->prefix('inventario')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | RUTAS DE VISTAS (BLADE)
        |--------------------------------------------------------------------------
        */

        // Vista principal del módulo Inventario
        Route::get('/', [InventoryController::class, 'viewIndex'])
            ->name('inventario.index');

        // Vista listado de stock
        Route::get('/stock', [InventoryController::class, 'viewStock'])
            ->name('inventario.stock');

        // Vista historial de ajustes
        Route::get('/historial', [InventoryController::class, 'viewHistory'])
            ->name('inventario.historial');

        /*
        |--------------------------------------------------------------------------
        | RUTAS FUNCIONALES (JSON: CRUD + STOCK)
        |--------------------------------------------------------------------------
        */

        // Listar inventario (JSON)
        Route::get('/list', [InventoryController::class, 'index']);

        // Ver inventario por ID
        Route::get('/item/{id}', [InventoryController::class, 'show']);

        // Buscar inventario por producto
        Route::get('/producto/{productoId}', [InventoryController::class, 'getByProduct']);

        // Buscar inventario por bodega
        Route::get('/bodega/{bodegaId}', [InventoryController::class, 'getByBodega']);

        // Crear inventario
        Route::post('/store', [InventoryController::class, 'store']);

        // Actualizar inventario
        Route::put('/update/{id}', [InventoryController::class, 'update']);

        // Eliminar inventario
        Route::delete('/delete/{id}', [InventoryController::class, 'destroy']);

        // Aumentar stock
        Route::post('/increase', [InventoryController::class, 'increaseStock']);

        // Disminuir stock
        Route::post('/decrease', [InventoryController::class, 'decreaseStock']);

        // Ajustar stock (valor absoluto con registro en ajustes_inventario)
        Route::post('/adjust', [InventoryController::class, 'adjustStock'])
            ->name('inventario.adjust');

        // Data del historial
        Route::get('/historial/data', [InventoryController::class, 'adjustmentsHistory']);
    });
