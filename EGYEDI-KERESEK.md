# Vass Kisgép – egyedi kérések (plusz fejlesztések)

> Élő lista (Áron, 2026-11-20): minden új egyedi kérés ide kerül, amikor elkészül vagy beérkezik. Utolsó frissítés: 2026-11-26.

Csak azok a tételek, amik **nem voltak a régi Joomla-boltban**, **nem javítások**, hanem az ügyfél (Dávid, Dani) **külön kérései**. Forrás: a vasskisgep téma változásnaplója (1.0.8–1.0.74) és az ügyfélkörök.

A **Mikor** oszlopban a dátum mellett a téma verziója áll (pl. 1.0.36), vagy az, hogy melyik ügyfélkörben készült. Ahol egy kérés több kiadásban készült el, az első és az utolsó verzió szerepel.

A **Méret** oszlop: **K** = kicsi (néhány óra), **K-K** = közepes (fél–egy nap), **N** = nagy (több nap, külső rendszerrel). Ezek az én becsléseim, a munkaórákat nem mértem.

## A) Admin – egyedi kezelőfelület

| # | Fejlesztés | Mikor | Méret |
|---|---|---|---|
| 1 | Admin rendeléslista: „Termékek” oszlop (kép, db, név, cikkszám), „Státusz” oszlop választóval, több rendelés státusza egy mentéssel, „Értesítés” pipa (kap-e levelet a vevő) | 11-06, 1.0.9–1.0.10 | K-K |
| 2 | Terméklista: „Szezon” oszlop (Tél / Tavasz / Nyár / Ősz kapcsolók), csoportműveletek, szűrés szezon szerint; szezonválasztó a termékszerkesztőben | 10. hó (ügyfélkör 5) – 1.0.16 | K-K |
| 3 | Terméklista: „Közzétéve” pipa (egy kattintással le- és felrakható) + csoportművelet | 11-09, 1.0.38 | K |
| 4 | „Ingyenes szállítás” termékre és kategóriára, pipa a terméklistában, csoportművelet, feltűnő promóciós címke | 11-09, 1.0.41–1.0.42 | K-K |
| 5 | „Készlet megjegyzés”, „Várható beérkezés” dátum (magától eltűnik), „Beérkező mennyiség” | 11-09, 1.0.36–1.0.37 | K-K |
| 6 | Termékkategóriák: szűrés (gyártó, főág, ingyenes szállítás, üres), „Sorrend” oszlop átírható számmal; a szűrt terméklista megmarad, „Vissza a szűrt listához” gomb | 11-09/10, 1.0.43, 1.0.46 | K-K |
| 7 | Gyártói oldal: csempék sorrendje gyártónként (kategória / termékszám / egyedi), csempeképek gyártónként | 11-10, 1.0.48; 11-19, 1.0.71 | K-K |
| 8 | Gyártók sorrendje állítható (menü Gyártók füle, Gyártók oldal) | 11-19, 1.0.71 | K |
| 9 | Termékszerkesztő: kategória-kereső, dokumentumok (fájlnévvel, több egyszerre), táblázat a leírás-szerkesztőben | 11-12, 1.0.49 | K-K |
| 10 | Gép → tartozék kapcsolat (kategóriánként, több is), tartozék-csempék a gép-kategóriáknál és a gyártói oldalon | 11-09/12, 1.0.32, 1.0.49 | K-K |
| 11 | Rendelés szerkesztése: „Feldolgozás alatt” is szerkeszthető, bruttó egységár és összeg oszlop, nagyobb kép | 10. hó (ügyfélkör 4); 11-09, 1.0.28 | K |
| 12 | Rendelésnél telefonszám-másolás (+36 nélkül, a futároknak) és e-mail-másolás | 11-10/12, 1.0.48–1.0.49 | K |
| 13 | Rendelés-megjegyzések a „Szállítás” oszlop alatt (vevő + munkatársak), magától frissül | 11-17, 1.0.68 | K |
| 14 | Ár-import Excelből (cikkszám szerint, akciós ár dátummal, előnézet) + „Saját ár” zárolás (termékenként, tömegesen) | 11-12, 1.0.52; 11-15, 1.0.53 | K-K |
| 15 | Analitika › Gyártók: forgalom gyártónként, termékenként lebontva | 11-12, 1.0.52 | K-K |
| 16 | Munkatársi fiók a webshopban vendégként (admin-belépés nem viszi át), a webshop saját belépőjével viszont belépve marad | 11-09, 1.0.35; 11-15, 1.0.53 | K-K |
| 17 | Kiemelt akció-kategória (pl. Orange Week): narancs gomb a Termékek menüben és a Gyártók oldalon, lejárati dátummal | 11-19, 1.0.71 | K-K |
| 18 | „Mehet csomagautomatába” jelölés termékre / kategóriára, GLS-térkép szűrése (az ügyfélnek még nem jeleztük) | 11-19, 1.0.70 | K-K |

