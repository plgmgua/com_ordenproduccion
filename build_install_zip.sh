#!/bin/bash
# Build com_ordenproduccion.zip for Joomla installation (manifest expects site/, admin/, media/ at zip root)
set -e
ROOT="$(cd "$(dirname "$0")" && pwd)"
COMP="$ROOT/com_ordenproduccion"
BUILD="$ROOT/build_zip"
ZIP="$ROOT/com_ordenproduccion.zip"

rm -rf "$BUILD" "$ZIP"
mkdir -p "$BUILD/site" "$BUILD/admin" "$BUILD/media"

cp "$COMP/com_ordenproduccion.xml" "$BUILD/"
test -f "$COMP/script.php" && cp "$COMP/script.php" "$BUILD/"
cp "$COMP/ordenproduccion.php" "$BUILD/site/"
cp -R "$COMP/src" "$COMP/tmpl" "$COMP/language" "$COMP/forms" "$BUILD/site/"
cp -R "$COMP/admin"/* "$BUILD/admin/"
cp -R "$COMP/media/css" "$COMP/media/js" "$BUILD/media/"

cd "$BUILD"
zip -r "$ZIP" . -x "*.git*" -x "*__MACOSX*" -x "*.DS_Store"
cd "$ROOT"
rm -rf "$BUILD"
echo "Created: $ZIP"
