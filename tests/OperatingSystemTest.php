<?php

use Illuminate\Container\Container;
use Valet\OperatingSystem;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

use function Valet\brew_command_as_root;

class OperatingSystemTest extends TestCase
{
    public function test_supported_operating_systems()
    {
        $this->assertTrue((new OperatingSystem('Darwin'))->isSupported());
        $this->assertTrue((new OperatingSystem('Linux'))->isSupported());
        $this->assertFalse((new OperatingSystem('Windows'))->isSupported());
    }

    public function test_platform_specific_defaults()
    {
        $originalSudoUser = $_SERVER['SUDO_USER'] ?? null;
        $originalHome = $_SERVER['HOME'] ?? null;
        $_SERVER['SUDO_USER'] = 'valet-test-user';
        $_SERVER['HOME'] = '/home/valet-test-user';

        $linux = new OperatingSystem('Linux');
        $mac = new OperatingSystem('Darwin');

        $this->assertSame('valet-test-user', $linux->user());
        $this->assertSame('/home/valet-test-user', $linux->userHomePath());
        $this->assertSame('valet-test-user', $linux->userGroup());
        $this->assertSame('valet-test-user', $linux->sudoersIdentity());
        $this->assertSame('staff', $mac->userGroup());
        $this->assertSame('admin', $mac->homebrewGroup());
        $this->assertSame('%admin', $mac->sudoersIdentity());

        if ($originalSudoUser === null) {
            unset($_SERVER['SUDO_USER']);
        } else {
            $_SERVER['SUDO_USER'] = $originalSudoUser;
        }

        if ($originalHome === null) {
            unset($_SERVER['HOME']);
        } else {
            $_SERVER['HOME'] = $originalHome;
        }
    }

    public function test_linux_root_brew_commands_keep_the_users_cache()
    {
        Container::setInstance(new Container);
        Container::getInstance()->instance(OperatingSystem::class, new class('Linux') extends OperatingSystem
        {
            public function userHomePath(): string
            {
                return '/home/valet-user';
            }
        });

        $this->assertSame(
            "sudo HOMEBREW_CACHE='/home/valet-user/.cache/Homebrew' XDG_CACHE_HOME='/home/valet-user/.cache' brew services start nginx",
            brew_command_as_root('services start nginx')
        );
    }
}
