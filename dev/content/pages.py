"""
A setup/pages/*.html oldal-tartalmak forrása.

Futtatás: python3 dev/content/pages.py  → vázlatok a setup/pages/ mappába,
utána dev/canon.sh minden fájlra (kanonikus markup a blokkszerkesztőből).
"""
import os
import sys

sys.path.insert(0, os.path.dirname(__file__))
from blocks import *  # noqa

OUT = os.path.join(os.path.dirname(__file__), '../../wp-content/themes/vasskisgep/setup/pages/')

STORE_KV = ('Kisvárdai üzlet', '4600 Kisvárda, Városmajor út 20.', '+36 45 404 379', 'tel:+3645404379',
            'https://www.google.com/maps/search/?api=1&query=Vass+Kisg%C3%A9p+Kisv%C3%A1rda+V%C3%A1rosmajor+%C3%BAt+20')
STORE_VN = ('Vásárosnaményi üzlet', '4800 Vásárosnamény, Jókai M. út 51.', '+36 45 472 379', 'tel:+3645472379',
            'https://www.google.com/maps/search/?api=1&query=Vass+Kisg%C3%A9p+V%C3%A1s%C3%A1rosnam%C3%A9ny+J%C3%B3kai+M.+%C3%BAt+51')

BRANDS = [
    (75, '2026/09/stihl-logo.png', 'stihl'),
    (78, '2026/09/stiga-logo.png', 'stiga'),
    (85, '2026/09/cedrus-logo.png', 'cedrus'),
    (91, '2026/09/bluebird-logo.png', 'bluebird'),
    (94, '2026/09/rato-logo.png', 'rato'),
    (98, '2026/09/kasei-logo.png', 'kasei'),
]


def cta(title, text, primary, secondary=None):
    btns = [button(primary[0], primary[1], 'highlight')]
    if secondary:
        btns.append(button(secondary[0], secondary[1], 'outline'))
    return section(
        row(col(
            h(title),
            p(text),
            buttons(*btns, marginTop='1.5rem'),
        )),
        bgColor='dark', lightText=True, paddingTop='4.5rem', paddingBottom='4.5rem', className='vk-cta'
    )


def contact_aside():
    return group(
        h('Közvetlen elérhetőségek', 4),
        p('<strong>Központi ügyfélszolgálat</strong><br><a href="tel:+36302895977">+36 30 289 5977</a><br><a href="mailto:info@vasskisgep.hu">info@vasskisgep.hu</a>'),
        p('<strong>%s</strong><br>%s<br><a href="%s">%s</a>' % (STORE_KV[0], STORE_KV[1], STORE_KV[3], STORE_KV[2])),
        p('<strong>%s</strong><br>%s<br><a href="%s">%s</a>' % (STORE_VN[0], STORE_VN[1], STORE_VN[3], STORE_VN[2])),
        direction='column', bgColor='light', paddingTop='2.1rem', paddingBottom='2.1rem', paddingLeft='2.1rem', paddingRight='2.1rem', className='vk-contact-aside'
    )


pages = {}

# ---------------------------------------------------------------- Rólunk (design: Rólunk)
OPENING_HOURS = 'H–P: 7:30–16:30, Szo: 7:30–12:00'


def timeline_item(year, title, text):
    return group(p(year, className='vk-year'), h(title, 4), p(text), direction='column', className='vk-timeline-item')


def store_info(title, rows):
    return group(h(title, 4), *[p('<strong>%s</strong><br>%s' % r) for r in rows], direction='column', className='vk-store-info')