## B) Rendelésfolyamat, státuszok, levelek

| # | Fejlesztés | Mikor | Méret |
|---|---|---|---|
| 19 | Új rendelésstátuszok: „Új rendelés”, „Postázva”, „Részben teljesítve” (tételenkénti „Még nincs teljesítve” jelöléssel), „Beszállítótól rendelve”; csoportműveletek | 11-06–11-12, 1.0.14–1.0.49 | K-K |
| 20 | Csomagkövetés doboz (futár + csomagszám, követési link a levelekben és a fiókban) + vásárlói levelek: Postázva, Részben teljesítve | 11-06, 1.0.14 | K-K |
| 21 | Elégedettségi kérdőív levél teljesítéskor (rendelésenként pipa) | 11-06, 1.0.14 | K |
| 22 | Kosáremlékeztető levél 20:00-kor (vendég e-mail a pénztárból, regisztráltaknak is, visszatöltő link, leiratkozás, próbalevél) | 11-06–11-10, 1.0.15–1.0.45 | K-K |
| 23 | Utalásra / utánvétre váró rendelést a WooCommerce ne mondja vissza magától | 11-15, 1.0.53 | K |
| 24 | Levelek arculata (logó, barátságosabb szöveg, kattintható termékek, feladó „Vass Kisgép”) | 11-06, 1.0.13 | K |

## C) Új integrációk (külső rendszerek)

| # | Fejlesztés | Mikor | Méret |
|---|---|---|---|
| 25 | GLS (MyGLS) összekötés: címke a rendelésből és tömegesen, utánvét (a Számlázz.hu számlaszámával), dobozszám, címke újrakészítése; óránként állapot: Postázva → Teljesítve | 11-06–11-15, 1.0.18–1.0.53 | N |
| 26 | GLS CsomagPont / Csomagautomata szállítás a GLS térképével a pénztárban, pont a rendelésben / levélben, címke a pontra | 11-16, 1.0.60 | N |
| 27 | Cofidis expressz online áruhitel (E-direkt): a bank kalkulátora, online hiteligénylés, tanúsítvány, visszahívások, státusz, tájékoztató oldal, tömeges jelölés (élesítés a bankra vár) | 11-15, 1.0.55–1.0.56 | N |
| 28 | NAV adószám-lekérdezés a pénztárban (cégnév + székhely) | 10. hó (ügyfélkör 4) | K-K |
| 29 | Irányítószám → település a postai jegyzékből | 10. hó (ügyfélkör 4) | K |
| 30 | Sütikezelő + Google Consent Mode v2 + Meta képpont (a Facebook-bővítménnyel összehangolva) + teljes Barion Pixel (vásárlási események) | 11-16, 1.0.66–1.0.67 | K-K |
| 31 | Google Merchant termékfeed (`/google.xml`) | 11-16, 1.0.60 | K |
| 32 | Google értékelések csúszka (API-kulcs kell hozzá) | 11-06, 1.0.13 | K |
| 33 | „Szállítás és fizetés” oldal az ÁSZF-ből (VirtualJog), óránként frissül | 11-06, 1.0.22 | K |
| 34 | Barion-összehangolás: Számlázz.hu fizetési mód és teljesítés, sikeres / sikertelen jelölés a rendelésnél, a levélben és a köszönőoldalon, Apple Pay igazoló fájl | 11-16/17, 1.0.57–1.0.68 | K-K |

