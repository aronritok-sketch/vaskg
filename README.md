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
   - Ha egy `setup/pages/*.html` fájl változik, a lecserélt változat ujjlenyomatát (szóközök nélküli tartalom md5-je) a `Setup::PAGE_HISTORY`-hoz kell adni, és egy új lépésben a `pages_refresh()`-t újra kell futtatni – így a már telepített, nem szerkesztett oldalak is frissülnek.
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
| Frissítés régebbi telepítésről (19–21.) | **19.** a régi kategóriafa (Kertészeti gépek, Erdészeti gépek, …) helyett a Figma szerinti: a termékek átsorolása, a régi kategóriák és menüpontjaik törlése, hiányzó tulajdonságok létrehozása, a tulajdonság nélküli bemutató termékek újratöltése, régi kategórialinkek cseréje a tartalmakban; a régi kategória-URL-ek 301-gyel az újra mutatnak (`Catalog::LEGACY_CATEGORIES`). **20.** a még lorem ipsumos kezelt tartalmak (pl. kezdőlap) cseréje a kész változatra. **21.** a korábbi telepítői változatukon maradt, nem szerkesztett oldalak frissítése (`Setup::PAGE_HISTORY`). Az előző változat mindig a revíziók között marad. **22.** árformátum a design szerint (249.900 Ft). **23.** a termékoldali tájékoztató pótlása a motoros bemutató gépeken. |
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
- [ ] **Kapcsolódó termékek a régi boltból**: a domain átállítása **előtt** lefutott-e (Termékek → Régi webshop átköltöztetés → Kapcsolódó termékek: „Kész”). Utána a régi bolt már nem olvasható.
- [ ] **Árukereső**: a Partner Portálon a feed címe `https://vasskisgep.hu/arukereso.xml` (a régi bolt feedje helyett); Megbízható Bolt programnál a WebAPI kulcs a rendelésküldéshez.
- [ ] **Sebesség**: a dev szerver gyorsítótár nélkül ~2–3 mp alatt adja ki az oldalt (a régi bolt ~1,2 mp). Élesítés előtt oldal-gyorsítótár (a kedvencek / összehasonlítás / kosár számlálója sütiből jön: ezeket a gyorsítótár ne rögzítse) és mérés mobilon és gépen.
- [ ] **Keresőmotorok**: élesítéskor Beállítások → Olvasás → „Keresőmotorok…” jelölés kivétele.
- [ ] **Cofidis** (áruhitel):
    - *Fizetési mód* (`inc/cofidis.php`, `inc/cofidis-gateway.php`): WooCommerce → Beállítások → Fizetés → **Cofidis áruhitel** → Engedélyezés. Csak akkor jelenik meg a pénztárban, ha a kosár minden terméke „Cofidis áruhitel” jelölésű és a végösszeg a min./max. határon belül van. A rendelés „Függőben” állapotba kerül (a készlet lefoglalva), a vásárló tájékoztatást kap (köszönőoldal + e-mail, opcionálisan a Cofidis hitelkérelmi linkjével). Hitelbírálat után a rendelésnél a *Rendelés műveletek* közül: „Cofidis: hitel jóváhagyva” (→ Feldolgozás alatt) vagy „elutasítva” (→ Törölve, a készlet visszaáll).
    - *Kalkulátor*: Megjelenés → Beállítások → Cofidis áruhitel fülön THM, ügyleti kamat, futamidők, min./max. hitelösszeg, reprezentatív példa (a Cofidis szerződés adatai).
    - *Online hitelkérelem (API)*: a Cofidis **webshop / online áruhitel** kereskedői dokumentációja kell hozzá (a PSD2 számlainformációs API nem erre való). Bekötési pontok: `vasskisgep_cofidis_start_application` szűrő (a rendelésből hitelkérelmet indít, az átirányítási URL-t adja vissza) és `vasskisgep_cofidis_callback` szűrő a `rest_url('vasskisgep/v1/cofidis/callback')` végponthoz (az iu_theme a REST előtagot átnevezi, a pontos cím a fizetési mód beállításainál látszik) (hitelesség ellenőrzése, `['order_id' => …, 'status' => 'approved'|'rejected'|'pending']`). Bekötés nélkül a végpont 501-gyel elutasít.

