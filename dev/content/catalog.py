"""
Katalógus-törzsadatok: kategóriafa (a Figma megamenüje alapján), termék-tulajdonságok,
bemutató (DEMO) termékek. Kimenet: setup/catalog/categories.json, setup/catalog/demo-products.json.

A "Kerti és erdészeti gépek" fa a Figma megamenüjéből származik; az "Építőipari gépek" és az
"Alkatrészek és karbantartás" fa a design szöveges melléklete nélkül készült, pontosítandó.
"""
import json
import os

OUT = os.path.join(os.path.dirname(__file__), '../../wp-content/themes/vasskisgep/setup/catalog/')

# (név, [gyerekek]) – 1. szint: megamenü fül, 2.: oszlop, 3.: csoport, 4.: kategória
TREE = [
    ('Kerti és erdészeti gépek', [
        ('Fűnyírás és gyepápolás', [
            ('Fűnyírók', ['Benzinmotoros fűnyírók', 'Akkumulátoros fűnyírók', 'Suhanó- és hengerkéses fűnyírók']),
            ('Fűnyíró traktorok', ['Fűgyűjtős fűnyíró traktorok', 'Oldalkidobós fűnyíró traktorok']),
            ('További gépek', ['Robotfűnyírók', 'Fűkaszák és szegélynyírók', 'Gyepszellőztetők', 'Kerekes és távirányítású bozótvágók']),
        ]),
        ('Fa- és sövényápolás', [
            ('Láncfűrészek és ágnyesők', ['Benzines motorfűrészek', 'Elektromos láncfűrészek', 'Akkumulátoros láncfűrészek', 'Magassági ágvágók', 'Fagondozó fűrészek']),
            ('Metszés és sövényápolás', ['Sövénynyírók és sövényvágók', 'Metszőollók', 'Kéziszerszámok és erdészeti eszközök', 'Kerekes bozótvágó']),
            ('Faanyag-feldolgozás', ['Ágaprítók és ágdarálók', 'Komposztaprítók', 'Rönkhasítók', 'Tuskómarók']),
        ]),
        ('Talajművelés és kertgondozás', [
            ('Talajművelő gépek', ['Kapálógépek és rotációs kapák', 'Talajmarók', 'Multifunkciós művelőgépek', 'Egytengelyes traktorok']),
            ('Kombi- és multirendszerek', ['Kombirendszerek', 'Multirendszerek', 'Adapterek és munkaeszközök']),
            ('Fúrás, ásás és szállítás', ['Talaj- és gödörfúrók', 'Árokásók', 'Utánfutók', 'Ekék és talajművelő eszközök']),
            ('Permetezés', ['Kézi és háti permetezők', 'Akkumulátoros permetezők', 'Motoros permetezőgépek']),
        ]),
        ('Tisztítás, víz és energia', [
            ('Terület- és kerttisztítás', ['Lombfúvók', 'Lombszívók és aprítók', 'Seprőgépek', 'Térkőtisztítók']),
            ('Gépi tisztítás', ['Magasnyomású mosók', 'Száraz-nedves porszívók']),
            ('Víz és energia', ['Vízszivattyúk és motorpumpák', 'Áramfejlesztők és aggregátorok', 'Kompresszorok', 'Motorok']),
        ]),
    ]),
    ('Építőipari gépek', [
        ('Vágás és bontás', [
            ('Daraboló gépek', ['Motoros daraboló gépek', 'Akkumulátoros daraboló gépek', 'Gyémánt vágótárcsák']),
            ('Bontás és fúrás', ['Bontókalapácsok', 'Magfúrók']),
        ]),
        ('Tömörítés és betonozás', [
            ('Tömörítő gépek', ['Lapvibrátorok', 'Döngölők']),
            ('Betongépek', ['Betonkeverők', 'Betonvibrátorok']),
        ]),
        ('Ipari energia és tisztítás', [
            ('Energia', ['Ipari áramfejlesztők', 'Ipari kompresszorok']),
            ('Ipari tisztítás', ['Ipari magasnyomású mosók', 'Ipari porszívók']),
        ]),
    ]),
    ('Alkatrészek és karbantartás', [
        ('Alkatrészek', [
            ('Vágóelemek', ['Láncok és vezetőlemezek', 'Damilok és fűkaszafejek', 'Fűnyírókések']),
            ('Motoralkatrészek', ['Szűrők', 'Gyújtógyertyák']),
        ]),
        ('Kenő- és üzemanyagok', [
            ('Olajok', ['Motorolajok', 'Lánckenő olajok']),
            ('Üzemanyagok', ['Üzemanyagok és adalékok']),
        ]),
        ('Akkumulátorok és töltők', [
            ('Energiaellátás', ['Akkumulátorok', 'Töltők']),
        ]),
        ('Munkaruházat és védőfelszerelés', [
            ('Védőfelszerelés', ['Vágásbiztos ruházat', 'Sisakok és arcvédők', 'Kesztyűk és lábbelik']),
        ]),
    ]),
]

