<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Product\ProductController;

/*
|--------------------------------------------------------------------------
| PRODUCTOS (supervisor|admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:supervisor|admin'])
    ->prefix('productos')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | VISTAS (BLADE)
        |--------------------------------------------------------------------------
        */

        Route::get('/', [ProductController::class, 'viewIndex'])
            ->name('productos.index');

        Route::get('/crear', [ProductController::class, 'viewCreate'])
            ->name('productos.create');

        Route::get('/editar/{id}', [ProductController::class, 'viewEdit'])
            ->name('productos.edit');

        /*
        |--------------------------------------------------------------------------
        | API (JSON)
        |--------------------------------------------------------------------------
        */

        Route::get('/list', [ProductController::class, 'index']);
        Route::get('/show/{id}', [ProductController::class, 'show']);

        Route::post('/store', [ProductController::class, 'store']);
        Route::put('/update/{id}', [ProductController::class, 'update']);
        Route::delete('/delete/{id}', [ProductController::class, 'destroy']);

        Route::patch('/{id}/estado', [ProductController::class, 'setEstado']);
    });
