<?php
spl_autoload_register(function ($class) {
    $prefix = 'OCA\\UrbanDuplicati\\';
    $base   = __DIR__ . '/../lib/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $rel  = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) require $file;
});
