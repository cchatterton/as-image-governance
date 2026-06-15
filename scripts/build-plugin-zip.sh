#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="as-image-governance"
DIST_DIR="dist"

cd "$(dirname "$0")/.."

rm -rf "$DIST_DIR/$PLUGIN_SLUG"
rm -f "$PLUGIN_SLUG.zip"
mkdir -p "$DIST_DIR"
cp -R "$PLUGIN_SLUG" "$DIST_DIR/$PLUGIN_SLUG"

find "$DIST_DIR/$PLUGIN_SLUG" -name ".DS_Store" -delete
rm -rf "$DIST_DIR/$PLUGIN_SLUG/node_modules"
rm -f "$DIST_DIR/$PLUGIN_SLUG/$PLUGIN_SLUG.zip"

cd "$DIST_DIR"
rm -f "$PLUGIN_SLUG.zip"
zip -qr "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"
cp "$PLUGIN_SLUG.zip" "../$PLUGIN_SLUG.zip"
