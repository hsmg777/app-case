<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Dashboard
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/**
 * CARGA AUTOMÁTICA DE RUTAS EN /resource
 */
if (!function_exists('require_route_dir')) {
    function require_route_dir(string $path): void
    {
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..')
                continue;

            $full = $path . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                require_route_dir($full);
            } elseif (is_file($full) && pathinfo($full, PATHINFO_EXTENSION) === 'php') {
                require $full;
            }
        }
    }
}

Route::middleware(['auth'])->group(function () {
    Route::post('/sri/config/test', [App\Http\Controllers\Sri\SriConfigController::class, 'testConfig'])->name('sri.config.test');
    require_route_dir(__DIR__ . '/resource');
});

// Rutas auth Breeze
require __DIR__ . '/auth.php';