# Szűrhető tulajdonságok (globális WooCommerce attribútumok). Sorrend = megjelenési sorrend.
ATTRIBUTES = [
    {'slug': 'loketterfogat', 'label': 'Lökettérfogat', 'unit': 'cm³'},
    {'slug': 'teljesitmeny', 'label': 'Teljesítmény', 'unit': 'LE'},
    {'slug': 'tomeg', 'label': 'Tömeg', 'unit': 'kg'},
    {'slug': 'vagasszelesseg', 'label': 'Vágásszélesség', 'unit': 'cm'},
    {'slug': 'garancia', 'label': 'Garancia', 'unit': 'hó'},
]

MOWER_DESC = 'A {name} a közepes méretű füves területek hatékony nyírásához ideális. A könnyű indítású motor és a könnyen gördülő kerekek kényelmes munkát tesznek lehetővé, a központi vágásmagasság-állítással a vágásmagasság egyetlen mozdulattal beállítható. A nyesedék a telítettségjelzős fűgyűjtőbe gyűjthető, mulcsozható vagy oldalra kidobható.'
GENERIC_DESC = 'A {name} megbízható, jól szervizelhető gép otthoni és professzionális felhasználásra. Üzembe helyezve, kipróbálva adjuk át, alkatrészt és szervizt üzleteinkben biztosítunk.'
DEMO_NOTE = '<em>Bemutató termék a webshop teszteléséhez – élesítés előtt törlendő.</em>'