pages['rolunk'] = '\n\n'.join([
    section(row(
        col(
            h('Kisgép szaküzlet és szerviz, felkészült szakemberekkel'),
            p('A Vass Kisgépnél kertészeti, erdészeti és mezőgazdasági kisgépeket, kiegészítőket, valamint alkatrészeket forgalmazunk. Kisvárdai és vásárosnaményi üzleteinkben személyesen is megtekinthetők a gépek, webshopunkban pedig országosan elérhető a kínálat.'),
            width='2-3'),
        columns='2-3|1-3'), className='vk-about-intro'),
    section(row(
        col(
            h('Számunkra a szakkereskedés…'),
            p('A műszaki adatlap fontos, de önmagában ritkán válaszolja meg, hogy egy gép megfelelő lesz-e az adott mindennapi munkához. A teljesítmény mellett számít a munkaszélesség, a gép tömege, a kezelhetőség, az üzemidő, a tartozékok elérhetősége és a későbbi alkatrészellátás is.'),
            p('Hogy segíteni tudjuk vevőinket, a választás során többek között ezeket vizsgáljuk:'),
            ul('A megmunkálandó terület mérete és adottságai',
               'Az alkalmi, rendszeres vagy intenzív használat',
               'A szükséges teljesítmény és munkakapacitás',
               'A benzines, elektromos vagy akkumulátoros hajtás előnyei',
               'A gép kezelhetősége, szállíthatósága és tárolása',
               'Az alkatrészek, kopóelemek és tartozékok hozzáférhetősége',
               'A várható karbantartási és szervizigény', className='vk-checklist'),
            width='1-2'),
        col(image(156, '2026/09/kerti-kisgep-szakkereskedes.jpg', crop=True, width='100%', height='110%', className='vk-about-photo'), width='1-2'),
        columns='1-2|1-2', vertical='center')),
    section(row(
        col(
            h('Márkák, amelyekhez műszaki háttér is tartozik'),
            p('A márkajelzés önmagában kevés. Egy gépnél ugyanilyen lényeges a konstrukció, a javíthatóság, az alkatrészellátás és az, hogy a gyártó termékei között található-e megfelelő modell az adott felhasználási területre.'),
            p('Kínálatunkban többek között STIHL, STIGA, Cedrus, Bluebird, Rato és Kasei gépek szerepelnek. A választék a kisebb ház körüli feladatoktól a rendszeres kertészeti, erdészeti és mezőgazdasági munkákig több felhasználási szintet lefed.'),
            width='1-2'),
        col(group(*[image(i, f, linkUrl=HOME + '/gyarto/' + slug + '/', width='8.4rem', align='center') for i, f, slug in BRANDS],
                  className='vk-brand-grid'), width='1-2'),
        columns='1-2|1-2', vertical='center')),
    section(
        row(col(h('Összeszerelve és beüzemelve vehető át'))),
        row(
            col(card(155, '2026/09/kerti-gep-szereles.jpg', 'Összeszerelés', 'A személyesen átvett gépeket díjmentesen összeszereljük és beüzemeljük. Így az átvételkor nem dobozban lévő alkatrészeket kell hazavinni, és az első indítás sem otthoni próbálkozással kezdődik.'), width='1-3'),
            col(card(132, '2026/09/funyirotraktorok-kulonbozo-szinben.jpg', 'Beüzemelés', 'A beüzemelés során a gép használatra kész állapotba kerül. Ez csökkenti a hibás összeszerelésből, helytelen beállításból vagy bizonytalan első indításból eredő problémák esélyét. Az átvételkor a kezeléshez kapcsolódó lényeges tudnivalók is tisztázhatók.'), width='1-3'),
            columns='1-3|1-3', marginTop='2.1rem', className='vk-about-cards'),
        bgColor='light', className='vk-about-assembly'),
    section(row(
        col(image(169, '2026/09/kerti-kisgep-szerviz.jpg', crop=True, width='100%', height='100%', className='vk-about-photo'), width='1-2'),
        col(
            h('Kisgép szerviz a garancia lejárta után is'),
            p('Egy meghibásodott gép sokszor akkor áll le, amikor a legnagyobb szükség lenne rá. Tavasszal gyorsan nő a fű, az erdészeti munkát nem lehet korlátlan ideig halasztani, egy vállalkozásnál pedig minden kieső munkanap költséget jelent.'),
            group(p('30+', className='vk-stat-number'),
                  group(h('év tapasztalat', 4), p('A hibák feltárásában és a precíz szerelésben.'), direction='column'),
                  vertical='center', className='vk-stat'),
            buttons(button('Szervizbejelentés', HOME + '/szerviz/', 'highlight'), marginTop='1.5rem'),
            width='1-2'),
        columns='1-2|1-2', vertical='center')),
    section(
        row(col(h('A műhely tapasztalata a gépválasztásnál is hasznos'))),
        row(
            col(card(154, '2026/09/kerti-kisgepek-karbantartasa.jpg', 'Karbantartási tapasztalatok', 'A szervizben pontosan látható, mely alkatrészek kopnak intenzív használat mellett, milyen hibákat okozhat a nem megfelelő üzemeltetés, és mely konstrukciók javíthatók gazdaságosan.'), width='1-3'),
            col(card(210, '2026/08/stihl-termekek.jpg', 'Alkatrész hozzáférhetőség', 'Tudjuk, hogy a kopóelemek és tartozékok elérhetősége hosszú távon meghatározó a gép életciklusában.'), width='1-3'),
            col(card(76, '2026/09/stihl-tools-in-forest.jpg', 'Modell ajánlások', 'Ennek köszönhetően a gépválasztásnál előkerülhetnek olyan gyakorlati szempontok is, amelyek a termékoldalakon ritkán kapnak hangsúlyt.'), width='1-3'),
            columns='1-3|1-3|1-3', marginTop='2.1rem', className='vk-about-cards'),
        className='vk-about-workshop'),
    section(
        row(col(h('Több mint harminc év szakmai tapasztalat'))),
        row(col(group(
            timeline_item('2011', 'Első üzlet megnyitása', 'A Vass Kisgép első üzlete 2011-ben nyílt meg Kisvárdán, majd Vásárosnaményben is elérhetővé vált kínálatunk és szolgáltatásunk.'),
            timeline_item('2015', 'Szerviz bővítés', 'A csapat szakmai tapasztalata ennél jóval korábbra nyúlik vissza: munkatársaink több mint három évtizede foglalkoznak kisgépekkel, műszaki tanácsadással és javítással.'),
            timeline_item('2020', 'Webshop indítás', 'Az eltelt évek alatt sokat változtak a gépek. A hagyományos benzines modellek mellett megjelentek a nagy teljesítményű akkumulátoros rendszerek, fejlődött a motorvezérlés, bővült a tartozékkínálat.'),
            className='vk-timeline')), marginTop='2.1rem')),
    section(
        row(col(h('Személyesen és online is elérhetőek vagyunk'))),
        row(col(group(
            h('Üzleteink', 3),
            group(
                store_info(STORE_KV[0], [('Telefon', '<a href="%s">%s</a>' % (STORE_KV[3], STORE_KV[2])), ('Cím', '<a href="%s" target="_blank" rel="noopener">%s</a>' % (STORE_KV[4], STORE_KV[1]))]),
                store_info(STORE_VN[0], [('Telefon', '<a href="%s">%s</a>' % (STORE_VN[3], STORE_VN[2])), ('Cím', '<a href="%s" target="_blank" rel="noopener">%s</a>' % (STORE_VN[4], STORE_VN[1]))]),
                store_info('Nyitvatartás', [('Mindkét üzlet', OPENING_HOURS)]),
                className='vk-store-grid'),
            direction='column', className='vk-reach-card vk-reach-personal')), marginTop='2.1rem'),
        row(col(group(
            h('Webshop', 3),
            group(store_info('Web', [('Online rendelés', 'A webshop azok számára jelent kényelmes megoldást, akik már ismerik a keresett típust, vagy távolabbról rendelnének. <a href="' + HOME + '/termekek/">Termékek megtekintése ›</a>')]), className='vk-store-grid'),
            direction='column', className='vk-reach-card vk-reach-online')), marginTop='1.2rem'),
        bgColor='light', className='vk-about-reach'),
    section(row(col(
        h('Kérdése van egy géppel kapcsolatban?'),
        p('Keresse munkatársainkat telefonon vagy személyesen üzleteinkben. Mondja el, milyen munkához keres gépet, mekkora területen használná, és milyen gyakran lenne rá szükség. Ezekből az adatokból már pontos, szakmailag indokolható ajánlás készíthető.'),
        buttons(button('Kapcsolatfelvétel', HOME + '/kapcsolat/'), marginTop='1.5rem', align='center'),
    )), bgColor='primary', paddingTop='4.5rem', paddingBottom='4.5rem', className='vk-cta vk-cta-primary'),
])