## Funkciók

- **Termékoldal** (`templates/single_product_content.html`, Figma szerint): galéria kedvezmény-jelvénnyel, promó szöveggel és márkával; cikkszám, készlet + csillagos értékelés, ár doboz (régi/akciós ár, áfa megjegyzés, Cofidis jelvény), tulajdonság-csempék, mennyiség + Kosárba (AJAX), kedvencek/összehasonlítás; „Kapcsolódó termékek” oldalsáv (keresztértékesítés); fülek: Részletek (tájékoztató doboz, leírás, műszaki adatok), Dokumentumok, Vélemények (WooCommerce értékelések, moderálás a Termékek → Értékelések alatt), Kérdése van?; szállítási és fizetési módok a WooCommerce beállításaiból; Cofidis kalkulátor; hasonló és legutóbb megtekintett termékek.
- **Terméklista** (`vasskisgep/product-listing` blokk; Termékek oldal, kategória, márka, címke): felső szűrősáv (Kategória, Gyártó, Ár, Készlet/Akciós) legördülőkkel és „(N) termék megtekintése” előnézettel, rács/lista nézet, rendezés, „Részletes szűrés” tulajdonságok szerint, „Továbbiak mutatása”. A szűrés URL-paraméterekben van (megosztható link).
- **Termékkártya**: kedvezmény, promó szöveg, Cofidis jelvény, márka, 3 fő tulajdonság, kedvencek/összehasonlítás ikonok a képen.
- **Megamenü** (`inc/megamenu.php`): a főmenü Termékek pontja alatt fülek (főkategóriák + Gyártók), 4 szintű kategóriafa; mobilon a menü saját almenüje.
- **Rólunk** oldal a Figma szerint (saját fejléc: Oldal szerkesztése → „Oldal megjelenése” → Saját oldalfejléc).
- **Kereső** (`inc/search.php`): gépelés közben teljes szélességű találati panel a fejléc alatt (termékek képpel/árral, kategóriák, Tudástár); Enter → találati oldal.
- **Akciók** menüpont (27. lépés): a főmenüben a Termékek után, a terméklista akciós szűrővel (`/termekek/?onsale=yes`), „Akciós termékek” címmel; narancs kiemelés (`.vk-menu-sale`).
- **Lebegő vásárlási sáv** (`ProductPage::sticky_buy`, shop.js): a termékoldalon alul megjelenik, ha a vásárlási doboz felfelé kigördült: kép, név, elérhetőség, régi és akciós ár, kedvezmény %, Kosárba (a fő űrlap mennyiségével, AJAX-szal); változatos terméknél „Kiválasztom”, nem vásárolhatónál „Ajánlatot kérek”.
- **Admin rendeléslista** (`inc/orders.php`): „Termékek” oszlop a tételekkel (kép, mennyiség, név, cikkszám), sok tételnél görgethető dobozban; HPOS és régi tárolás.
- **Kezdőlap márkasáv** (28. lépés): a Kasei helyett a Berrin (fő profil: kapálógép, kép: `setup/media/berrin-kapalogep.jpg`, logó: `berrin-logo.png`), a Rato diája áramfejlesztő + kapa (`rato-kapa-aramfejleszto.jpg`). A két kép a régi bolt termékfotóiból összeállított kompozit; ha lesz terepen készült fotó, a médiatárban a fájl cseréjével frissíthető.
- **Ügyfélkör 2** (2026-10): a kezdőlapi márkasáv diái teljes felületükön kattinthatók, sorrend STIHL, STIGA, Cedrus, Rato, Berrin, Bluebird, a Berrin képe az ügyfél kivágott G900 fotójából (29. lépés, `berrin-kapalogep-2.jpg`), a diák közti csíkok megszüntetve; márkaoldalon a márka kategóriái chipekben (`Catalog::brand_categories`), a márkaoldali kategóriaszűrő javítva; termékoldalon a márka logója; a kártyák, a kereső és az összehasonlítás képei nem vágottak (`image_downsize`: `woocommerce_thumbnail` → `woocommerce_single`), a nagyító a fejléc fölött; a leírás táblázatai keretesek, a leírás képei arányosak; második ügyfélszolgálati telefonszám (30. lépés; a 32. lépés váltotta fel).
- **Új tulajdonság-csempék** (31. lépés): Max. teljesítmény (kW), Hasítóerő (t), Max. ágátmérő (mm), Vízáteresztő képesség (l/h), Max. munkanyomás (bar). A régi webshopból átvett termékeknél a régi mezőkből és a leírás táblázataiból tölti ki (`MigrationImporter::spec_fields`), csak hiányzó értéket ad. A csempék ikonjai a régi webshop piktogramjai, ahol volt (`assets/spec-icons/<slug>.svg`, pl. lökettérfogat); a többi a blokkban rajzolt vonalikon. Ezek „contextual” tulajdonságok: a Részletes szűrésben csak ott jelennek meg, ahol a listában van ilyen termék.
- **Ügyfélkör 3** (2026-10): a képnagyító a keretrendszer fejléce (z-index 999999999) fölött; a galéria kis képei vágatlanok (`woocommerce_gallery_thumbnail` → `medium`). Márkaoldalon a kategóriák képes csempékben, gépfajta szinten, mint a régi webshop márkaoldalain (`Catalog::brand_categories`, `BRAND_TILE_GROUPS`: ezek a fa 3. szintjén maradnak, a többi gyűjtőcsoport a 4. szintre bomlik; Alkatrészek és karbantartás ágai külön, Tartozékok egyben; a kép a márka egy terméke a csoportból). Elérhetőségek (32. lépés, `Setup::PHONE_2`): „Központi ügyfélszolgálat” +36 30 289 5977, „Termékinformáció, árak és rendelésfelvétel:” +36 30 304 1524 és +36 30 289 5977 a láblécben és az Ajánlatkérés oldalon; a 30. lépés téves számát eltávolítja.
- **Ügyfélkör 4** (2026-10):
  - **Kereső** (`Search::product_ids`, a fejléc panel és a keresés oldal közös): minden szónak egyeznie kell; számok és rövid szavak csak a névben/cikkszámban, egész szóként („msa 60” nem hozza a 160-at); rokon alakok (`SYNONYMS`: akkus → akkumulátoros stb.) és toldalék nélküli tő; sorrend: pontos cikkszám → a kifejezés egyben a névben → minden szó a névben → leírás; a gépek a tartozékok előtt.
  - **Termékkép márkalogó** doboz nélkül, `mix-blend-mode: multiply` (a fehér hátterű logók is átlátszók a fotón).
  - **Leírásbeli ikonok** (YouTube gomb 25×18, 34×34-es ikon) eredeti méretben: az átvétel megtartja a régi méretet (`MigrationImporter::description_icon_sizes`), a 33. lépés javítja a már átvett leírásokat.
  - **Pénztár** (`inc/checkout.php`, `assets/checkout.js`): irányítószám → település a Magyar Posta jegyzékéből (`assets/iranyitoszamok.json`, 3046 irányítószám; frissítés: a posta.hu „Iranyitoszam-Internet_uj.xlsx” táblából); több településnél választólista. Adószám → cégnév + székhely a NAV Online Számla `queryTaxpayer` hívással; beállítás: WooCommerce → Adószám-lekérdezés (vagy `VK_NAV_LOGIN`, `VK_NAV_PASSWORD`, `VK_NAV_SIGN_KEY`, `VK_NAV_TAX_NUMBER`, `VK_NAV_TEST` konstans); beállítás nélkül a mező kézi. Csak üres címmezőt tölt ki; IP-nként 20 lekérdezés / 10 perc; 1 napos gyorsítótár.
  - **Rendelés szerkesztése**: a „Feldolgozás alatt” rendelés tételei is szerkeszthetők (`Orders::editable`, `wc_order_is_editable`).
