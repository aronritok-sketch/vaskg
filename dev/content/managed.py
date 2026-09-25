"""
A meglévő (DB-ben tárolt) tartalmak módosított változatai: setup/managed/.

Bemenet: az eredeti adatbázis-mentésből kinyert tartalmak (dev/content/original/*.html,
a dev URL helyén {{home}}). Kimenet: setup/managed/*.html vázlatok + manifest.json
(az eredeti tartalom md5-je, a telepítő ez alapján dönti el, hogy felülírhat-e).

Utána minden fájlt át kell futtatni a dev/canon.sh-n (kanonikus markup).
"""
import hashlib
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(__file__))
from blocks import attrs, block, HOME  # noqa

BASE = os.path.dirname(__file__)
ORIG = os.path.join(BASE, 'original')
OUT = os.path.join(BASE, '../../wp-content/themes/vasskisgep/setup/managed/')

ENTRIES = [
    # id, post_type, file
    (21, 'iu_template', 'header.html'),
    (30, 'iu_template', 'footer.html'),
    (2, 'page', 'front-page.html'),
    (6, 'page', 'termekek.html'),
    # A Loop Product mintát már nem kezeljük: a kártya gombjait a Lists osztály teszi a képre.
]


def replace_block_attrs(content, name, match, update):
    """A `name` blokkok közül azoknak az attribútumait frissíti, amelyekre match(attrs) igaz."""
    pattern = re.compile(r'<!-- wp:' + re.escape(name) + r'( \{.*?\})? (/)?-->', re.S)

    def repl(m):
        a = json.loads(m.group(1)) if m.group(1) else {}
        if not match(a):
            return m.group(0)
        a = update(dict(a))
        return '<!-- wp:' + name + attrs(a) + ' ' + (m.group(2) or '') + '-->'
    return pattern.sub(repl, content)


def header(s):
    targets = {'Összehasonlítás': 'osszehasonlitas', 'Kedvencek': 'kedvencek'}
    for text, slug in targets.items():
        s = replace_block_attrs(s, 'iu/icon-text', lambda a, t=text: a.get('text') == t,
                                lambda a, slug=slug: {**a, 'linkUrl': HOME + '/' + slug + '/'})
    return s


def footer(s):
    s = s.replace('https://dev.otletoldal.hu/adatkezelesi-tajekoztato', HOME + '/adatkezelesi-tajekoztato/')
    return s.replace('>Szervíz</a>', '>Szerviz</a>')


def front_page(s):
    # Hero: "Termékek megtekintése" → márkaoldal.
    brands = ['stihl', 'stiga', 'cedrus', 'bluebird', 'rato', 'kasei']

    def hero(a):
        for b in brands:
            if b + '-logo' in a.get('open', ''):
                a['open'] = a['open'].replace('href="#"', 'href="' + HOME + '/gyarto/' + b + '/"')
        return a
    s = replace_block_attrs(s, 'vasskisgep/pager-hero-slide', lambda a: 'href="#"' in a.get('open', ''), hero)

    # Szöveges gombok célja.
    links = {
        'Tudjon meg többet': HOME + '/rolunk/',
        'Kérek tanácsadást': HOME + '/szerviz/',
        'Összes termék megtekintése': HOME + '/termekek/',
    }
    s = replace_block_attrs(s, 'iu/button', lambda a: a.get('text') in links and not a.get('linkUrl'),
                            lambda a: {**a, 'linkUrl': links[a['text']]})
    s = replace_block_attrs(s, 'iu/card', lambda a: a.get('button') == 'Bővebben' and not a.get('linkUrl'),
                            lambda a: {**a, 'linkUrl': HOME + '/szolgaltatasok/'})

    # A három azonos termékkarusszel helyett válogatott listák.
    sources = iter([('featured', '3'), ('season', '4'), ('popular', '3')])

    def carousel(m):
        source, columns = next(sources)
        return '<!-- wp:vasskisgep/products-carousel' + attrs({'source': source, 'columns': columns}) + ' /-->'
    s = re.sub(r'<!-- wp:iu-woocommerce/product-carousel( \{.*?\})? /-->', carousel, s, flags=re.S)

    # Kitalált (lorem ipsum) vélemények helyett a Google-profil valódi értékelései.
    start = s.index('<section class="iu-section testimonials">') + len('<section class="iu-section testimonials">')
    end = s.index('</section>', start)
    s = s[:start] + ('<!-- wp:iu/row -->\n<div class="iu-row"><!-- wp:iu/column -->\n<div class="iu-column iu-column-1-1">'
                     '<!-- wp:vasskisgep/google-reviews /--></div>\n<!-- /wp:iu/column --></div>\n<!-- /wp:iu/row -->') + s[end:]

    s = s.replace('<p class="iu-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmo</p>',
                  '<p class="iu-text">Üzembe helyezés, karbantartás, javítás és alkatrész-ellátás – hogy gépei hosszú évekig megbízhatóan dolgozzanak.</p>')
    return s


def shop_page(s):
    # A szűrős terméklista (vasskisgep/product-listing) a régi szűrő + [products] helyett.
    return ('<!-- wp:iu/section {"paddingTop":"1.5rem"} -->\n<section class="iu-section"><!-- wp:iu/row -->\n'
            '<div class="iu-row"><!-- wp:iu/column -->\n<div class="iu-column iu-column-1-1">'
            '<!-- wp:vasskisgep/product-listing /--></div>\n<!-- /wp:iu/column --></div>\n<!-- /wp:iu/row --></section>\n'
            '<!-- /wp:iu/section -->')


TRANSFORMS = {
    'header.html': header,
    'footer.html': footer,
    'front-page.html': front_page,
    'termekek.html': shop_page,
}

if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    manifest = []
    for post_id, post_type, name in ENTRIES:
        original = open(os.path.join(ORIG, name), encoding='utf-8').read()
        new = TRANSFORMS[name](original)
        if new == original:
            print('!! változatlan:', name)
        open(os.path.join(OUT, name), 'w', encoding='utf-8').write(new)
        manifest.append({'id': post_id, 'post_type': post_type, 'file': name,
                         'original_md5': hashlib.md5(original.encode('utf-8')).hexdigest()})
        print(name)
    json.dump(manifest, open(os.path.join(OUT, 'manifest.json'), 'w'), indent=4)
