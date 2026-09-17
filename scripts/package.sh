#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="1.0.0"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/wordpress-fetch"
for path in wordpress-fetch.php uninstall.php composer.json README.md readme.txt LICENSE CHANGELOG.md src blocks languages templates docs; do
  if [[ -e "$ROOT/$path" ]]; then cp -R "$ROOT/$path" "$STAGE/wordpress-fetch/"; fi
done
mkdir -p "$STAGE/wordpress-fetch/assets"
cp -R "$ROOT/assets/dist" "$STAGE/wordpress-fetch/assets/"
mkdir -p "$ROOT/build"
rm -f "$ROOT/build/wordpress-fetch-$VERSION.zip"
(cd "$STAGE" && zip -qr "$ROOT/build/wordpress-fetch-$VERSION.zip" wordpress-fetch)
unzip -t "$ROOT/build/wordpress-fetch-$VERSION.zip"
if unzip -l "$ROOT/build/wordpress-fetch-$VERSION.zip" | grep -Eq '(^|/)(\.git|node_modules|tests|\.env|phpunit\.xml|phpstan\.neon)'; then
  echo "Package contains development-only files" >&2
  exit 1
fi
echo "$ROOT/build/wordpress-fetch-$VERSION.zip"
