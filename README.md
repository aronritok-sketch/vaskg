# Vass Kisgép webshop

WordPress + WooCommerce webshop a Vass Kisgép 2011 Kft. részére, az **Infinite Unity (`iu_theme`)** keretrendszerre építve.

- Dev oldal: `https://dev.otletoldal.hu/vasskisgep`
- WordPress 7.1, WooCommerce 11.1, PHP 8.4
- Téma: `iu_theme` (keretrendszer) + `vasskisgep` (child téma, itt van minden projekt-specifikus kód)
- MU-pluginek: `iu_*` (keretrendszer részei, változatlanok)

## Mappák

```
wp-content/
├── themes/iu_theme/            keretrendszer (NEM módosítjuk)
├── themes/vasskisgep/          child téma
│   ├── inc/theme.php           belépési pont
│   ├── inc/setup.php           egyszeri telepítő lépések (beállítások, oldalak, menü)
│   ├── inc/shop.php            WooCommerce kiegészítések
│   ├── inc/lists.php           kedvencek és összehasonlítás
│   ├── inc/forms.php           űrlap e-mailek
│   ├── inc/reviews.php         Google értékelések
│   ├── inc/blocks/*/block.php  saját blokkok (iucb_add_block)
│   ├── templates/*.html        iu sablonok fájlként ({típus}_{pozíció}.html)
│   ├── setup/pages/*.html      új oldalak tartalma (a telepítő hozza létre)
│   ├── setup/managed/*.html    módosított meglévő tartalmak + manifest.json
│   └── assets/shop.css, shop.js
└── mu-plugins/iu_*             keretrendszer (NEM módosítjuk)
dev/                            helyi fejlesztés, tartalom-generálás, ellenőrzés
```

A `wp-content/plugins`, `uploads` és `languages` mappák nincsenek verziókövetve (a szerveren vannak).

## Élesítés a dev szerverre

1. Töltsd fel a `wp-content/themes/vasskisgep` mappát (felülírva a régit). Más mappához nem kell nyúlni.
2. Telepítsd a **Barion Payment Gateway for WooCommerce** bővítményt (`pay-via-barion-for-woocommerce`), ha kártyás fizetés kell.
3. Lépj be adminisztrátorként. Az első admin oldalbetöltéskor lefutnak a telepítő lépések, és egy zöld értesítés felsorolja, mi történt.
   - WP-CLI-vel is futtatható: `wp vasskisgep setup --user=<admin>`.
   - Minden lépés csak egyszer fut (`vasskisgep_setup_version` opció).
4. A meglévő fejlécet, láblécet, kezdőlapot és Termékek oldalt a telepítő **csak akkor írja felül, ha 2026-09-25 óta nem szerkesztették**. Ha közben módosultak, az értesítésben „FIGYELEM” sor jelenik meg, és az új változat a `setup/managed/` mappában található; ezt kézzel kell átvezetni.
5. Beállítások → Közvetlen linkek: egy mentés nem árt (a telepítő a Tudástár URL-jeit `/tudastar/cikk/`-re állítja).

### Mit állít be a telepítő

