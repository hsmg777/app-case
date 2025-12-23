<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Inventory\SupplierController;

/*
|--------------------------------------------------------------------------
| PROVEEDORES + COMPRAS (inventario) - supervisor|admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:supervisor|admin'])
    ->prefix('inventario')
    ->group(function () {

        // ===== PROVEEDORES (VISTAS) =====
        Route::get('/proveedores', [SupplierController::class, 'viewMenu'])
            ->name('proveedores.menu');

        Route::get('/proveedores/listado', [SupplierController::class, 'viewIndex'])
            ->name('proveedores.index');

        // ===== PROVEEDORES (API JSON) =====
        Route::prefix('proveedores')->group(function () {
            Route::get('/list',    [SupplierController::class, 'index']);
            Route::get('/{id}',    [SupplierController::class, 'show']);
            Route::post('/',       [SupplierController::class, 'store']);
            Route::put('/{id}',    [SupplierController::class, 'update']);
            Route::delete('/{id}', [SupplierController::class, 'destroy']);
        });

        // ===== COMPRAS =====
        Route::prefix('compras')->group(function () {
            // Vistas
            Route::get('/',      [PurchaseController::class, 'viewIndex'])->name('compras.index');
            Route::get('/crear', [PurchaseController::class, 'viewCreate'])->name('compras.create');

            // API / acciones
            Route::get('/list',        [PurchaseController::class, 'index'])->name('compras.list');
            Route::get('/{id}',        [PurchaseController::class, 'show']);
            Route::post('/',           [PurchaseController::class, 'store'])->name('compras.store');
            Route::post('/{id}/pagos', [PurchaseController::class, 'addPayment'])->name('compras.pagos');
        });
    });
