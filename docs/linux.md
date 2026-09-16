# Valet on Linux (experimental)

Valet's Linux support currently targets systemd-based distributions that use `systemd-resolved`. It uses Homebrew for PHP, Nginx, DnsMasq, and service management, matching Valet's macOS architecture as closely as possible.

The Linux implementation differs only where the operating system requires it:

- `systemd-resolved` routes only the configured Valet TLD to DnsMasq on `127.0.0.1:5354`. Existing global and split-DNS configuration, including Tailscale DNS, remains active.
- systemd persists custom loopback aliases.
- the Valet CA is installed into the system trust store, Linuxbrew's OpenSSL bundle, and NSS databases used by Chromium and Firefox.
- the invoking user's real home directory and primary group replace macOS-specific `staff` and `admin` assumptions.
- root-level Homebrew service commands retain the invoking user's Homebrew metadata cache. Without this, Homebrew refuses to start services because it cannot download metadata as root.

## Omarchy prerequisites

Install Homebrew's build requirements and Valet's browser-certificate tooling using Omarchy's package helper:

```bash
omarchy pkg add base-devel procps-ng curl file git ruby-erb nss
```

`ruby-erb` prevents Omarchy's system Ruby from printing a `cannot load such file -- erb` warning while the Homebrew installer initializes its terminal output. The warning is harmless, but installing the package makes the process clean and reproducible. The `nss` package supplies `certutil`, which Valet uses to trust its CA in Chromium and Firefox certificate databases.

Install Homebrew using its official installer:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

Add the `brew shellenv` line printed by the installer to `~/.bashrc`, then either open a new terminal or evaluate it in the current shell. On a standard Linux installation this is:

```bash
printf '%s\n' 'eval "$(/home/linuxbrew/.linuxbrew/bin/brew shellenv bash)"' >> ~/.bashrc
eval "$(/home/linuxbrew/.linuxbrew/bin/brew shellenv bash)"
```

Install PHP and Composer:

```bash
brew install php composer
```

Omarchy already enables NetworkManager and `systemd-resolved`. Confirm that `/etc/resolv.conf` points to systemd-resolved's stub before installing Valet:

```bash
readlink -f /etc/resolv.conf
systemctl is-active systemd-resolved
```

The expected resolver target is `/run/systemd/resolve/stub-resolv.conf` and the service should report `active`. Do not disable `systemd-resolved` or replace `/etc/resolv.conf` with a DnsMasq-owned file.

## Install this development branch

Clone your fork and switch to its Linux development branch:

```bash
git clone <your-fork-url> ~/Projects/valet
cd ~/Projects/valet
git switch experiment/linux-omarchy
```

Register the checkout as a Composer path repository, then install that exact branch globally:

```bash
composer global config repositories.valet path "$PWD"
composer global require laravel/valet:dev-experiment/linux-omarchy
```

Ensure Composer's global binary directory is in `PATH`. Install Valet's sudoers rules before the services so the initial service installation needs only one administrator-authentication session:

```bash
export PATH="$(composer global config bin-dir --absolute):$PATH"
ln -s "$PWD/valet" "$(brew --prefix)/bin/valet"
valet trust
valet install
```

The initial symlink breaks a bootstrap cycle: the sudoers rule created by `valet trust` intentionally authorizes only Homebrew's Valet executable. `valet install` recreates the same symlink as part of its normal installation. If the destination already exists on a reinstall, skip the `ln` command.

`valet trust` asks for administrator authentication and installs sudoers rules for later Valet service operations; on Linux those rules apply only to the invoking user. Nginx needs elevated service management because it binds to ports 80 and 443. Valet also adds a narrowly scoped resolver route.

## Interactive Omarchy installer

The repository includes an idempotent interactive installer that performs the prerequisite checks and commands above:

```bash
cd ~/Projects/valet
./scripts/install-linux.sh
```

Review its summary and confirm when prompted. Use `--yes` to skip only the initial confirmation; sudo may still request your administrator password. You can select another checked-out branch with `--branch <name>`.

## Verify the installation

```bash
valet status
resolvectl query example.test
curl -I http://example.test
```

To exercise an actual project:

```bash
mkdir -p ~/Sites/example
cd ~/Sites/example
printf '<?php echo "Valet on Linux";' > index.php
valet link
valet secure
curl https://example.test
```

The final request should print `Valet on Linux` without disabling TLS verification.

## Troubleshooting

Use the Linux-aware diagnostics report:

```bash
valet diagnose --print
```

Useful focused checks are:

```bash
brew services list
systemctl status homebrew.nginx homebrew.dnsmasq systemd-resolved
resolvectl status
cat /etc/systemd/resolved.conf.d/valet.conf
ss -lntup | grep -E ':(53|80|443|5354)\b'
```

If browser TLS still warns after `valet secure`, fully restart the browser so it reopens its certificate database. Firefox profiles that are created after the CA is installed may require running `valet secure` once more.

If an older development checkout reports `Need to download ... but cannot as root`, update the branch and run `valet restart`. Valet's Linux service runner must pass the user's `HOMEBREW_CACHE` and `XDG_CACHE_HOME` into root-level `brew services` commands.
