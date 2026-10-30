<?php

$basePath = dirname(__DIR__);
$configCache = $basePath.'/bootstrap/cache/config.php';

if (is_file($configCache)) {
    unlink($configCache);
}

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $basePath.'/vendor/autoload.php';