# name, brand, category (4. szintű név), regular, sale, stock ('instock:5' | 'backorder' | 'outofstock'),
# attributes {slug: érték}, promo, cofidis, featured, seasons, image (média neve), weight, cross-sell SKU-k
P = [
    ('STIHL RM 248 benzinmotoros fűnyíró', 'stihl', 'Benzinmotoros fűnyírók', 201990, 129900, 'instock:5',
     {'loketterfogat': '127', 'teljesitmeny': '2,4', 'tomeg': '26', 'vagasszelesseg': '46', 'garancia': '24'},
     'Ingyenes szállítás!', True, True, ['tavasz', 'nyar'], 'funyiro', 26, ['DEMO-019', 'DEMO-020']),
    ('STIHL RM 448 T benzinmotoros fűnyíró', 'stihl', 'Benzinmotoros fűnyírók', 299900, 249900, 'backorder',
     {'loketterfogat': '127', 'teljesitmeny': '2,4', 'tomeg': '28', 'vagasszelesseg': '46', 'garancia': '24'},
     'Ingyenes szállítás!\n+Ajándék ponyva', True, False, ['tavasz', 'nyar'], 'TESZT001', 28, ['DEMO-019', 'DEMO-020']),
    ('CEDRUS benzinmotoros fűnyíró 42 cm', 'cedrus', 'Benzinmotoros fűnyírók', 89900, None, 'instock:8',
     {'loketterfogat': '139', 'teljesitmeny': '2,8', 'tomeg': '24', 'vagasszelesseg': '42', 'garancia': '12'},
     '', False, False, ['tavasz', 'nyar'], 'TESZT001', 24, ['DEMO-019']),
    ('STIGA akkumulátoros fűnyíró 41 cm', 'stiga', 'Akkumulátoros fűnyírók', 169900, None, 'instock:3',
     {'tomeg': '19', 'vagasszelesseg': '41', 'garancia': '24'},
     '', True, False, ['tavasz', 'nyar'], 'stiga-lawnmower-in-garden', 19, []),
    ('STIHL akkumulátoros fűnyíró 37 cm', 'stihl', 'Akkumulátoros fűnyírók', 139900, 119900, 'instock:4',
     {'tomeg': '15', 'vagasszelesseg': '37', 'garancia': '24'},
     '', False, False, ['tavasz'], 'stiga-lawnmower-in-garden', 15, []),
    ('STIGA fűgyűjtős fűnyíró traktor 98 cm', 'stiga', 'Fűgyűjtős fűnyíró traktorok', 1249000, None, 'instock:1',
     {'loketterfogat': '452', 'teljesitmeny': '11,5', 'tomeg': '190', 'vagasszelesseg': '98', 'garancia': '24'},
     'Ingyenes szállítás!', True, True, ['tavasz', 'nyar'], 'funyirotraktorok-kulonbozo-szinben', 190, []),
    ('STIGA oldalkidobós fűnyíró traktor 107 cm', 'stiga', 'Oldalkidobós fűnyíró traktorok', 1090000, None, 'backorder',
     {'loketterfogat': '656', 'teljesitmeny': '15', 'tomeg': '205', 'vagasszelesseg': '107', 'garancia': '24'},
     'Ingyenes szállítás!', True, False, ['nyar'], 'funyirotraktorok-kulonbozo-szinben', 205, []),
    ('STIGA robotfűnyíró 1000 m²', 'stiga', 'Robotfűnyírók', 499900, None, 'instock:2',
     {'tomeg': '12', 'vagasszelesseg': '22', 'garancia': '24'},
     '', True, False, ['tavasz', 'nyar'], '', 12, []),
    ('KASEI benzinmotoros fűkasza 43 cm³', 'kasei', 'Fűkaszák és szegélynyírók', 59900, 52900, 'instock:10',
     {'loketterfogat': '43', 'teljesitmeny': '1,7', 'tomeg': '8', 'vagasszelesseg': '42', 'garancia': '12'},
     '', False, False, ['nyar'], 'kasei-fukasza', 8, ['DEMO-022']),
    ('STIHL FS 235 motoros kasza', 'stihl', 'Fűkaszák és szegélynyírók', 219900, 142900, 'instock:3',
     {'loketterfogat': '36,3', 'teljesitmeny': '2,1', 'tomeg': '6,5', 'vagasszelesseg': '42', 'garancia': '24'},
     '', True, True, ['nyar'], 'kasei-fukasza', 6.5, ['DEMO-022']),
    ('STIHL benzines motorfűrész 40 cm', 'stihl', 'Benzines motorfűrészek', 149900, None, 'instock:6',
     {'loketterfogat': '45,6', 'teljesitmeny': '3', 'tomeg': '4,6', 'vagasszelesseg': '40', 'garancia': '24'},
     '', True, True, ['osz', 'tel'], 'stihl-tools-in-forest', 4.6, ['DEMO-021']),
    ('STIHL akkumulátoros láncfűrész 30 cm', 'stihl', 'Akkumulátoros láncfűrészek', 109900, None, 'instock:4',
     {'tomeg': '3,1', 'vagasszelesseg': '30', 'garancia': '24'},
     '', False, False, ['osz'], 'stihl-tools-in-forest', 3.1, ['DEMO-021']),
    ('BLUEBIRD akkumulátoros metszőolló', 'bluebird', 'Metszőollók', 129900, None, 'instock:5',
     {'tomeg': '0,9', 'garancia': '12'},
     '', False, False, ['tel', 'tavasz'], 'bluebird-agvago', 0.9, []),
    ('BLUEBIRD akkumulátoros magassági ágvágó', 'bluebird', 'Magassági ágvágók', 189900, None, 'backorder',
     {'tomeg': '3,4', 'vagasszelesseg': '25', 'garancia': '12'},
     '', False, False, ['tel'], 'bluebird-agvago', 3.4, []),
    ('RATO kapálógép 7 LE', 'rato', 'Kapálógépek és rotációs kapák', 189900, 169900, 'instock:2',
     {'loketterfogat': '212', 'teljesitmeny': '7', 'tomeg': '52', 'vagasszelesseg': '90', 'garancia': '24'},
     'Ingyenes szállítás!', True, False, ['tavasz'], 'rato-rotator', 52, []),
    ('CEDRUS benzinmotoros ágaprító', 'cedrus', 'Ágaprítók és ágdarálók', 459900, None, 'instock:1',
     {'loketterfogat': '420', 'teljesitmeny': '15', 'tomeg': '118', 'garancia': '24'},
     'Ingyenes szállítás!', True, True, ['osz'], 'cedrus-agdaralo-kertben', 118, []),
    ('KASEI háti motoros permetező 26 l', 'kasei', 'Motoros permetezőgépek', 119900, None, 'instock:3',
     {'loketterfogat': '41,5', 'teljesitmeny': '2,1', 'tomeg': '11', 'garancia': '12'},
     '', False, False, ['tavasz', 'nyar'], '', 11, []),
    ('RATO benzines áramfejlesztő 3,5 kW', 'rato', 'Áramfejlesztők és aggregátorok', 229900, None, 'outofstock',
     {'loketterfogat': '212', 'teljesitmeny': '7', 'tomeg': '45', 'garancia': '24'},
     '', True, False, [], '', 45, []),
    ('BRIGGS motorolaj 0,6 l SAE30', '', 'Motorolajok', 2500, None, 'instock:40', {}, '', False, False, [], '', 0.6, []),
    ('STIHL 10W-30 motorolaj 600 ml', 'stihl', 'Motorolajok', 3490, None, 'instock:30', {}, '', False, False, [], '', 0.6, []),
    ('STIHL lánc 3/8" P 1,3 mm 55 szem', 'stihl', 'Láncok és vezetőlemezek', 8990, None, 'instock:25', {}, '', False, False, [], '', 0.3, []),
    ('KASEI fűkasza damil 2,4 mm 15 m', 'kasei', 'Damilok és fűkaszafejek', 2490, None, 'instock:50', {}, '', False, False, [], '', 0.2, []),
    ('STIHL vágásbiztos nadrág', 'stihl', 'Vágásbiztos ruházat', 49900, None, 'outofstock', {}, '', False, False, [], '', 1.2, []),
]

