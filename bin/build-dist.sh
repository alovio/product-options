#!/usr/bin/env bash
#
# Build a clean wp.org distribution zip: production autoloader (no dev deps) and
# only shippable files (dev/test/build-tooling excluded). Output: dist/<slug>.zip
#
set -euo pipefail

SLUG="conditional-product-options"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist"
DEST="$OUT/$SLUG"

rm -rf "$OUT"
mkdir -p "$DEST"

# Copy only shippable files (keep build/, includes/, languages/, readme, main file, uninstall).
rsync -a \
	--exclude='.git' --exclude='.github' --exclude='.gitignore' --exclude='.distignore' \
	--exclude='.wp-env.json' --exclude='.playwright-mcp' --exclude='node_modules' \
	--exclude='src' --exclude='assets' --exclude='tests' --exclude='docs' --exclude='bin' \
	--exclude='package.json' --exclude='package-lock.json' --exclude='webpack.config.js' \
	--exclude='phpunit.xml.dist' --exclude='.phpunit.result.cache' --exclude='dist' \
	--exclude='vendor' \
	"$ROOT/" "$DEST/"

# Production-only Composer autoloader (the plugin has no runtime deps; this is just the PSR-4 map).
cp "$ROOT/composer.json" "$ROOT/composer.lock" "$DEST/" 2>/dev/null || cp "$ROOT/composer.json" "$DEST/"
( cd "$DEST" && composer install --no-dev --optimize-autoloader --quiet && rm -f composer.json composer.lock )

# Zip it.
( cd "$OUT" && zip -rqX "$SLUG.zip" "$SLUG" )
echo "Built: $OUT/$SLUG.zip"