# ---------------------------------------------------------------- Szolgáltatások
pages['szolgaltatasok'] = '\n\n'.join([
    section(
        row(col(p('Szaküzleteinkben nemcsak gépet vásárolhat: üzembe helyezéssel, rendszeres karbantartással, gyors javítással és alkatrész-ellátással segítünk, hogy gépei hosszú évekig megbízhatóan dolgozzanak.'))),
        row(
            col(card(154, '2026/09/kerti-kisgepek-karbantartasa.jpg', 'Kerti kisgépek karbantartása', 'Rendszeres átvizsgálással és gondos karbantartással növeljük gépe élettartamát.', 'Szervizbejelentés', HOME + '/szerviz/'), width='1-3'),
            col(card(155, '2026/09/kerti-gep-szereles.jpg', 'Kerti gép szerelés', 'Gyors, szakszerű javítással biztosítjuk kerti gépe tartós, megbízható működését.', 'Szervizbejelentés', HOME + '/szerviz/'), width='1-3'),
            col(card(156, '2026/09/kerti-kisgep-szakkereskedes.jpg', 'Kerti kisgép szakkereskedés', 'Kerti kisgépek széles választéka szakértő tanácsadással és szervizháttérrel.', 'Termékek', HOME + '/termekek/'), width='1-3'),
            columns='1-3|1-3|1-3', marginTop='2.1rem')),
    section(row(
        col(
            h('Karbantartás és szezonális átvizsgálás', 3),
            p('A szezon előtti átvizsgálással megelőzhetők a váratlan leállások. A karbantartás során többek között:'),
            ul('ellenőrizzük és beállítjuk a motort, cseréljük az olajat és a szűrőket,',
               'élezzük vagy cseréljük a vágókéseket, láncokat,',
               'átnézzük a gyújtást, az üzemanyag-rendszert és a biztonsági elemeket,',
               'akkumulátoros gépeknél ellenőrizzük az akkumulátort és a töltőt.'),
            width='1-2'),
        col(
            h('Javítás és alkatrész', 3),
            p('Márkaszervizként a forgalmazott gyártók gépeit eredeti alkatrészekkel javítjuk. A hibafeltárás után árajánlatot adunk, és csak az Ön jóváhagyásával kezdjük meg a javítást.'),
            ul('fűnyírók, fűnyíró traktorok, robotfűnyírók,',
               'fűkaszák, sövényvágók, láncfűrészek,',
               'kapálógépek, áramfejlesztők, permetezők.'),
            width='1-2'),
        columns='1-2|1-2'), bgColor='light'),
    section(row(
        col(h('Üzembe helyezés és tanácsadás', 3),
            p('Az általunk értékesített gépeket átadás előtt összeszereljük, feltöltjük és kipróbáljuk, és megmutatjuk a biztonságos használatot. Nem tudja, melyik gép illik a feladathoz? Kérjen ajánlatot vagy hívjon minket.'),
            buttons(button('Ajánlatkérés', HOME + '/ajanlatkeres/', 'highlight'), button('Szervizbejelentés', HOME + '/szerviz/'), marginTop='1.5rem')),
    )),
])

