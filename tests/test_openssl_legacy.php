<?php
$providerPath = '/usr/lib/ossl-modules';
$cmd = "openssl list -provider-path $providerPath -provider legacy -provider default -providers";
echo "Command: $cmd\n";
exec($cmd, $output, $ret);
echo "Return: $ret\n";
echo "Output:\n" . implode("\n", $output) . "\n";