- **Ügyfélkör 5** (2026-10):
  - **Szezon** választó a termékszerkesztőben (oldalsáv: Tél, Tavasz, Nyár, Ősz) és a tömeges szerkesztésben; a `tavasz/nyar/osz/tel` címkékkel szinkronban (`Shop::set_product_seasons`).
  - **Márkaoldal**: a kategória-csempe a terméklistához ugrik (`#termekek`); a csempéből választott (a Kategória legördülőben egyébként nem szereplő) kategória bejelölve bekerül a legördülőbe, így az ár- és egyéb szűrés nem veszíti el.
  - **Alapértelmezett rendezés kategóriában**: ár szerint növekvő (`Listing::default_orderby`; kategória oldal, vagy kiválasztott kategória), az ár nélküli termékek a végén; máshol a kirakat-sorrend.
  - **Lábléc**: az üres (#) Instagram és YouTube ikon ki (34. lépés).
  - **Súly szerinti házhozszállítás** (`inc/shipping.php`, `inc/shipping-weight-rate.php`, 35. lépés): a régi webshop ÁSZF-jének díjtáblája (10 kg-ig 1 900 Ft … 500 kg-ig 17 790 Ft, bruttó; 30 000 Ft felett 10 kg-ig ingyenes). Szerkeszthető: WooCommerce → Beállítások → Szállítás → Magyarország → Súly szerinti házhozszállítás (díjtábla soronként „kg | Ft”, ingyenes értékhatár és súlyhatár). Súly: a Tömeg mező, ha üres, a Tömeg tulajdonság. A tábla felett csak személyes átvétel (magyarázattal). A termékoldal „Szállítási módok” doboza a termék díját mutatja, a Szállítás és fizetés oldalon a díjtábla blokk (`vasskisgep/shipping-rates`). A régi egységes díjú mód kikapcsolva, visszakapcsolható.
- **Ügyfélkör 6** (2026-10): a termékoldali csempék (ár alatt) a termék **összes** tulajdonságát mutatják, mint a régi webshop (elöl a szűrhetők, utána pl. Vágáshossz, Kapasor szélesség, Légszállítás, Vízszállítás, Méret; 40 karakternél hosszabb érték csak a Műszaki adatok táblában), a régi webshop piktogramjaival (`assets/spec-icons/`). A Légszállítás mértékegysége m³/h (36. lépés; az átvétel is így veszi át).
- **Ügyfélkör 7** (2026-10): a terméklista kártyáin is a csempék adatai, legfeljebb 6 sor (`Catalog::CARD_ATTRIBUTES`; elöl a szűrhetők, 40 karakternél hosszabb érték csak a termékoldalon). A kártyák egy soron belül egy magasak, az ár + gomb sor alul egy vonalban (akkor is, ha az egyik terméknél kevés vagy semmi adat nincs).
- **Ügyfélkör 8** (2026-10):
  - **Termékleírás**: a régi webshopból jött leírások egy része lezáratlan `<div>`-ekkel végződött, ezért a Dokumentumok és Vélemények fül, valamint a szállítás-fizetés doboz a Részletek fülbe került (nem lehetett rájuk kattintani, nem látszott). A leírás kirajzoláskor lezárva, `vk-description` keretben (`ProductPage::description`, `tidy_description`), a 37. lépés az adatbázisban is rendbe teszi. A régi „kattints” kéz ikon (5607.jpg) ki, helyette a leírás minden linkje végére a stíluslap tesz ikont (képet tartalmazó linkre nem). A régi bolt termék- és kategórialinkjei az új oldalakra mutatnak (37. lépés).
  - **Kapcsolódó termékek** (jobb oldali lista, kosárba gombbal): a régi termékoldalak „Kapcsolódó termékek” sávjából (`MigrationRelated`, `inc/migration-related.php`): a 39. lépés a háttérben elindítja, az átköltöztetés oldalán kézzel is futtatható (`wp vasskisgep migrate related`). A régi boltnak elérhetőnek kell lennie: a domain átállítása előtt. Kézzel beállított keresztértékesítést nem ír felül.
  - **Dokumentumok**: új lapon, a böngészőben nyílnak meg (nincs `download`), a feltöltés dátuma nem látszik.
  - **Gyártók oldal** (`/gyartok/`, `vasskisgep/brand-list` blokk: márkák logóval, termékszámmal): a főmenüben a Tudástár helyén (38. lépés); a Tudástár a láblécben marad.
  - **Árukereső feed**: `/arukereso.xml` (`inc/feed.php`, 40. lépés). `Delivery_Time` 1 = Raktáron, utánrendelhető 5; `Delivery_Cost` a súly szerinti díjtáblából (díjmentesnél „ingyenes”, ismeretlen súlynál üres → a Partner Portál díjtáblája). Élesítéskor az Árukereső Partner Portálon ezt a címet kell megadni.
- **Ügyfélkör 9** (2026-10):
  - Termékoldal (asztali): milliós árnál, kapcsolódó termékek mellett az „(Az ár az áfát tartalmazza)” az ár alá kerül (nem lóg át az oldalsávba).
  - Mobil fejléc: a telefonszám kiírva (+36 30 289 5977), egy érintéssel hívható.
  - Mobil kezdőlap: a márka-hero csempéin (Stiga, Cedrus, Rato, Berrin, Bluebird) látszanak a logók; az asztali középre igazítás (57vw-s eltolás) a csempén kívülre tolta őket.
  - Márkaoldal mobilon: a kategória-csempék két oszlopban, lefelé görgetve, nagyobb felirattal (korábban két sorban, oldalra görgetve).
  - Összehasonlítás mobilon: a sor neve a sor fölött, alatta a termékek fél képernyő szélesen (kettő látszik, a többi oldalra görgetve); a táblázat `--vk-compare-n` változóban kapja a termékek számát.
  - Megamenü: a tárolt panel a téma frissítésekor újraépül (`MegaMenu::panel`, a fájl módosítási ideje a gyorsítótárban).
- **Ügyfélkör 10** (2026-10):
  - Márkaoldal: a kategória-csempe után típusválasztó (`#tipusok`, `.vk-brand-subcats`): a kategória alkategóriái ebben a márkában (pl. STIHL Fűnyírók → Összes / Akkumulátoros / Benzinmotoros / Elektromos), nagy gombokkal, mobilon két oszlopban (`Catalog::brand_categories` a csempékhez az alkategóriák darabszámát is tárolja).
  - Kezdőlapi márka-hero mobilon: lapozó (`pager-hero/script.js`): csak a kiválasztott márka látszik, két oldalt mindig látható nyíllal, ujjal húzva is lapozható; alatta a hat márka fix sorrendben (3×2 csempe). Az érintés nem vált ki rámutatást (a korábbi hibánál a koppintás után átrendeződő csempék miatt másik márka nyílt meg). Asztali nézet változatlan.
- **Kirakat-sorrend** (`Catalog::showcase_clauses`, `vk_showcase` lekérdezés-paraméter): a terméklista alapértelmezett rendezése és a kezdőlapi Kiemelt karusszel: Kiemelt → gép (a Tartozékok és az Alkatrészek és karbantartás ág hátrébb) → készleten → legújabb. A Népszerű karusszel előtte az eladások szerint rendez (a `wc_get_products()` a „popularity” rendezést nem ismeri, ezért korábban dátum szerint rendezett).
- **Kedvencek tárolása**: vendégnél sütiben (1 év, a böngésző törölheti); belépett vásárlónál az adatbázisban is, és ha a süti elveszett, onnan áll vissza.
- **Képek a setup tartalmakban**: `{{media:fajl.jpg}}`, `{{media-thumb:…}}`, `{{media-id:…}}` helyőrzők a `setup/media/` fájlaira; a telepítő tölti fel őket a médiatárba.
- **Keresés**: termékek (név, leírás, cikkszám) és Tudástár cikkek.
- **Kedvencek és összehasonlítás**: sütiben tárolva, bejelentkezett vásárlónál a fiókba is mentve; számláló a fejlécben; legfeljebb 4 termék hasonlítható össze.
- **Űrlapok**: visszahívás (lábléc), ajánlatkérés, szervizbejelentés, kapcsolat, termékkérdés. Az e-mail minden kitöltött mezőt tartalmaz, a válaszcím a kitöltő e-mail címe. Címzett: `Forms::RECIPIENT` (`inc/forms.php`).
- **Pénztár**: cégnév és adószám (a rendelésben és az e-mailekben is megjelenik), ÁSZF és adatkezelés elfogadása.

## Régi webshop átköltöztetés (Joomla / VirtueMart → WooCommerce)

Ideiglenes eszköz: **Termékek → Régi webshop átköltöztetés** (`inc/migration*.php`, `assets/migration.js`). A régi bolt (https://vasskisgep.hu, Joomla + VirtueMart) nyilvános oldalait olvassa – a Joomla adminhoz, adatbázishoz nem kell hozzáférés, a régi oldalon semmi nem változik. Kb. 1700 termék, 340 régi kategória.

1. **Beolvasás** – a kategóriaoldalakat bejárva minden termékoldalt feldolgoz: név, cikkszám, ár és akciós ár, készletállapot, márka, régi kategóriaútvonal, összes kép, leírás, műszaki adatok, csatolt PDF-ek (kezelési útmutató, robbantott ábra). Újrafuttatható (a régi azonosító alapján frissít). Ha a szerver nem éri el a régi boltot: egy másik telepítésben beolvasott adat „Letöltés JSON-ként”, majd itt „JSON fájl betöltése”.
2. **Besorolás** – a régi bolt márka szerinti volt (STIHL › Motorfűrészek › …), az új funkció szerinti. `setup/migration/category-map.json`: régi útvonal-előtag → új levélkategória (a leghosszabb egyező előtag dönt), a vegyes régi kategóriák (gépek és tartozékok együtt) „ellenőrizendők”. Ezeket **Claude** sorolja be (Anthropic Messages API, eszközhívás a kategórianevek felsorolásával, gyorsítótárazott rendszerüzenettel, 10-es adagok): a küszöb (alapból 80%) feletti megbízhatóság jóváhagyva, alatta kézi ellenőrzés. API-kulcs: `wp-config.php` → `define('VASSKISGEP_ANTHROPIC_API_KEY', '…');` vagy a beállításoknál (lezáráskor törlődik). Modell: `claude-sonnet-5` (módosítható). Kulcs nélkül az ellenőrizendők kézi áttekintésre kerülnek. A téma előre elkészített Claude-besorolást is tartalmaz (`setup/migration/claude-categories.json`, régi termékazonosító → [új levélkategória, megbízhatóság, megjegyzés]): minden terméknél (nem csak a vegyes kategóriákban) felülírja a megfeleltetést, így kulcs nélkül is 1678 termék jóváhagyva, 41 kézi ellenőrzésre (ár nélküliek, méretválasztósak, bizonytalanok). Ide kerülnek a név alapján máshová tartozó termékek is (pl. a régi „Metszőollók” alatti fűrészek → Kéziszerszámok és erdészeti eszközök). Becsült költség a ~340 termékre: ~1–2 USD.
3. **Áttekintés** – táblázat (szűrés állapotra, keresés): a kategória soronként vagy csoportosan módosítható (azonnal jóváhagy), jóváhagyás, kihagyás, „Claude-nak újra”. A régi boltban méretválasztós termékek (kesztyű, ruha) megjegyzéssel jelölve (egyszerű termékként jönnek át), de ettől még nem kerülnek ellenőrzésre; ellenőrizendő csak az ár nélküli vagy a küszöb alatti megbízhatóságú termék. A 26. lépés a régi kategóriacímek átirányítását számolja újra: a vegyes régi kategória azoknak az új kategóriáknak a közös szülőjére mutat, amelyek együtt a termékei legalább 70%-át adják (pl. a régi STIHL „Motorfűrészek és magassági ágnyesők” → Láncfűrészek és ágnyesők, nem a főkategória). A 25. telepítő lépés a téma frissítésekor a már beolvasott, még nem importált rekordokat e szabályok és a termékszintű besorolás szerint újrasorolja (a kézzel beállított kategóriák maradnak).
4. **Szűrőadatok** – a régi műszaki adatmezők (Lökettérfogat, Teljesítmény, Tömeg, Vágásszélesség, Garancia) a szűrők tulajdonságaivá válnak; ahol a régi bolt nem adta meg őket külön mezőben, az importáló a leírásból veszi ki (csak mértékegységgel együtt: „Súly 18 kg”, „Munkaszélesség: 90 cm”, „Teljesítmény 1 kW” → LE; a teljesítményfelvételt nem). Kb. 265 érték pótlódik így.
5. **Import** – WooCommerce egyszerű termékek (a régi azonosító `_vk_old_id` alapján frissít): ár (bruttó; ha a régi alapár magasabb, akciós ár), készlet (Raktáron → készleten; „Visszaigazolástól 1-5 munkanap” → utánrendelhető), cikkszám (ha egyedi), kategória, márka (hiányzó márka létrehozása, logóval ha van `setup/media/marka-*.png`), műszaki adatok (a szűrhető tulajdonságok: lökettérfogat, teljesítmény, tömeg, vágásszélesség, garancia; a többi helyi tulajdonságként), leírás (tisztítva, a beágyazott képek a médiatárba), rövid leírás (a leírás eleje), képek, dokumentumok (Dokumentumok fül). Képek: minden / csak főkép / nincs (gyors próba). Állapot: közzétéve vagy piszkozat.
**Készlet (CSV)** – a régi bolt nyilvános oldalain csak a készletállapot látszik (raktáron / visszaigazolástól 1-5 munkanap), ezt az import átveszi. Darabszámhoz: az átköltöztető oldal „Készlet (CSV)” kártyáján CSV feltöltése (`inc/migration-stock.php`; WP-CLI: `wp vasskisgep migrate stock fajl.csv`). Első sor oszlopnevekkel; elég a `cikkszám;készlet`, de a régi azonosítóval pontosabb (49 régi terméknek nincs cikkszáma, 11 cikkszám több terméken is szerepel). phpMyAdmin: `SELECT virtuemart_product_id, product_sku, product_in_stock, product_ordered FROM xxx_virtuemart_products;` (készlet = in_stock − ordered). Elválasztó ; , vagy tab, UTF-8 vagy Windows-1250. Az importált termékeken azonnal, a többin az importkor állít készletkezelést; elfogyott termék rendelhető marad (backorder: „1-5 munkanap visszaigazolástól”). Újratölthető (élesítés napján friss készlettel).
6. **Átirányítás** – a régi termékcímek (`/termekek/stihl/…/stihl-ms-172-…`) 301-gyel az új termékre, a régi kategóriacímek az ott lévő termékek leggyakoribb új kategóriájára, a régi márka-főkategóriák (`/termekek/stihl`) a márka oldalára. A lezárás után is működik (`vk_mig_redirects` opció).
7. **Lezárás** – az átmeneti rekordok és az API-kulcs törlése.

WP-CLI (nagy importhoz, időkorlát nélkül): `wp vasskisgep migrate crawl [--reset]`, `wp vasskisgep migrate map`, `wp vasskisgep migrate ai`, `wp vasskisgep migrate import [--refresh]` (a `--refresh` a már importáltakat is frissíti a legutóbbi beolvasásból, a kézi módosításaikat felülírva), `wp vasskisgep migrate stock fajl.csv`, `wp vasskisgep migrate status`.

A kategóriafa a régi kínálathoz bővült (24. telepítő lépés): **Tartozékok** főkategória (gépcsaládonként), elektromos fűnyírók, helyben forduló és frontkaszás fűnyíró traktorok, szórókocsik, betonsimítók, műgyanta vágótárcsák, szerviz csomagok, kannák, ápolószerek, akkus kiegészítők, hallás- és szemvédők, munkaruházat, ajándéktárgyak.

Élesítés sorrendje: téma feltöltése → admin belépés (24–26. lépés) → bemutató (DEMO-) termékek törlése → Beolvasás → Besorolás (+ Claude) → Áttekintés → Import (képekkel ez órákig tarthat; a lap maradjon nyitva, vagy WP-CLI) → ellenőrzés → a domain átállítása után a régi címek átirányítódnak → Lezárás.

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