| Terület | Beállítás |
|---|---|
| Bolt | cím (Kisvárda), csak Magyarország, HUF, e-mail feladó: `info@vasskisgep.hu` |
| ÁFA | 27%, bruttó árak megadása és megjelenítése |
| Szállítás | Házhozszállítás futárszolgálattal; Személyes átvétel Kisvárdán és Vásárosnaményban (ingyenes) |
| Fizetés | Fizetés átvételkor; Előre utalás (csak ha van bankszámlaszám); Barion (ha telepítve és beállítva) |
| Katalógus | 4 szintű kategóriafa (`setup/catalog/categories.json`: Kerti és erdészeti gépek a Figma megamenüje szerint; Építőipari gépek és Alkatrészek és karbantartás **javaslat, egyeztetendő**), szűrhető tulajdonságok (Lökettérfogat cm³, Teljesítmény LE, Tömeg kg, Vágásszélesség cm, Garancia hó), márkák logóval, évszak címkék |
| Bemutató termékek | 23 db `DEMO-001…023` cikkszámú termék képpel, tulajdonságokkal, akcióval, készletállapotokkal, kapcsolódó termékekkel, néhány minta értékeléssel (a TESZT001 termékek piszkozatba kerülnek) |
| Tudástár | 3 cikk a lorem ipsum bejegyzések helyett, témák: Gépválasztás, Karbantartás, Szezonális teendők |
| E-mailek | WooCommerce levelek színei az arculat szerint |
| Termékoldal | értékelések bekapcsolva, tájékoztató doboz a bemutató gépeknél |
| Rólunk | a Figma szerinti oldal a terv fotóival és 10 márkalogóval (`setup/media/` → médiatár), „Saját oldalfejléc” bekapcsolva |
| WooCommerce oldalak | Fiókom iu/section → iu/row → iu/column keretben; a Termékek oldal régi szűrője helyett az új szűrős lista |
| Lábléc, megamenü | a design szövegei; rövid menünevek (`vk_menu_label`) és „Összes …” linkek (`vk_menu_all`) kategória term metában |
| Oldalak | Rólunk (Figma szerint), Szolgáltatások, Ajánlatkérés, Szerviz, Kapcsolat, Kedvencek, Összehasonlítás, Szállítás és fizetés, ÁSZF, Adatkezelési tájékoztató, Elállás és visszaküldés |
| Menü | Főmenü linkjei, a Termékek alatt a főkategóriák |
| Tartalom | fejléc: Kedvencek/Összehasonlítás link; lábléc: hibás adatkezelési link javítva; kezdőlap: gomb-linkek, válogatott karusszelek (kiemelt, szezonális, népszerű), valódi Google értékelések a lorem ipsum helyett; Termékek oldal: szűrős terméklista |

## Teendők élesítés előtt

- [ ] **Bankszámlaszám**: WooCommerce → Beállítások → Fizetés → Előre utalás (addig ki van kapcsolva).
- [ ] **Barion**: bővítmény telepítése, POSKey, Barion e-mail cím; élesítés előtt teszt módban kipróbálni.
- [ ] **Szállítási díj**: a „Házhozszállítás” díja most 1000 Ft + ÁFA (a korábbi beállítás maradt). Nagy gépekre valószínűleg súly vagy termékosztály szerinti díj kell.
- [ ] **Jogi szövegek**: az ÁSZF és az Adatkezelési tájékoztató `[kitöltendő]` részei (cégjegyzékszám, adószám, tárhely, futár, számlázó, könyvelő). A szövegek minták, jogásszal ellenőriztetni kell.
- [ ] **Számlázás**: számlázó bővítmény (Számlázz.hu vagy Billingo) a NAV-bejelentéshez.
- [ ] **E-mail kézbesítés**: SMTP (pl. WP Mail SMTP) a `vasskisgep.hu` domainről, SPF/DKIM beállítással.
- [ ] **Google értékelések**: Megjelenés → Beállítások → Google értékelések: Places API kulcs és Place ID. Addig a kezdőlapon csak a „Véleményeink a Google-on” gomb látszik.
- [ ] **Közösségi linkek**: a láblécben az Instagram és a YouTube ikon még `#`-re mutat.
- [ ] **Bemutató termékek törlése**: Termékek → keresés „DEMO-” → mind törlése (a minta értékelések velük törlődnek). Az 5 „TESZT001” piszkozat is törölhető.
- [ ] **Termékek**: valódi termékek felvitele vagy importja (minta és leírás: `dev/import/`) (kategória, márka, a szűrhető tulajdonságok globális attribútumként, képek, súly). Termékenként: promóciós szöveg, Cofidis jelölés, dokumentumok (PDF), tájékoztató doboz, keresztértékesítés (= „Kapcsolódó termékek” oldalsáv). Szezonális termékeknél az évszak címke.
- [ ] **Kategóriafa**: az Építőipari gépek és az Alkatrészek és karbantartás ágat a Figma nem tartalmazza (a „Mega dropdown szöveges tartalma” dokumentum hiányzott) – egyeztetni, és a `setup/catalog/categories.json` alapján vagy az adminban javítani.
- [ ] **Nyitvatartás**: a Rólunk oldalon a Figma szerinti „H–P: 7:30–16:30, Szo: 7:30–12:00” szerepel – ellenőrizni.
- [ ] **Keresőmotorok**: élesítéskor Beállítások → Olvasás → „Keresőmotorok…” jelölés kivétele.
- [ ] **Cofidis** (áruhitel):
    - *Fizetési mód* (`inc/cofidis.php`, `inc/cofidis-gateway.php`): WooCommerce → Beállítások → Fizetés → **Cofidis áruhitel** → Engedélyezés. Csak akkor jelenik meg a pénztárban, ha a kosár minden terméke „Cofidis áruhitel” jelölésű és a végösszeg a min./max. határon belül van. A rendelés „Függőben” állapotba kerül (a készlet lefoglalva), a vásárló tájékoztatást kap (köszönőoldal + e-mail, opcionálisan a Cofidis hitelkérelmi linkjével). Hitelbírálat után a rendelésnél a *Rendelés műveletek* közül: „Cofidis: hitel jóváhagyva” (→ Feldolgozás alatt) vagy „elutasítva” (→ Törölve, a készlet visszaáll).
    - *Kalkulátor*: Megjelenés → Beállítások → Cofidis áruhitel fülön THM, ügyleti kamat, futamidők, min./max. hitelösszeg, reprezentatív példa (a Cofidis szerződés adatai).
    - *Online hitelkérelem (API)*: a Cofidis **webshop / online áruhitel** kereskedői dokumentációja kell hozzá (a PSD2 számlainformációs API nem erre való). Bekötési pontok: `vasskisgep_cofidis_start_application` szűrő (a rendelésből hitelkérelmet indít, az átirányítási URL-t adja vissza) és `vasskisgep_cofidis_callback` szűrő a `/wp-json/vasskisgep/v1/cofidis/callback` végponthoz (hitelesség ellenőrzése, `['order_id' => …, 'status' => 'approved'|'rejected'|'pending']`). Bekötés nélkül a végpont 501-gyel elutasít.

