#!/usr/bin/env bash
#
# Baut ein WordPress.org-taugliches Plugin-ZIP — getrennt vom GitHub-Build
# (.github/workflows/release-zip.yml bleibt unverändert, versorgt weiter die
# bestehenden Vereine per Auto-Update). Unterschiede zum GitHub-Paket:
#
#   1. KEIN GitHub-Selbst-Updater: includes/class-updater.php und die
#      plugin-update-checker-Lib fehlen. class-plugin.php lädt/instanziiert den
#      Updater nur, wenn vorhanden → im WP.org-Build sauber übersprungen
#      (Updates macht dort der WP-Core). WP.org verbietet Selbst-Updater.
#   2. KEIN "Update URI"-Header: ein fremder Update-URI würde WP.org-Updates
#      blockieren.
#   3. Stable tag der readme = aktuelle Version.
#
# Danach: ZIP bei wordpress.org/plugins/developers/add/ einreichen (einmaliges
# Review), nach Freigabe per SVN (trunk/tags/assets) pflegen.
#
# Usage: bash scripts/build-wporg.sh
set -euo pipefail

slug=rc-racemap-club-calendar
root="$(cd "$(dirname "$0")/.." && pwd)"
ver=$(grep -m1 "RC_RCC_VERSION" "$root/$slug.php" | sed "s/.*'\([0-9.]*\)'.*/\1/")
out="$root/build-wporg"

rm -rf "$out"
mkdir -p "$out/$slug"

rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.gitignore' \
  --exclude='CLAUDE.md' \
  --exclude='docs' \
  --exclude='dev-preview.html' \
  --exclude='build' \
  --exclude='build-wporg' \
  --exclude='scripts' \
  --exclude='BRIEF-*.md' \
  --exclude='*.po' \
  --exclude='*.pot' \
  --exclude='includes/lib' \
  --exclude='includes/class-updater.php' \
  "$root/" "$out/$slug/"

# "Update URI"-Header entfernen.
sed -i.bak '/^[[:space:]]*\*[[:space:]]*Update URI:/d' "$out/$slug/$slug.php"
rm -f "$out/$slug/$slug.php.bak"

# Stable tag an die aktuelle Version angleichen.
if [ -f "$out/$slug/readme.txt" ]; then
  sed -i.bak "s/^Stable tag:.*/Stable tag: $ver/" "$out/$slug/readme.txt"
  rm -f "$out/$slug/readme.txt.bak"
fi

( cd "$out" && zip -qr "$slug-wporg-$ver.zip" "$slug" )

echo "Gebaut: build-wporg/$slug-wporg-$ver.zip (Version $ver)"
echo "--- Compliance-Kontrolle (sollte NICHTS zeigen) ---"
if unzip -l "$out/$slug-wporg-$ver.zip" | grep -iE "class-updater|plugin-update-checker"; then
  echo "  ⚠ FEHLER: Updater ist noch im Paket!"; exit 1
fi
if unzip -p "$out/$slug-wporg-$ver.zip" "$slug/$slug.php" | grep -qi "Update URI:"; then
  echo "  ⚠ FEHLER: 'Update URI'-Header noch vorhanden!"; exit 1
fi
echo "  ✓ kein Selbst-Updater, keine Update-URI"
echo "  Stable tag: $(unzip -p "$out/$slug-wporg-$ver.zip" "$slug/readme.txt" | grep -i '^Stable tag:')"
