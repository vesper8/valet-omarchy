<?php

namespace Valet;

use DomainException;

class OperatingSystem
{
    public function __construct(protected ?string $family = null) {}

    /**
     * Get the operating-system family reported by PHP.
     */
    public function family(): string
    {
        return $this->family ?? PHP_OS_FAMILY;
    }

    /**
     * Determine whether Valet is running on macOS.
     */
    public function isMacOS(): bool
    {
        return $this->family() === 'Darwin';
    }

    /**
     * Determine whether Valet is running on Linux.
     */
    public function isLinux(): bool
    {
        return $this->family() === 'Linux';
    }

    /**
     * Determine whether the current operating system is supported.
     */
    public function isSupported(): bool
    {
        return $this->isMacOS() || $this->isLinux();
    }

    /**
     * Find the Homebrew executable without relying on sudo's PATH.
     */
    public function findHomebrewBinary(): ?string
    {
        $fromPath = trim((string) shell_exec('command -v brew 2>/dev/null'));
        $candidates = array_filter([
            $fromPath,
            '/opt/homebrew/bin/brew',
            '/usr/local/bin/brew',
            '/home/linuxbrew/.linuxbrew/bin/brew',
        ]);

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $this->isMacOS() && $candidate === $fromPath ? 'brew' : $candidate;
            }
        }

        return null;
    }

    /**
     * Get the validated Homebrew executable.
     */
    public function homebrewBinary(): string
    {
        return $this->findHomebrewBinary()
            ?? throw new DomainException('Valet requires Homebrew to be installed and available in your PATH.');
    }

    /**
     * Get the validated Homebrew prefix.
     */
    public function homebrewPrefix(): string
    {
        $binary = $this->homebrewBinary();
        $prefix = trim((string) shell_exec(escapeshellarg($binary).' --prefix 2>/dev/null'));

        if ($prefix === '' || $prefix[0] !== '/' || ! is_dir($prefix)) {
            throw new DomainException('Valet could not determine a valid Homebrew prefix.');
        }

        return $prefix;
    }

    /**
     * Get the non-root user running Valet.
     */
    public function user(): string
    {
        return $_SERVER['SUDO_USER'] ?? $_SERVER['USER'] ?? get_current_user();
    }

    /**
     * Get the non-root user's home directory without trusting sudo's HOME.
     */
    public function userHomePath(): string
    {
        if (function_exists('posix_getpwnam') && ($account = posix_getpwnam($this->user()))) {
            return $account['dir'];
        }

        return $_SERVER['HOME'];
    }

    /**
     * Get the non-root user's primary group.
     */
    public function userGroup(): string
    {
        if ($this->isMacOS()) {
            return 'staff';
        }

        if (function_exists('posix_getpwnam') && function_exists('posix_getgrgid')
            && ($account = posix_getpwnam($this->user()))
            && ($group = posix_getgrgid($account['gid']))) {
            return $group['name'];
        }

        return $this->user();
    }

    /**
     * Get the group Homebrew-managed files should be restored to.
     */
    public function homebrewGroup(): string
    {
        return $this->isMacOS() ? 'admin' : $this->userGroup();
    }

    /**
     * Get the sudoers identity allowed to manage Valet services.
     */
    public function sudoersIdentity(): string
    {
        return $this->isMacOS() ? '%admin' : $this->user();
    }
}