## Funkciók

- **Termékoldal** (`templates/single_product_content.html`, Figma szerint): galéria kedvezmény-jelvénnyel, promó szöveggel és márkával; cikkszám, készlet + csillagos értékelés, ár doboz (régi/akciós ár, áfa megjegyzés, Cofidis jelvény), tulajdonság-csempék, mennyiség + Kosárba (AJAX), kedvencek/összehasonlítás; „Kapcsolódó termékek” oldalsáv (keresztértékesítés); fülek: Részletek (tájékoztató doboz, leírás, műszaki adatok), Dokumentumok, Vélemények (WooCommerce értékelések, moderálás a Termékek → Értékelések alatt), Kérdése van?; szállítási és fizetési módok a WooCommerce beállításaiból; Cofidis kalkulátor; hasonló és legutóbb megtekintett termékek.
- **Terméklista** (`vasskisgep/product-listing` blokk; Termékek oldal, kategória, márka, címke): felső szűrősáv (Kategória, Gyártó, Ár, Készlet/Akciós) legördülőkkel és „(N) termék megtekintése” előnézettel, rács/lista nézet, rendezés, „Részletes szűrés” tulajdonságok szerint, „Továbbiak mutatása”. A szűrés URL-paraméterekben van (megosztható link).
- **Termékkártya**: kedvezmény, promó szöveg, Cofidis jelvény, márka, 3 fő tulajdonság, kedvencek/összehasonlítás ikonok a képen.
- **Megamenü** (`inc/megamenu.php`): a főmenü Termékek pontja alatt fülek (főkategóriák + Gyártók), 4 szintű kategóriafa; mobilon a menü saját almenüje.
- **Rólunk** oldal a Figma szerint (saját fejléc: Oldal szerkesztése → „Oldal megjelenése” → Saját oldalfejléc).
- **Kereső** (`inc/search.php`): gépelés közben teljes szélességű találati panel a fejléc alatt (termékek képpel/árral, kategóriák, Tudástár); Enter → találati oldal.
- **Kedvencek tárolása**: vendégnél sütiben (1 év, a böngésző törölheti); belépett vásárlónál az adatbázisban is, és ha a süti elveszett, onnan áll vissza.
- **Képek a setup tartalmakban**: `{{media:fajl.jpg}}`, `{{media-thumb:…}}`, `{{media-id:…}}` helyőrzők a `setup/media/` fájlaira; a telepítő tölti fel őket a médiatárba.
- **Keresés**: termékek (név, leírás, cikkszám) és Tudástár cikkek.
- **Kedvencek és összehasonlítás**: sütiben tárolva, bejelentkezett vásárlónál a fiókba is mentve; számláló a fejlécben; legfeljebb 4 termék hasonlítható össze.
- **Űrlapok**: visszahívás (lábléc), ajánlatkérés, szervizbejelentés, kapcsolat, termékkérdés. Az e-mail minden kitöltött mezőt tartalmaz, a válaszcím a kitöltő e-mail címe. Címzett: `Forms::RECIPIENT` (`inc/forms.php`).
- **Pénztár**: cégnév és adószám (a rendelésben és az e-mailekben is megjelenik), ÁSZF és adatkezelés elfogadása.

