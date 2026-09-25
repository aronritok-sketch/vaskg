#!/bin/sh
# Blokk-markup ellenőrzése és kanonikus formára hozása a valódi blokkszerkesztőben.
#
#   dev/canon.sh <fájl> [home]
#
# A fájl tartalma egy piszkozat iu_template bejegyzésbe kerül, a dev/editor.js megnyitja
# a szerkesztőben (a hibás blokkokat az attribútumaikból újraépíti, az iu_style értékeket
# kiszámolja), elmenti, majd a tartalom visszaíródik a fájlba. A "home" kapcsolóval a
# helyi URL {{home}}-ra cserélődik (setup/ tartalmakhoz).
#
# Környezet: WP (wp-cli parancs, alapértelmezés: "wp"), SITE_URL, WP_ADMIN_USER, WP_ADMIN_PASS.
set -e
DIR=$(cd "$(dirname "$0")" && pwd)
F=$(readlink -f "$1")
WP=${WP:-wp}
cd /tmp
ID=$($WP eval-file "$DIR/canon.php" push "$F" 2>/dev/null | tail -1)
node "$DIR/editor.js" "$ID"
$WP eval-file "$DIR/canon.php" pull "$F" "$ID" $2 2>/dev/null | tail -1
