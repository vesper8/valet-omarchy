#!/usr/bin/env bash

set -Eeuo pipefail

VALET_BRANCH="experiment/linux-omarchy"
ASSUME_YES=false

usage() {
    cat <<'EOF'
Install the current Laravel Valet checkout on an Omarchy Linux system.

Usage: ./scripts/install-linux.sh [--yes] [--branch BRANCH]

  --yes            Skip the initial confirmation.
  --branch BRANCH  Composer branch constraint (default: experiment/linux-omarchy).
EOF
}

while (($#)); do
    case "$1" in
        --yes)
            ASSUME_YES=true
            shift
            ;;
        --branch)
            VALET_BRANCH="${2:?--branch requires a branch name}"
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            printf 'Unknown option: %s\n' "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [[ "$(uname -s)" != "Linux" ]]; then
    printf 'This installer only supports Linux.\n' >&2
    exit 1
fi

if [[ "$EUID" -eq 0 ]]; then
    printf 'Run this installer as your normal user, not root.\n' >&2
    exit 1
fi

if ! command -v omarchy >/dev/null 2>&1; then
    printf 'This installer currently targets Omarchy. See docs/linux.md for manual installation.\n' >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VALET_SOURCE="$(cd "$SCRIPT_DIR/.." && pwd)"
BREW_BIN=/home/linuxbrew/.linuxbrew/bin/brew
BREW_SHELLENV='eval "$(/home/linuxbrew/.linuxbrew/bin/brew shellenv bash)"'

if [[ ! -f "$VALET_SOURCE/composer.json" ]]; then
    printf 'Could not find the Valet source checkout at %s.\n' "$VALET_SOURCE" >&2
    exit 1
fi

if [[ "$ASSUME_YES" != true ]]; then
    cat <<EOF
This will:
  - install Omarchy packages needed by Homebrew and browser certificate trust;
  - install Homebrew in /home/linuxbrew/.linuxbrew when absent;
  - install Homebrew PHP and Composer;
  - install this checkout as laravel/valet:dev-$VALET_BRANCH;
  - add Valet's user-scoped sudoers rules; and
  - install and start PHP-FPM, Nginx, DnsMasq, and a systemd-resolved route.

Existing NetworkManager, systemd-resolved, and Tailscale DNS settings are preserved.
EOF
    read -r -p 'Continue? [y/N] ' reply
    [[ "$reply" =~ ^[Yy]$ ]] || exit 0
fi

PREREQUISITES=(base-devel procps-ng curl file git ruby-erb nss)
MISSING_PREREQUISITES=()

for package in "${PREREQUISITES[@]}"; do
    pacman -Q "$package" >/dev/null 2>&1 || MISSING_PREREQUISITES+=("$package")
done

if ((${#MISSING_PREREQUISITES[@]})) || [[ ! -x "$BREW_BIN" ]]; then
    printf '\nChecking administrator access...\n'
    sudo -v
fi

if ((${#MISSING_PREREQUISITES[@]})); then
    printf '\nInstalling missing system prerequisites...\n'
    omarchy pkg add "${MISSING_PREREQUISITES[@]}"
fi

if ! systemctl is-active --quiet systemd-resolved; then
    printf 'systemd-resolved must be active before Valet can be installed.\n' >&2
    exit 1
fi

if [[ "$(readlink -f /etc/resolv.conf)" != "/run/systemd/resolve/stub-resolv.conf" ]]; then
    printf '/etc/resolv.conf must point to /run/systemd/resolve/stub-resolv.conf.\n' >&2
    exit 1
fi

if [[ ! -x "$BREW_BIN" ]]; then
    printf '\nInstalling Homebrew...\n'
    NONINTERACTIVE=1 /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
fi

eval "$("$BREW_BIN" shellenv bash)"

if ! grep -qxF "$BREW_SHELLENV" "$HOME/.bashrc" 2>/dev/null; then
    printf '\n%s\n' "$BREW_SHELLENV" >> "$HOME/.bashrc"
fi

printf '\nInstalling Homebrew PHP and Composer...\n'
brew install php composer

COMPOSER_BIN_DIR="$(composer global config bin-dir --absolute 2>/dev/null)"
COMPOSER_PATH_LINE="export PATH=\"$COMPOSER_BIN_DIR:\$PATH\""

if ! grep -qxF "$COMPOSER_PATH_LINE" "$HOME/.bashrc" 2>/dev/null; then
    printf '%s\n' "$COMPOSER_PATH_LINE" >> "$HOME/.bashrc"
fi

export PATH="$COMPOSER_BIN_DIR:$PATH"

printf '\nInstalling this Valet checkout with Composer...\n'
composer global config repositories.valet path "$VALET_SOURCE"
composer global require "laravel/valet:dev-$VALET_BRANCH"

VALET_BREW_BIN="$(brew --prefix)/bin/valet"

if [[ -e "$VALET_BREW_BIN" || -L "$VALET_BREW_BIN" ]]; then
    if [[ "$(readlink -f "$VALET_BREW_BIN")" != "$VALET_SOURCE/valet" ]]; then
        printf '%s already exists and does not point to this checkout.\n' "$VALET_BREW_BIN" >&2
        exit 1
    fi
else
    ln -s "$VALET_SOURCE/valet" "$VALET_BREW_BIN"
fi

hash -r

printf '\nInstalling Valet privilege rules and services...\n'
"$VALET_BREW_BIN" trust
"$VALET_BREW_BIN" install

printf '\nVerifying Valet...\n'
"$VALET_BREW_BIN" status
resolvectl query valet-health.test

cat <<'EOF'

Valet installation completed.

Open a new shell, create or enter a project directory, and run:
  valet link
  valet secure
EOF