## Helyi fejlesztés

Szükséges: PHP 8.2+ (mysqli), MariaDB/MySQL, WP-CLI, Node + Playwright (a tartalom-ellenőrzéshez).

```sh
# adatbázis (pl. Dockerrel)
docker run -d --name vkdb -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=vasskisgep -p 3306:3306 mariadb:10.11
docker exec -i vkdb mariadb -uroot -proot vasskisgep < dump.sql

# WordPress core egy külön mappában, a wp-content erre a repóra mutat
cp dev/wp-config.sample.php <wp>/wp-config.php   # WP_CONTENT_DIR = <repo>/wp-content
wp search-replace 'https://dev.otletoldal.hu/vasskisgep' 'http://localhost:8080' --all-tables --skip-columns=guid
php -S localhost:8080 -t <wp> dev/router.php
wp vasskisgep setup
```

A `dev/wp-config.sample.php` `VASSKISGEP_SETUP_MANUAL` konstansa helyben kikapcsolja az automatikus futást (csak WP-CLI-ből fut).

### Tartalmak szerkesztése

Az oldalak és a módosított meglévő tartalmak forrása a `dev/content/pages.py` és a `dev/content/managed.py`. A blokk-markupot **nem kézzel írjuk**: a generátor vázlatot készít, amit a `dev/canon.sh` a valódi blokkszerkesztőn futtat át. Ez ellenőrzi a blokkokat, előállítja a mentett HTML-t és az `iu_style` értékeket.

```sh
WP="wp --path=<wp>" SITE_URL=http://localhost:8080 WP_ADMIN_USER=... WP_ADMIN_PASS=... dev/build-content.sh
```

Egy blokk érvényes, ha a `dev/editor.js` kimenetében `"invalid":[]` szerepel. Az ellenőrzés csak olvasva is lefuttatható: `DRY=1 node dev/editor.js <post ID-k>`.

## A keretrendszerben talált hibák (nem javítottuk, a child téma kerüli meg őket)

- `iu_theme/inc/forms.php`: az e-mail csak a `message` attribútum fix szövegét küldi, a kitöltött mezőket nem; a `From` fejléc elgépelés (`$from`) miatt sosem áll be; a checkbox kitöltését szerveroldalon nem ellenőrzi. → `inc/forms.php`
- `iu_woocommerce/inc/blocks.php`: `is_archive('product_cat')` minden archívumon igaz, ezért márka- és címkeoldalon üres a terméklista. → `Shop::fix_archive_category()`
- `iu_woocommerce/inc/blocks.php`: a `search` blokk `input` típusánál hiányzik a záró `>` (`'</div'`); a `filter_params` `post__id` elgépelés.
- `iu_theme/inc/blocks/breadcrumbs/render.php`: több kategóriás terméknél `$cats` definiálatlan; márka/címke oldalt nem kezeli. → `Shop::term_breadcrumbs()`
- A WooCommerce márka-archívum saját sablont töltene fejléc és lábléc nélkül. → `Shop::brand_template()`
- A mu-pluginek és a téma relatív `require_once('blocks.php')`-t használnak; ha a PHP munkakönyvtárában van ilyen nevű fájl, rossz fájl töltődik be.
