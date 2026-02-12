<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Product\ProductController;

Route::prefix('productos')->group(function () {

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
    Route::get('/export', [ProductController::class, 'export'])
        ->name('productos.export');
    Route::get('/import/template', [ProductController::class, 'downloadImportTemplate'])
        ->name('productos.import.template');
    Route::post('/import', [ProductController::class, 'import'])
        ->name('productos.import');
    Route::get('/import/{importId}/status', [ProductController::class, 'importStatus'])
        ->name('productos.import.status');
    Route::get('/show/{id}', [ProductController::class, 'show']);

    Route::post('/store', [ProductController::class, 'store']);
    Route::put('/update/{id}', [ProductController::class, 'update']);
    Route::delete('/delete/{id}', [ProductController::class, 'destroy']);
    Route::patch('/{id}/estado', [ProductController::class, 'setEstado']);

});
