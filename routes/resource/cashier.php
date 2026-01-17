<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cashier\CashierController;

/*
|--------------------------------------------------------------------------
| CAJA (cashier|admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:cashier|admin|supervisor'])
    ->prefix('cashier')
    ->name('cashier.')
    ->group(function () {

        // Apertura
        Route::get('/open', [CashierController::class, 'openView'])->name('open.view');
        Route::post('/open', [CashierController::class, 'open'])->name('open');

        // Movimientos (Ingreso / Retiro)
        Route::post('/movement', [CashierController::class, 'movement'])->name('movement');

        // Cierre
        Route::get('/close', [CashierController::class, 'closeView'])->name('close.view');
        Route::post('/close', [CashierController::class, 'close'])->name('close');

        // Resumen
        Route::get('/summary/{id}', [CashierController::class, 'summary'])->name('summary');
        Route::get('/summary/{id}/print', [CashierController::class, 'printSummary'])->name('summary.print');
    });
