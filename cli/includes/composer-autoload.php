<?php

namespace Valet;

/**
 * Find Composer's autoloader for a source checkout or a global installation.
 *
 * A Composer path repository resolves __DIR__ to its source checkout, so the
 * usual relative vendor path is absent on a fresh clone. Also, sudo may set
 * HOME to /root even when Valet was installed for a different user.
 */
function composer_autoload_candidates(
    string $cliDirectory,
    ?string $userHome,
    ?string $composerHome = null,
    ?string $xdgConfigHome = null
): array {
    $paths = [
        $cliDirectory.'/../vendor/autoload.php',
        $cliDirectory.'/../../../autoload.php',
    ];

    if ($composerHome) {
        $paths[] = rtrim($composerHome, '/').'/vendor/autoload.php';
    }

    if ($userHome) {
        $paths[] = rtrim($userHome, '/').'/.config/composer/vendor/autoload.php';
        $paths[] = rtrim($userHome, '/').'/.composer/vendor/autoload.php';
    }

    if ($xdgConfigHome) {
        $paths[] = rtrim($xdgConfigHome, '/').'/composer/vendor/autoload.php';
    }

    return array_values(array_unique($paths));
}
