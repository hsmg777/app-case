<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SalePrintController;

/*
|--------------------------------------------------------------------------
| VENTAS / FACTURACIÓN (cashier|admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:cashier|admin|supervisor'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | VISTAS DEL MÓDULO DE VENTAS
    |--------------------------------------------------------------------------
    */

    // Selección de bodega
    Route::get('/bodega', [SaleController::class, 'viewSelectBodega'])
        ->name('ventas.select_bodega');

    // Vista principal POS
    Route::get('/facturar/{bodega}', [SaleController::class, 'viewIndex'])
        ->name('ventas.index');

    // Ticket / impresión
    Route::get('/ventas/{id}/ticket', [SalePrintController::class, 'ticket'])
        ->name('sales.ticket');

    /*
    |--------------------------------------------------------------------------
    | ENDPOINTS JSON (API VENTAS)
    |--------------------------------------------------------------------------
    */

    Route::prefix('/api/ventas')->name('api.ventas.')->group(function () {

        // Crear una venta (cabecera + items + pago)
        Route::post('/', [SaleController::class, 'store'])
            ->name('store');

        // Ver una venta específica (detalle / reimpresión)
        Route::get('/{id}', [SaleController::class, 'show'])
            ->name('show');

        // Futuro:
        // Route::get('/', [SaleController::class, 'index'])->name('index');
        // Route::post('/{id}/anular', [SaleController::class, 'anular'])->name('anular');
    });
});