# ---------------------------------------------------------------- Ajánlatkérés
pages['ajanlatkeres'] = '\n\n'.join([
    section(row(
        col(
            p('Írja meg, milyen munkához keres gépet, és munkatársunk egy munkanapon belül személyre szabott ajánlattal jelentkezik. Céges és nagyobb tételű megrendeléseknél egyedi árajánlatot adunk.'),
            form('ajanlatkeres', 'Ajánlatot kérek', 'Köszönjük! Ajánlatunkkal hamarosan jelentkezünk.',
                 fields_row(text_field('fullname', 'Név', 'Teljes név', REQUIRED_NAME),
                            text_field('company', 'Cégnév', 'Nem kötelező')),
                 fields_row(text_field('email', 'E-mail cím', 'pelda@email.hu', REQUIRED_EMAIL),
                            text_field('phone', 'Telefonszám', '+36 30 123 4567', REQUIRED_PHONE)),
                 select_field('category', 'Milyen gép érdekli?', ['Kertészeti gép', 'Erdészeti gép', 'Építőipari gép', 'Alkatrész, tartozék', 'Egyéb']),
                 textarea_field('message', 'Részletek', 'Milyen munkára, mekkora területre keres gépet? Van márka- vagy típuselképzelése?', 'required|Írja le, milyen gépet keres'),
                 accept_field(),
                 marginTop='2.1rem'),
            width='2-3'),
        col(contact_aside(), width='1-3'),
        columns='2-3|1-3')),
])

