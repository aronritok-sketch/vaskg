# Termékimport (WooCommerce CSV)

Termékek → Importálás → `termekimport-minta.csv` szerkezetű fájl (UTF-8, vessző elválasztó).

- **Categories**: a teljes útvonal `>` jellel (`Kerti és erdészeti gépek > Fűnyírás és gyepápolás > Fűnyírók > Benzinmotoros fűnyírók`). A kategóriák nevei egyezzenek a `setup/catalog/categories.json` fájl neveivel, különben új kategória jön létre.
- **Tags**: évszak címkék (`tavasz`, `nyar`, `osz`, `tel`) – a kezdőlapi szezonális karusszel ezekből válogat.
- **Brands**: a márka neve (Stihl, Stiga, Cedrus, Bluebird, Rato, Kasei). Ha az importáló nem ismeri fel az oszlopot, a leképezésnél a „Márkák” mezőt kell választani.
- **Attribute 1–5**: a szűrhető tulajdonságok **globális** attribútumként (`… global = 1`), a név pontosan: Lökettérfogat, Teljesítmény, Tömeg, Vágásszélesség, Garancia. Az érték csak szám (tizedesvesszővel: `2,4`), a mértékegységet a téma teszi hozzá.
- **Cross-sells**: a „Kapcsolódó termékek” oldalsávban megjelenő termékek cikkszámai vesszővel.
- **Meta: _vk_promo**: promóciós szöveg a kártyán és a termékképen (több sor `\n`-nel).
- **Meta: _vk_cofidis**: `yes`, ha Cofidis áruhitellel is megvásárolható (jelvény + kalkulátor).
- **Meta: _vk_notice**: a Részletek fül tetején megjelenő tájékoztató doboz.
- Dokumentumok (PDF) importtal nem adhatók meg: a termék szerkesztésénél a „Dokumentumok kiválasztása” gombbal.
- Import után a szűrők és a megamenü automatikusan frissülnek.
