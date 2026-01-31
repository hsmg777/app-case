<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = \App\Models\Sri\SriConfig::first();

echo "Config found: " . ($config ? 'YES' : 'NO') . "\n";
if ($config) {
    echo "Ruta DB: " . $config->ruta_certificado . "\n";
    echo "Path attribute: " . $config->cert_absolute_path . "\n";
    echo "File Exists: " . (file_exists($config->cert_absolute_path) ? 'YES' : 'NO') . "\n";
    echo "Disk root: " . config('filesystems.disks.local.root') . "\n";

    // Check fallback logic
    $srv = app(\App\Services\Sri\SriConfigService::class);
    $cfg = $srv->get();
    echo "Service Get: " . ($cfg ? 'YES' : 'NO') . "\n";
}