# ---------------------------------------------------------------- Szerviz
pages['szerviz'] = '\n\n'.join([
    section(row(
        col(
            h('Furcsa hang, nehéz indítás, gyengébb teljesítmény?'),
            p('Kérjen tanácsot, mielőtt a kisebb hiba komolyabb javítást igényel. Szakszervizünk a forgalmazott márkák kertészeti, erdészeti és építőipari gépeit javítja, eredeti alkatrészekkel.'),
            h('Így működik', 4, marginTop='2.1rem'),
            ol('<strong>Bejelentés</strong> – töltse ki az űrlapot vagy hívjon minket.',
               '<strong>Leadás</strong> – hozza be a gépet a kisvárdai vagy a vásárosnaményi üzletbe.',
               '<strong>Hibafeltárás és árajánlat</strong> – a javítás előtt egyeztetjük a költséget.',
               '<strong>Javítás</strong> – eredeti alkatrészekkel, a gyártói előírások szerint.',
               '<strong>Átvétel</strong> – értesítjük, amikor a gép elkészült.'),
            width='1-2'),
        col(
            h('Szervizbejelentés', 3),
            form('szerviz', 'Bejelentés elküldése', 'Köszönjük a bejelentést! Munkatársunk hamarosan visszahívja.',
                 fields_row(text_field('fullname', 'Név', 'Teljes név', REQUIRED_NAME),
                            text_field('phone', 'Telefonszám', '+36 30 123 4567', REQUIRED_PHONE)),
                 text_field('email', 'E-mail cím', 'pelda@email.hu', 'email|Érvénytelen e-mail cím'),
                 fields_row(text_field('machine', 'Gép márkája és típusa', 'pl. STIHL MS 250', 'required|Adja meg a gép típusát'),
                            select_field('store', 'Melyik üzletbe hozná?', ['Kisvárda', 'Vásárosnamény'])),
                 textarea_field('message', 'A hiba leírása', 'Mikor jelentkezik a hiba, mit tapasztal?', 'required|Írja le a hibát'),
                 accept_field()),
            width='1-2'),
        columns='1-2|1-2')),
    cta('Alkatrészre van szüksége?', 'Eredeti alkatrészeket és tartozékokat raktárról vagy rövid határidővel beszerzünk. Kérdezzen minket a gép típusának megadásával.',
        ('Alkatrészek, tartozékok', HOME + '/termekkategoria/alkatreszek-tartozekok/'), ('Kapcsolat', HOME + '/kapcsolat/')),
])

# ---------------------------------------------------------------- Kapcsolat
def store_col(store):
    name, address, phone, tel, maps = store
    return col(group(
        h(name, 4),
        p('%s<br><a href="%s">%s</a>' % (address, tel, phone)),
        p('<a href="%s" target="_blank" rel="noopener">Útvonaltervezés</a>' % maps),
        direction='column'), width='1-3')


pages['kapcsolat'] = '\n\n'.join([
    section(row(
        col(group(
            h('Központi ügyfélszolgálat', 4),
            p('<a href="tel:+36302895977">+36 30 289 5977</a><br><a href="mailto:info@vasskisgep.hu">info@vasskisgep.hu</a>'),
            p('Vass Kisgép 2011 Kft.'),
            direction='column'), width='1-3'),
        store_col(STORE_KV),
        store_col(STORE_VN),
        columns='1-3|1-3|1-3')),
    section(row(
        col(
            h('Írjon nekünk', 3),
            form('kapcsolat', 'Üzenet küldése', 'Köszönjük az üzenetét! Hamarosan válaszolunk.',
                 fields_row(text_field('fullname', 'Név', 'Teljes név', REQUIRED_NAME),
                            text_field('email', 'E-mail cím', 'pelda@email.hu', REQUIRED_EMAIL)),
                 text_field('phone', 'Telefonszám', 'Nem kötelező'),
                 textarea_field('message', 'Üzenet', 'Miben segíthetünk?', 'required|Írja meg az üzenetét'),
                 accept_field()),
            width='1-2'),
        col(custom('vasskisgep/map', query='Vass Kisgép Kisvárda Városmajor út 20', title='Vass Kisgép – Kisvárda'), width='1-2'),
        columns='1-2|1-2'), bgColor='light'),
])

# ---------------------------------------------------------------- Kedvencek, összehasonlítás
pages['kedvencek'] = section(row(col(custom('vasskisgep/wishlist'))))
pages['osszehasonlitas'] = section(row(col(
    p('Legfeljebb 4 terméket tehet egymás mellé. A termékeket a termékkártyán vagy a termékoldalon az összehasonlítás ikonnal jelölheti ki.', className='vk-muted'),
    custom('vasskisgep/compare'))))

