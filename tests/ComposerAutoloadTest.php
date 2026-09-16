<?php

require_once __DIR__.'/../cli/includes/composer-autoload.php';

use PHPUnit\Framework\TestCase;

use function Valet\composer_autoload_candidates;

class ComposerAutoloadTest extends TestCase
{
    public function test_it_finds_the_invoking_users_global_composer_directory(): void
    {
        $paths = composer_autoload_candidates('/source/cli', '/home/valet-user');

        $this->assertSame('/source/cli/../vendor/autoload.php', $paths[0]);
        $this->assertSame('/source/cli/../../../autoload.php', $paths[1]);
        $this->assertContains('/home/valet-user/.config/composer/vendor/autoload.php', $paths);
        $this->assertContains('/home/valet-user/.composer/vendor/autoload.php', $paths);
        $this->assertNotContains('/root/.composer/vendor/autoload.php', $paths);
    }

    public function test_it_supports_custom_composer_and_xdg_locations(): void
    {
        $paths = composer_autoload_candidates(
            '/source/cli',
            '/home/valet-user',
            '/home/valet-user/composer-home',
            '/home/valet-user/xdg-config'
        );

        $this->assertContains('/home/valet-user/composer-home/vendor/autoload.php', $paths);
        $this->assertContains('/home/valet-user/xdg-config/composer/vendor/autoload.php', $paths);
    }
}
