<?php

use Valet\OperatingSystem;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

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
}
