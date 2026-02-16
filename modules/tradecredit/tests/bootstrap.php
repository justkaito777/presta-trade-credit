<?php

declare(strict_types=1);

$psRootDir = dirname(__DIR__, 3);

$configFile = $psRootDir . '/config/config.inc.php';
if (!file_exists($configFile)) {
    throw new RuntimeException(
        'Nie znaleziono pliku konfiguracyjnego PrestaShop: ' . $configFile
    );
}
require_once $configFile;

// Autoloader modułu
require_once dirname(__DIR__) . '/vendor/autoload.php';
