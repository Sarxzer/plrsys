#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
SASS_BIN="${SASS_BIN:-$ROOT_DIR/.tools/dart-sass/sass}"
SASS_SOURCE="$ROOT_DIR/src/scss/style.scss"
CSS_OUTPUT="$ROOT_DIR/public/assets/css/style.css"

if [ ! -x "$SASS_BIN" ]; then
    printf 'Dart Sass is not installed. Run: scripts/install-sass.sh\n' >&2
    exit 1
fi

exec "$SASS_BIN" "$@" "$SASS_SOURCE" "$CSS_OUTPUT" --style=expanded --source-map