MOWER_CATS = {'Benzinmotoros fűnyírók', 'Akkumulátoros fűnyírók', 'Fűgyűjtős fűnyíró traktorok', 'Oldalkidobós fűnyíró traktorok', 'Robotfűnyírók'}


def product(i, row):
    (name, brand, cat, regular, sale, stock, attrs, promo, cofidis, featured, seasons, image, weight, cross) = row
    desc = (MOWER_DESC if cat in MOWER_CATS else GENERIC_DESC).format(name=name)
    return {
        'sku': 'DEMO-%03d' % i,
        'name': name,
        'brand': brand,
        'category': cat,
        'regular_price': regular,
        'sale_price': sale,
        'stock': stock,
        'attributes': attrs,
        'promo': promo,
        'cofidis': cofidis,
        'featured': featured,
        'seasons': seasons,
        'image': image,
        'weight': weight,
        'cross_sells': cross,
        'short_description': desc.split('. ')[0] + '.',
        'description': '<p>' + desc + '</p>\n<p>' + DEMO_NOTE + '</p>',
    }


if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    json.dump({'tree': TREE, 'attributes': ATTRIBUTES}, open(OUT + 'categories.json', 'w'), ensure_ascii=False, indent=1)
    json.dump([product(i + 1, row) for i, row in enumerate(P)], open(OUT + 'demo-products.json', 'w'), ensure_ascii=False, indent=1)
    print('ok', len(P))
