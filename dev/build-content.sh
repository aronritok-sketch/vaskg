#!/bin/sh
# A setup/ tartalmak újragenerálása: oldalak (pages.py), módosított meglévő
# tartalmak (managed.py), majd ezek és a téma sablonfájljainak (templates/)
# kanonikus formára hozása a blokkszerkesztőben (canon.sh).
set -e
ROOT=$(cd "$(dirname "$0")/.." && pwd)
python3 "$ROOT/dev/content/pages.py" > /dev/null
python3 "$ROOT/dev/content/managed.py" > /dev/null
python3 "$ROOT/dev/content/posts.py" > /dev/null
for f in "$ROOT"/wp-content/themes/vasskisgep/setup/pages/*.html "$ROOT"/wp-content/themes/vasskisgep/setup/managed/*.html "$ROOT"/wp-content/themes/vasskisgep/setup/posts/*.html; do
	printf '%s: ' "$(basename "$f")"
	"$ROOT/dev/canon.sh" "$f" home | head -1
done
for f in "$ROOT"/wp-content/themes/vasskisgep/templates/*.html; do
	printf '%s: ' "$(basename "$f")"
	"$ROOT/dev/canon.sh" "$f" | head -1
done
