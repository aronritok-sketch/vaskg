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
4. A meglévő fejlécet, láblécet, kezdőlapot, Termékek oldalt és a terméklista kártyát a telepítő **csak akkor írja felül, ha 2026-09-25 óta nem szerkesztették**. Ha közben módosultak, az értesítésben „FIGYELEM” sor jelenik meg, és az új változat a `setup/managed/` mappában található; ezt kézzel kell átvezetni.
5. Beállítások → Közvetlen linkek: egy mentés nem árt (a telepítő a Tudástár URL-jeit `/tudastar/cikk/`-re állítja).

### Mit állít be a telepítő

| Terület | Beállítás |
|---|---|
| Bolt | cím (Kisvárda), csak Magyarország, HUF, e-mail feladó: `info@vasskisgep.hu` |
| ÁFA | 27%, bruttó árak megadása és megjelenítése |
| Szállítás | Házhozszállítás futárszolgálattal; Személyes átvétel Kisvárdán és Vásárosnaményban (ingyenes) |
| Fizetés | Fizetés átvételkor; Előre utalás (csak ha van bankszámlaszám); Barion (ha telepítve és beállítva) |
| Katalógus | Kategóriafa (Kertészeti / Erdészeti / Építőipari gépek, Alkatrészek, Munkaruházat), márkák logóval (Stihl, Stiga, Cedrus, Bluebird, Rato, Kasei), évszak címkék (Tavasz, Nyár, Ősz, Tél) |
| Oldalak | Rólunk, Szolgáltatások, Ajánlatkérés, Szerviz, Kapcsolat, Kedvencek, Összehasonlítás, Szállítás és fizetés, ÁSZF, Adatkezelési tájékoztató, Elállás és visszaküldés |
| Menü | Főmenü linkjei, a Termékek alatt a főkategóriák |
| Tartalom | fejléc: Kedvencek/Összehasonlítás link; lábléc: hibás adatkezelési link javítva; kezdőlap: gomb-linkek, válogatott karusszelek (kiemelt, szezonális, népszerű), valódi Google értékelések a lorem ipsum helyett; terméklista kártya: kedvenc/összehasonlítás ikonok |

## Teendők élesítés előtt

- [ ] **Bankszámlaszám**: WooCommerce → Beállítások → Fizetés → Előre utalás (addig ki van kapcsolva).
- [ ] **Barion**: bővítmény telepítése, POSKey, Barion e-mail cím; élesítés előtt teszt módban kipróbálni.
- [ ] **Szállítási díj**: a „Házhozszállítás” díja most 1000 Ft + ÁFA (a korábbi beállítás maradt). Nagy gépekre valószínűleg súly vagy termékosztály szerinti díj kell.
- [ ] **Jogi szövegek**: az ÁSZF és az Adatkezelési tájékoztató `[kitöltendő]` részei (cégjegyzékszám, adószám, tárhely, futár, számlázó, könyvelő). A szövegek minták, jogásszal ellenőriztetni kell.
- [ ] **Számlázás**: számlázó bővítmény (Számlázz.hu vagy Billingo) a NAV-bejelentéshez.
- [ ] **E-mail kézbesítés**: SMTP (pl. WP Mail SMTP) a `vasskisgep.hu` domainről, SPF/DKIM beállítással.
- [ ] **Google értékelések**: Megjelenés → Beállítások → Google értékelések: Places API kulcs és Place ID. Addig a kezdőlapon csak a „Véleményeink a Google-on” gomb látszik.
- [ ] **Közösségi linkek**: a láblécben az Instagram és a YouTube ikon még `#`-re mutat.
- [ ] **Termékek**: valódi termékek felvitele vagy importja (kategória, márka, tulajdonságok, képek, súly). Az 5 „TESZT001” termék törölhető. Szezonális termékeknél az évszak címkét kell megadni.
- [ ] **Keresőmotorok**: élesítéskor Beállítások → Olvasás → „Keresőmotorok…” jelölés kivétele.
- [ ] **Cofidis** (áruhitel): a láblécben szerepel a logó, de integráció nincs; a Cofidis saját bővítményét kell kérni.

## Funkciók

- **Termékoldal** (`templates/single_product_content.html`): galéria, márka, kulcsadatok, ár, készlet, kosárba, kedvencek/összehasonlítás, vásárlási információk, fülek (Leírás, Műszaki adatok, Szállítás és fizetés, Kérdése van? űrlap), hasonló termékek.
- **Kategória, márka, címke archívum**: fejléckép, leírás, alkategóriák, szűrő, terméklista.
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
