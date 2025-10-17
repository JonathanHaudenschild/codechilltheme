#!/usr/bin/env bash
set -euo pipefail

THEME_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$THEME_ROOT/dist"
THEME_SLUG="codechilltheme"
PACKAGE_DIR="$DIST_DIR/$THEME_SLUG"
ZIP_PATH="$DIST_DIR/${THEME_SLUG}.zip"

printf "\n🚀 Packaging %s...\n" "$THEME_SLUG"

# Ensure dependencies are built
if ! command -v npm >/dev/null 2>&1; then
  echo "Error: npm is required to build assets."
  exit 1
fi

( cd "$THEME_ROOT" && npm install >/dev/null 2>&1 )
( cd "$THEME_ROOT" && npm run build )

rm -rf "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR"

rsync -a --delete \
  --exclude '.git/' \
  --exclude '.gitignore' \
  --exclude 'dist/' \
  --exclude 'scripts/' \
  --exclude 'node_modules/' \
  --exclude 'package-lock.json' \
  --exclude '*.map' \
  --exclude '.DS_Store' \
  "$THEME_ROOT/" "$PACKAGE_DIR/"

rm -f "$ZIP_PATH"
( cd "$DIST_DIR" && zip -qr "$ZIP_PATH" "$THEME_SLUG" )

printf "✅ Theme packaged: %s\n" "$ZIP_PATH"
