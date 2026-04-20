#!/usr/bin/env bash
# Build mod_ordop_pending_approvals_<version>.zip for Joomla (manifest + PHP + tmpl + language at zip root).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
MOD_DIR="$ROOT/mod_ordop_pending_approvals"
MANIFEST="$MOD_DIR/mod_ordop_pending_approvals.xml"

if [[ ! -f "$MANIFEST" ]]; then
  echo "Missing manifest: $MANIFEST" >&2
  exit 1
fi

VER="$(sed -n 's/^[[:space:]]*<version>\([^<]*\)<\/version>.*/\1/p' "$MANIFEST" | head -1)"
VER="$(echo -n "$VER" | tr -d '\r\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
if [[ -z "$VER" ]]; then
  echo "Could not read <version> from manifest." >&2
  exit 1
fi

# Sanitize filename (manifest allows e.g. 1.0.0-STABLE)
SAFE_VER="$(printf '%s' "$VER" | tr -c 'A-Za-z0-9._-' '_')"
OUT="$ROOT/mod_ordop_pending_approvals_${SAFE_VER}.zip"

rm -f "$OUT"
(
  cd "$MOD_DIR"
  zip -r "$OUT" . \
    -x "*.git*" \
    -x "*__MACOSX*" \
    -x "*.DS_Store" \
    -x ".idea/*" \
    -x "*.swp"
)

echo "Created: $OUT"
echo "Install: Joomla → System → Install → Extensions → Upload Package File"
