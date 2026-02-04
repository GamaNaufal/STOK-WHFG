<?php

require __DIR__ . '/../vendor/autoload.php';

// Load Pest functions when running via phpunit / artisan test
$pestFunctions = __DIR__ . '/../vendor/pestphp/pest/src/Functions.php';
if (!file_exists($pestFunctions)) {
    throw new RuntimeException('Pest is not installed. Run: composer install');
}

require $pestFunctions;

if (file_exists(__DIR__ . '/Pest.php')) {
    require __DIR__ . '/Pest.php';
}
