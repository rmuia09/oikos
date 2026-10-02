#!/usr/bin/env bash
#
# Build the Oikos app for same-domain WordPress plugin deployment, and package
# it as an installable plugin zip.
#
#   ./deploy/build-plugin.sh
#
# Produces deploy/oikos-app.zip — upload it in WordPress under
# Plugins → Add New → Upload Plugin, then activate. The app appears at
# https://your-site/oikos/ (change OIKOS_SLUG below or in the plugin).
#
# No environment variables are needed: the app is built to call the REST API on
# its own origin, so there is no host to configure and no CORS to set up.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_DIR="$ROOT/deploy/oikos-app"
BUILD_DIR="$APP_DIR/build"
SLUG="oikos"

echo "==> Building the app (prefix /$SLUG, same-origin API)"
cd "$ROOT/gatsby"
if [ ! -d node_modules ]; then
  echo "    installing dependencies…"
  npm ci
fi
# Build from clean: Gatsby caches modules and leaves old hashed files in
# public/, which can ship a stale or mismatched bundle.
npx gatsby clean
GATSBY_PATH_PREFIX="/$SLUG" npx gatsby build --prefix-paths

echo "==> Bundling the build into the plugin"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
cp -R "$ROOT/gatsby/public/." "$BUILD_DIR/"

# Source maps are not needed in production and they expose the original source.
find "$BUILD_DIR" -name '*.map' -delete

echo "==> Packaging"
cd "$ROOT/deploy"
rm -f oikos-app.zip
zip -qr oikos-app.zip oikos-app -x '*/.*'

echo
echo "Done: $ROOT/deploy/oikos-app.zip"
echo
echo "Next:"
echo "  1. WordPress → Plugins → Add New → Upload Plugin → oikos-app.zip"
echo "  2. Activate it."
echo "  3. Open https://your-site/$SLUG/"