# ---------------------------------------------------------------- Szállítás és fizetés
pages['szallitas-es-fizetes'] = section(row(col(
    h('Szállítási módok', 3),
    ul('<strong>Házhozszállítás futárszolgálattal</strong> – Magyarország területére, a rendelés visszaigazolásától számított 1-5 munkanapon belül. A szállítási díjat a pénztárban, a rendelés leadása előtt látja.',
       '<strong>Személyes átvétel – Kisvárda</strong>, Városmajor út 20. – ingyenes, értesítésünk után.',
       '<strong>Személyes átvétel – Vásárosnamény</strong>, Jókai M. út 51. – ingyenes, értesítésünk után.'),
    p('Nagyobb méretű gépeknél (pl. fűnyíró traktor) a szállítás módját és díját egyedileg egyeztetjük.'),
    h('Fizetési módok', 3, marginTop='2.1rem'),
    ul('<strong>Fizetés átvételkor</strong> – a futárnak vagy az üzletben, készpénzzel vagy bankkártyával.',
       '<strong>Online bankkártyás fizetés</strong> – a Barion biztonságos fizetési oldalán (Visa, Mastercard, American Express, Apple Pay, Google Pay).',
       '<strong>Előre utalás</strong> – banki átutalással; a terméket a jóváírás után adjuk át vagy küldjük el.'),
    p('Az árak forintban értendők és tartalmazzák a 27% ÁFA-t. A vásárlásról elektronikus számlát küldünk.'),
    h('Rendelés menete', 3, marginTop='2.1rem'),
    ol('Tegye a kosárba a kiválasztott termékeket.',
       'A pénztárban adja meg a számlázási és szállítási adatokat, válassza ki a szállítási és fizetési módot.',
       'A rendelés leadása után e-mailben visszaigazoljuk a rendelést.',
       'Értesítjük, amikor a csomag feladásra került, vagy átvehető az üzletben.'),
    p('Kérdése van? Hívjon minket: <a href="tel:+36302895977">+36 30 289 5977</a>, vagy írjon: <a href="mailto:info@vasskisgep.hu">info@vasskisgep.hu</a>.'),
    className='vk-legal')))


def legal(*paragraphs):
    return section(row(col(*paragraphs, className='vk-legal')))


TODO = '<mark>[kitöltendő]</mark>'

# ---------------------------------------------------------------- ÁSZF
pages['aszf'] = legal(
    p('<em>Hatályos: 2026. október 1-jétől. Ez a szöveg általános webáruházi minta; élesítés előtt jogász ellenőrzése javasolt.</em>'),
    h('1. A szolgáltató adatai', 3),
    ul('Cégnév: Vass Kisgép 2011 Kft.',
       'Székhely: 4600 Kisvárda, Városmajor út 20.',
       'Cégjegyzékszám: ' + TODO,
       'Adószám: ' + TODO,
       'E-mail: info@vasskisgep.hu',
       'Telefon: +36 30 289 5977',
       'Üzletek: 4600 Kisvárda, Városmajor út 20.; 4800 Vásárosnamény, Jókai M. út 51.',
       'Tárhelyszolgáltató: ' + TODO),
    h('2. Az ÁSZF hatálya', 3),
    p('Jelen Általános Szerződési Feltételek (ÁSZF) a Vass Kisgép 2011 Kft. (a továbbiakban: Eladó) által üzemeltetett webáruházban (a továbbiakban: Webáruház) történő vásárlás feltételeit tartalmazza. A megrendelés leadásával a Vásárló elfogadja az ÁSZF-et. A szerződés nyelve magyar; a szerződés elektronikus úton kötött, írásban megkötött szerződésnek minősül, amelyet az Eladó iktat.'),
    h('3. Termékek és árak', 3),
    p('A Webáruházban feltüntetett árak magyar forintban értendők, és tartalmazzák a 27% általános forgalmi adót. Az árak nem tartalmazzák a szállítás díját, amely a pénztárban, a rendelés leadása előtt jelenik meg. A termékképek illusztrációk, a termék a képtől kisebb mértékben eltérhet. Nyilvánvalóan hibás ár esetén az Eladó nem köteles a terméket a hibás áron értékesíteni; ilyenkor felajánlja a helyes áron történő teljesítést, amelytől a Vásárló elállhat.'),
    h('4. A rendelés menete', 3),
    p('A Vásárló a kiválasztott termékeket a kosárba helyezi, majd a pénztárban megadja a számlázási és szállítási adatait, kiválasztja a szállítási és fizetési módot. A „Megrendelem” gomb megnyomásával a Vásárló fizetési kötelezettséggel járó ajánlatot tesz. A rendelés leadása előtt az adatbeviteli hibák a pénztárban javíthatók. Az Eladó a megrendelés beérkezését e-mailben haladéktalanul visszaigazolja; a szerződés a visszaigazolással jön létre.'),
    h('5. Fizetés', 3),
    p('Fizetési lehetőségek: fizetés átvételkor (futárnál vagy az üzletben), online bankkártyás fizetés a Barion Payment Zrt. rendszerén keresztül, valamint előre utalás. Bankkártyás fizetésnél a kártyaadatok az Eladóhoz nem jutnak el. A vásárlásról az Eladó számlát állít ki.'),
    h('6. Szállítás és átvétel', 3),
    p('A szállítás Magyarország területén futárszolgálattal, vagy személyes átvétellel az Eladó üzleteiben történik. A teljesítési határidő a visszaigazolástól számított 1-5 munkanap, rendelésre beszerzett termékeknél a termékoldalon jelzett idő. Ha a termék nem áll rendelkezésre, az Eladó erről haladéktalanul tájékoztatja a Vásárlót, és a már kifizetett összeget legkésőbb 30 napon belül visszatéríti. Átvételkor kérjük, ellenőrizze a csomag épségét; sérült csomag esetén jegyzőkönyv felvételét kérje.'),
    h('7. Elállási jog', 3),
    p('A fogyasztónak minősülő Vásárlót a 45/2014. (II. 26.) Korm. rendelet alapján a termék átvételétől számított 14 napon belül indoklás nélküli elállási jog illeti meg. Az elállás részleteit és a nyilatkozatmintát az <a href="' + HOME + '/elallas-es-visszakuldes/">Elállás és visszaküldés</a> oldal tartalmazza.'),
    h('8. Szavatosság és jótállás', 3),
    p('Az Eladót a Polgári Törvénykönyv szerinti kellékszavatossági és termékszavatossági kötelezettség terheli. A tartós fogyasztási cikkekre a 151/2003. (IX. 22.) Korm. rendelet szerinti kötelező jótállás vonatkozik: 10 000 Ft és 250 000 Ft közötti eladási ár esetén 2 év, 250 000 Ft feletti eladási ár esetén 3 év. A gyártói garancia ezen felül érvényesíthető. Szavatossági és jótállási igényét üzleteinkben vagy az info@vasskisgep.hu címen jelentheti be; a javítást márkaszervizünk végzi.'),
    h('9. Panaszkezelés és jogorvoslat', 3),
    p('Panaszát az info@vasskisgep.hu címen, telefonon (+36 30 289 5977) vagy személyesen üzleteinkben teheti meg. Az írásbeli panaszra 30 napon belül érdemben válaszolunk. Ha a panasz rendezése nem sikerül, a Vásárló a lakóhelye szerint illetékes békéltető testülethez (a székhely szerint illetékes: Szabolcs-Szatmár-Bereg Vármegyei Békéltető Testület, 4400 Nyíregyháza, Széchenyi u. 2.) vagy a fogyasztóvédelmi hatósághoz fordulhat, illetve igénybe veheti az Európai Unió online vitarendezési platformját.'),
    h('10. Adatkezelés', 3),
    p('A személyes adatok kezelésének részleteit az <a href="' + HOME + '/adatkezelesi-tajekoztato/">Adatkezelési tájékoztató</a> tartalmazza.'),
)

