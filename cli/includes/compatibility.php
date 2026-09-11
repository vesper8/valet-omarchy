<?php

use Valet\OperatingSystem;

// Allow bypassing these checks if using Valet in a non-CLI app
if (php_sapi_name() !== 'cli') {
    return;
}

/**
 * Check the system's compatibility with Valet.
 */
$inTestingEnvironment = strpos($_SERVER['SCRIPT_NAME'], 'phpunit') !== false;
$operatingSystem = new OperatingSystem;

if (! $operatingSystem->isSupported() && ! $inTestingEnvironment) {
    echo 'Valet only supports macOS and Linux.'.PHP_EOL;

    exit(1);
}

if (version_compare(PHP_VERSION, '8.0', '<')) {
    echo 'Valet requires PHP 8.0 or later.';

    exit(1);
}

if (! $operatingSystem->findHomebrewBinary() && ! $inTestingEnvironment) {
    echo 'Valet requires Homebrew to be installed and available in your PATH.';

    exit(1);
}
