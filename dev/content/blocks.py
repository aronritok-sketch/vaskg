"""
Blokk-markup építő segédfüggvények a setup/ tartalmak (oldalak) előállításához.

A kimenet "vázlat": az attribútumok pontosak, a statikus blokkok HTML-je
egyszerűsített. A dev/canon.sh a valódi blokkszerkesztőn futtatja át, ami a
kanonikus mentett HTML-t és az iu_style értékeket előállítja.
"""
import json
import html

HOME = '{{home}}'
UPLOADS = HOME + '/wp-content/uploads/'


def attrs(a):
    if not a:
        return ''
    s = json.dumps(a, ensure_ascii=False, separators=(',', ':'))
    s = (s.replace('--', '\\u002d\\u002d').replace('<', '\\u003c').replace('>', '\\u003e')
          .replace('&', '\\u0026').replace('\\"', '\\u0022'))
    return ' ' + s


def block(name, a=None, inner=None, html_open='', html_close=''):
    if inner is None:
        return '<!-- wp:%s%s /-->' % (name, attrs(a))
    return '<!-- wp:%s%s -->\n%s%s%s\n<!-- /wp:%s -->' % (name, attrs(a), html_open, '\n\n'.join(inner), html_close, name)


def clean(a):
    return {k: v for k, v in a.items() if v not in (None, '', False) or k in ('main_query',)}


# ---------- Struktúra ----------

def section(*inner, **a):
    return block('iu/section', clean(a), list(inner), '<section class="iu-section">', '</section>')


def row(*cols, columns=None, **a):
    if columns:
        a['columns'] = columns
    return block('iu/row', clean(a), list(cols), '<div class="iu-row">', '</div>')


def col(*inner, width=None, **a):
    if width and width != '1-1':
        a['width'] = width
    return block('iu/column', clean(a), list(inner), '<div class="iu-column iu-column-%s">' % (width or '1-1'), '</div>')


def group(*inner, **a):
    return block('iu/group', clean(a), list(inner), '<div class="iu-group">', '</div>')


# ---------- Szöveg ----------

def h(text, level=2, **a):
    if level != 2:
        a['level'] = level
    return block('heading', clean(a), [], '<h%d class="wp-block-heading iu-heading">%s' % (level, text), '</h%d>' % level)


def p(text, **a):
    return block('paragraph', clean(a), [], '<p class="iu-text">%s' % text, '</p>')


def ul(*items, **a):
    li = [block('list-item', None, [], '<li class="iu-list-item">%s' % t, '</li>') for t in items]
    return block('list', clean(a), li, '<ul class="wp-block-list iu-list">', '</ul>')


def ol(*items, **a):
    a['ordered'] = True
    li = [block('list-item', None, [], '<li class="iu-list-item">%s' % t, '</li>') for t in items]
    return block('list', clean(a), li, '<ol class="wp-block-list iu-list">', '</ol>')


def buttons(*btns, **a):
    return block('iu/button-group', clean(a), list(btns), '<div class="iu-button-group">', '</div>')


def button(text, url='', type='default'):
    a = {'text': text}
    if type != 'default':
        a['type'] = type
    if url:
        a['linkUrl'] = url
    return block('iu/button', a, [], '<a class="iu-button iu-button-%s"><span class="iu-button-text"><span>%s</span></span>' % (type, html.escape(text)), '</a>')


def image(image_id, file, **a):
    base = file.rsplit('.', 1)
    a2 = {'imageID': image_id, 'imageURL': UPLOADS + file, 'imageThumbURL': UPLOADS + base[0] + '-150x150.' + base[1]}
    a2.update(a)
    return block('iu/image', clean(a2), [], '<div class="iu-image"><div class="iu-image-wrap"><img src="%s"/></div>' % (UPLOADS + file), '</div>')


def card(image_id, file, heading, text, btn='', url=''):
    base = file.rsplit('.', 1)
    a = {'imageID': image_id, 'imageURL': UPLOADS + file, 'imageThumbURL': UPLOADS + base[0] + '-150x150.' + base[1],
         'heading': heading, 'text': text}
    if btn:
        a['button'] = btn
    if url:
        a['linkUrl'] = url
    return block('iu/card', a, [], '<div class="iu-card">', '</div>')


def html_block(content):
    return block('html', None, [], content, '')


def custom(name, **a):
    return block(name, clean(a))


# ---------- Űrlap ----------

def form(form_id, button_label, success, *fields, **a):
    a2 = {'formId': form_id, 'buttonLabel': button_label, 'success': success}
    a2.update(a)
    return block('iu/form', clean(a2), list(fields), '<form class="iu-form"><input type="hidden" name="iu_form_id" value="%s"/>' % form_id, '</form>')


def text_field(name, label, placeholder='', validate=''):
    return block('iu/form-text', clean({'name': name, 'label': label, 'placeholder': placeholder, 'validate': validate}), [], '<div class="iu-form-field iu-form-text">', '</div>')


def textarea_field(name, label, placeholder='', validate=''):
    return block('iu/form-textarea', clean({'name': name, 'label': label, 'placeholder': placeholder, 'validate': validate}), [], '<div class="iu-form-field iu-form-textarea">', '</div>')


def select_field(name, label, options, validate=''):
    return block('iu/form-select', clean({'name': name, 'label': label, 'options': '\n'.join(options), 'validate': validate}), [], '<div class="iu-form-field iu-form-select">', '</div>')


def accept_field():
    return block('iu/form-accept', {
        'name': 'accept',
        'label': 'Elfogadom az <a href="%s/adatkezelesi-tajekoztato/" target="_blank">adatkezelési tájékoztató</a>t.' % HOME,
        'error': 'Fogadja el az adatkezelési tájékoztatót'
    }, [], '<div class="iu-form-field iu-form-accept">', '</div>')


def fields_row(*fields):
    return block('iu/group', {'equal': True}, list(fields), '<div class="iu-group">', '</div>')


REQUIRED_NAME = 'required|Adja meg a nevét'
REQUIRED_PHONE = 'required|Adja meg a telefonszámát'
REQUIRED_EMAIL = 'required|Adja meg az e-mail címét\nemail|Érvénytelen e-mail cím'