# ---------------------------------------------------------------- Elállás
pages['elallas-es-visszakuldes'] = legal(
    p('<em>Ez a szöveg általános webáruházi minta; élesítés előtt jogász ellenőrzése javasolt.</em>'),
    h('Elállási jog', 3),
    p('Fogyasztóként a termék (több termék esetén az utolsó termék) átvételétől számított 14 napon belül indoklás nélkül elállhat a vásárlástól. Az elállási jogot a szerződés megkötése és a termék átvétele között is gyakorolhatja.'),
    h('Az elállás menete', 3),
    ol('Elállási szándékát egyértelmű nyilatkozattal jelezze az info@vasskisgep.hu címen, postán (4600 Kisvárda, Városmajor út 20.) vagy üzleteinkben. Használhatja az alábbi nyilatkozatmintát is.',
       'A terméket az elállás közlésétől számított 14 napon belül küldje vissza vagy adja le bármelyik üzletünkben. A visszaküldés közvetlen költsége a Vásárlót terheli.',
       'A vételárat és a legolcsóbb szállítási mód díját az elállás kézhezvételétől számított 14 napon belül visszatérítjük, az eredeti fizetési móddal megegyező módon. A visszatérítést visszatarthatjuk, amíg a terméket vissza nem kaptuk.'),
    p('A Vásárló a termék jellegének, tulajdonságainak és működésének megállapításához szükséges használatot meghaladó használatból eredő értékcsökkenésért felel. Üzemanyaggal vagy olajjal feltöltött, illetve használatba vett gépek visszaküldése előtt kérjük, egyeztessen velünk.'),
    h('Elállási nyilatkozatminta', 3),
    p('Címzett: Vass Kisgép 2011 Kft., 4600 Kisvárda, Városmajor út 20., info@vasskisgep.hu<br>Alulírott kijelentem, hogy gyakorlom elállási jogomat az alábbi termék(ek) adásvételére irányuló szerződés tekintetében:<br>Termék(ek): …<br>Rendelésszám: …<br>Átvétel időpontja: …<br>A fogyasztó neve és címe: …<br>Kelt: …<br>Aláírás (kizárólag papíron tett nyilatkozat esetén): …'),
    h('Hibás termék', 3),
    p('Ha a termék hibás, szavatossági vagy jótállási igényét az <a href="' + HOME + '/aszf/">ÁSZF</a> 8. pontja szerint érvényesítheti.'),
)

