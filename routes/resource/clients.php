<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clients\ClientController;

/*
|--------------------------------------------------------------------------
| CLIENTES (cashier|admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:cashier|admin|supervisor'])
    ->prefix('clients')
    ->name('clients.')
    ->group(function () {

        Route::get('/', [ClientController::class, 'index'])->name('index');

        Route::post('/', [ClientController::class, 'store'])->name('store');

        Route::get('/{id}', [ClientController::class, 'show'])->name('show');

        Route::get('/{id}/emails', [ClientController::class, 'emails'])->name('emails');

        Route::put('/{id}', [ClientController::class, 'update'])->name('update');
        Route::patch('/{id}', [ClientController::class, 'update'])->name('update.partial');

        Route::delete('/{id}', [ClientController::class, 'destroy'])->name('destroy');

        Route::get('/search-by-identificacion', [ClientController::class, 'findByIdentificacion'])
            ->name('searchByIdentificacion');
    });
