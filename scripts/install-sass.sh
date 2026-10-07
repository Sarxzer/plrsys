#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
TOOLS_DIR="$ROOT_DIR/.tools"
ARCHIVE="$TOOLS_DIR/dart-sass.tar.gz"
SASS_VERSION="1.105.1"

case "$(uname -m)" in
    x86_64|amd64)
        ASSET_SUFFIX="linux-x64.tar.gz"
        SHA256="9046fdd4a31a524a0020298c55d9e155d4c22363ef268e8992574335a72c6a6f"
        ;;
    aarch64|arm64)
        ASSET_SUFFIX="linux-arm64.tar.gz"
        SHA256="7f53a2a77ef4aaf24a9f2f8c826759ed93b38954430a2e32130921a2e16a4a1c"
        ;;
    *)
        printf 'Unsupported architecture: %s\n' "$(uname -m)" >&2
        exit 1
        ;;
esac

mkdir -p "$TOOLS_DIR"
DOWNLOAD_URL="https://github.com/sass/dart-sass/releases/download/${SASS_VERSION}/dart-sass-${SASS_VERSION}-${ASSET_SUFFIX}"

printf 'Downloading Dart Sass %s (%s)...\n' "$SASS_VERSION" "$ASSET_SUFFIX"
curl -fL "$DOWNLOAD_URL" -o "$ARCHIVE"
printf 'Verifying SHA256 checksum...\n'
printf '%s  %s\n' "$SHA256" "$ARCHIVE" | sha256sum -c -
rm -rf "$TOOLS_DIR/dart-sass"
tar -xzf "$ARCHIVE" -C "$TOOLS_DIR"
rm -f "$ARCHIVE"
chmod +x "$TOOLS_DIR/dart-sass/sass"
printf 'Installed Dart Sass at %s\n' "$TOOLS_DIR/dart-sass/sass"