# ---------------------------------------------------------------- Adatkezelés
pages['adatkezelesi-tajekoztato'] = legal(
    p('<em>Hatályos: 2026. október 1-jétől. Ez a szöveg általános minta az Európai Parlament és a Tanács (EU) 2016/679 rendelete (GDPR) alapján; élesítés előtt adatvédelmi szakember ellenőrzése javasolt.</em>'),
    h('1. Az adatkezelő', 3),
    ul('Vass Kisgép 2011 Kft.',
       'Székhely: 4600 Kisvárda, Városmajor út 20.',
       'E-mail: info@vasskisgep.hu, telefon: +36 30 289 5977',
       'Cégjegyzékszám: ' + TODO + ', adószám: ' + TODO),
    h('2. Kezelt adatok, célok és jogalapok', 3),
    ul('<strong>Vásárlás és rendelés teljesítése</strong> – név, számlázási és szállítási cím, e-mail cím, telefonszám, cégnév és adószám (céges vásárlásnál), rendelés adatai. Jogalap: szerződés teljesítése (GDPR 6. cikk (1) b)). Megőrzés: a számviteli bizonylatokat a számviteli törvény alapján 8 évig őrizzük (6. cikk (1) c)).',
       '<strong>Vásárlói fiók</strong> – név, e-mail cím, jelszó (titkosítva), címek, korábbi rendelések. Jogalap: hozzájárulás (6. cikk (1) a)). Megőrzés: a fiók törléséig.',
       '<strong>Kapcsolatfelvétel, ajánlatkérés, szervizbejelentés, visszahívás</strong> – név, telefonszám, e-mail cím, üzenet és gépadatok. Jogalap: hozzájárulás, illetve szerződéskötést megelőző lépések (6. cikk (1) a) és b)). Megőrzés: az ügy lezárásától számított 1 évig.',
       '<strong>Kedvencek és összehasonlítás</strong> – a kijelölt termékek azonosítói a böngésző sütijében (bejelentkezett vásárlónál a fiókban is). Jogalap: jogos érdek a kényelmi funkció biztosításához.'),
    h('3. Adatfeldolgozók és címzettek', 3),
    ul('Tárhelyszolgáltató: ' + TODO,
       'Futárszolgálat (házhozszállításnál): ' + TODO,
       'Barion Payment Zrt. (1117 Budapest, Irinyi József u. 4-20.) – online bankkártyás fizetés',
       'Számlázó program szolgáltatója: ' + TODO,
       'Könyvelő: ' + TODO),
    h('4. Sütik', 3),
    p('A webáruház működéséhez szükséges sütiket használunk (kosár, bejelentkezés, pénztár, kedvencek és összehasonlítás listák). Ezek nélkül a vásárlás nem lehetséges, ezért ezekhez nem kérünk hozzájárulást. A Kapcsolat oldalon beágyazott Google Térkép a Google saját sütijeit használhatja.'),
    h('5. Az érintett jogai', 3),
    p('Ön kérheti a személyes adataihoz való hozzáférést, azok helyesbítését, törlését vagy kezelésének korlátozását, tiltakozhat az adatkezelés ellen, élhet az adathordozhatósághoz való jogával, és hozzájárulását bármikor visszavonhatja. Kérelmét az info@vasskisgep.hu címre küldheti; arra legkésőbb 1 hónapon belül válaszolunk.'),
    h('6. Jogorvoslat', 3),
    p('Panaszával a Nemzeti Adatvédelmi és Információszabadság Hatósághoz fordulhat (1055 Budapest, Falk Miksa utca 9-11.; ugyfelszolgalat@naih.hu; www.naih.hu), illetve bírósághoz fordulhat.'),
)


if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    for slug, content in pages.items():
        path = os.path.join(OUT, slug + '.html')
        with open(path, 'w') as f:
            f.write(content + '\n')
        print(path)