## D) Webshop – egyedi kérések a felületen

| # | Fejlesztés | Mikor | Méret |
|---|---|---|---|
| 35 | Pénztár: nettó / ÁFA / bruttó sorok az „Adótartalom” helyett | 10. hó (ügyfélkör 15) | K |
| 36 | Pénztár: „Cégként vásárolok” (adószám, cégnév kötelező), telefonszám országhívóval („06” → „+36”) | 11-06, 1.0.13, 1.0.17 | K |
| 37 | Kosár oldalpanel: mennyiség (−/+, beírás), darabár, tétel-összeg | 10. hó (ügyfélkör 14) | K |
| 38 | Kosár oldal új elrendezése (ragadós összesítő, ajánlatok alatta) | 11-06, 1.0.25 | K |
| 39 | „Új termék” jelvény 30 napig, garancia ár szerint (2 / 3 év) | 11-06, 1.0.13 | K |
| 40 | Mobilon a „Termékek” a Gyártók oldalra visz | 11-06, 1.0.13 | K |
| 41 | Cedrus gyártói oldal: „Lombfúvók és lombszívók” csempe (kézi és háti / kerekes), csak a gyártói oldalon | 11-19, 1.0.71–1.0.72 | K |
| 42 | Akció-gomb (Orange Week) a fejlécben: gépen a Kapcsolat és a kereső között, telefonon a kereső alatt | 11-20, 1.0.73–1.0.74 | K |
| 43 | Adószám a céges vevők leveleiben, a köszönőoldalon és a fiókban | 11-26, 1.0.77 | K |
| 44 | Ár-import: súly és dobozméret is cikkszám szerint (kg / g, cm / mm) | 11-26, 1.0.77 | K |
| 45 | Cofidis: konstrukció termékenként (5 konstrukció), vegyes kosár szabálya (19,9% elsőbbség, különben a legrövidebb ingyenes), kizárt tartozékok, tájékoztatás kosárba tételkor / kosárban / pénztárban, hirdetmények | 11-26, 1.0.78 | K-K |

## E) Kért, de még nem készült el (ajánlat / döntés kell)

| # | Kérés | Ki kérte | Megjegyzés |
|---|---|---|---|
| 46 | Gyorsfizetés: Apple Pay / Google Pay gomb a termékoldalon / kosárban | Dávid, 11-17 | Istvánhoz került ajánlatra; nagy (N) |
| 47 | B2B kedvezmény kategóriánként, partnerenként egyedi | Dávid, 11-19 | a B2BKing ingyenes változata nem tudja; saját fejlesztés (N) |
| 48 | Cedrus API: termékképek átvétele cikkszám szerint | Dávid, 11-20 | API-leírás kell; K-K–N az API-tól függően |
| 49 | Stiga API | Dávid, 11-19 | nem tudom, mit ígértünk |
| 50 | Cedrus termékszövegek (angol) MI-fordítása magyarra, feltöltés a webshopba | Dávid, 11-20 (csak kérdés) | most nem kell (Dávid, 11-23: a gépek szövegét maga írja); később, az alkatrészeknél jöhet (K-K) |
| 51 | Cofidis: ajánló levél a sikeres hiteligénylés után (a géphez való tartozékok) | Dávid, 11-25 | még nem készült el (K-K) |

## Nem került a listára

- **A régi Joomla-bolt átvétele** (az eredeti munka része):
  - kategóriabontások (hajtás szerint, Cedrus motorok, profi traktorok);
  - súlyok, régi szállítási szabályok, nagyker árak (B2BKing);
  - kapcsolódó termékek, méretváltozatok;
  - Árukereső-feed és -jelvény, régi címek átirányítása, analitika-import.
- **Javítások**:
  - kiürülő kosár, munkamenet- és REST-hibák, a mobilapp belépése;
  - memória- és sebességgondok, Barion-hibakeresés, pénztári hibaüzenetek;
  - megjelenési hibák (iPhone, csempék, képek).
- **Az eredeti terv része** (Figma, megamenü, kereső, szűrők, termékoldal, lebegő vásárlási sáv), és az élesítéskor szükséges beállítások.
