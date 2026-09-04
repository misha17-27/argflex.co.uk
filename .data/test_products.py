"""Sale prices and stock control, from the editor through to a placed order."""
import re, os, json, datetime, urllib.request, urllib.parse, urllib.error, http.cookiejar, sys

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
ORD  = os.path.join(ROOT, 'storage/orders')
op   = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

# Every public form now carries a token signed for its own action, so these
# suites have to carry one too — see .data/formtoken.py. Scraped from the real
# page, never computed here: a test that signed its own tokens would keep
# passing if the server stopped checking them.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from formtoken import Tokens
TOK = Tokens(op, BASE)

# The suites hammer the same forms from one address, so they trip the counters
# the shop uses against brute force and spam. Clearing them at the start keeps
# a run from failing on its own previous run — never do this on a live server,
# which is why every one of these files says local-only at the top.
try:
    os.remove(os.path.join(ROOT, 'storage', 'rate-limits.json'))
except OSError:
    pass


SLUG = 'submersible-fuel-hose-sae-j30-r10'      # simple, £12.70
VAR  = 'acetylene-hose'                          # variable, 7 options

def get(url):
    try:
        with op.open(BASE + url, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')

def post(url, fields):
    if '_form' not in fields and not url.startswith('/admin'):
        fields = {**fields, '_form': TOK.sign(url, fields)}
    data = urllib.parse.urlencode(fields, doseq=True, encoding='utf-8').encode()
    try:
        with op.open(urllib.request.Request(BASE + url, data=data, method='POST'), timeout=60) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')

def token(html):
    m = re.search(r'name="_token" value="([^"]+)"', html)
    return m.group(1) if m else ''

def form_fields(html):
    """Read a product form back so a test can change one field and repost.

    Anything named name[] holds several values at once — categories, images,
    upsells. Collapsing those to one value silently drops the rest, which
    once cost a product half its categories, so they are kept as lists.
    """
    fields = {}

    def add(name, value):
        if name.endswith('[]'):
            fields.setdefault(name, []).append(value)
        else:
            fields.setdefault(name, value)

    for m in re.finditer(r'<input\b[^>]*>', html):
        tag = m.group(0)
        name = re.search(r'name="([^"]+)"', tag)
        if not name or name.group(1) == '_token': continue
        kind = (re.search(r'type="([^"]+)"', tag) or [None, 'text'])[1]
        val  = re.search(r'value="([^"]*)"', tag)
        if kind in ('checkbox', 'radio'):
            if 'checked' in tag:
                add(name.group(1), val.group(1) if val else 'on')
        else:
            add(name.group(1), val.group(1) if val else '')

    for m in re.finditer(r'<textarea\b[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>', html, re.S):
        fields[m.group(1)] = m.group(2)

    for m in re.finditer(r'<select\b[^>]*name="([^"]+)"[^>]*>(.*?)</select>', html, re.S):
        picked = re.findall(r'<option value="([^"]*)"[^>]*selected', m.group(2))
        if not picked: continue
        fields[m.group(1)] = picked if m.group(1).endswith('[]') else picked[0]

    return fields


def unescape(fields):
    """Form values come back HTML-escaped; repost them as they were typed."""
    import html as H
    return {k: ([H.unescape(x) for x in v] if isinstance(v, list) else H.unescape(v))
            for k, v in fields.items()}

PHP = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))


def snapshot(action):
    """Put the catalogue back exactly as it was.

    Reposting a product form re-escapes every description, so a run that
    saves fifteen times leaves the copy buried under layers of &amp;.
    Tidying by hand missed that; a file snapshot cannot.
    """
    import subprocess
    out = subprocess.run([PHP, '.data/catalogue_snapshot.php', action],
                         cwd=ROOT, capture_output=True, text=True)
    return (out.stdout or out.stderr).strip()


FAILS, MADE = [], []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:54} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')

def save_product(slug, changes, drop=()):
    _, html = get('/admin/products/' + slug)
    f = unescape(form_fields(html))
    f.update(changes)
    for k in drop: f.pop(k, None)
    f['_token'] = token(html)
    return post('/admin/products/' + slug, f)

_, html = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk', 'password': 'Str0ngPass!2026'})
for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))
print('SETUP')
print('  catalogue snapshot: ' + snapshot('save'))

print('\nTHE EDITOR')
code, html = get('/admin/products/' + SLUG)
check('the pricing card offers a sale price', code == 200 and 'name="sale_price"' in html
      and 'name="sale_from"' in html and 'name="sale_to"' in html)
check('an inventory card', 'name="manage_stock"' in html and 'name="backorders"' in html
      and 'name="sold_individually"' in html)
check('a shipping card', 'name="weight"' in html and 'name="shipping_class"' in html
      and 'name="height"' in html)
check('linked products', 'name="upsells[]"' in html and 'name="crosssells[]"' in html)
check('advanced fields', 'name="purchase_note"' in html and 'name="menu_order"' in html)
_, vhtml = get('/admin/products/' + VAR)
check('each option has its own sale price', 'variant[0][sale]' in vhtml)

print('\nA SALE ON A SIMPLE PRODUCT')
save_product(SLUG, {'sale_price': '9.99', 'sale_from': '', 'sale_to': ''})
_, page = get('/product/' + SLUG + '/')
check('the sale price shows', '£9.99' in page)
check('the old price is struck through', '<s>£12.70</s>' in page)
check('a saving is shown', '21% off' in page, re.search(r'(\d+)% off', page).group(0) if '% off' in page else '')
_, shop = get('/shop/')
check('the card shows both prices', '<s>£12.70</s>' in shop and '£9.99' in shop)
check('a sale flash on the card', 'flash-sale' in shop)
check('add-to-cart carries the sale price', 'data-price="999"' in shop)

print('\nTHE SALE SCHEDULE')
tomorrow = (datetime.date.today() + datetime.timedelta(days=1)).isoformat()
save_product(SLUG, {'sale_price': '9.99', 'sale_from': tomorrow})
_, page = get('/product/' + SLUG + '/')
check('a sale that has not started is ignored', '£12.70' in page and '£9.99' not in page)

yesterday = (datetime.date.today() - datetime.timedelta(days=1)).isoformat()
save_product(SLUG, {'sale_price': '9.99', 'sale_from': '', 'sale_to': yesterday})
_, page = get('/product/' + SLUG + '/')
check('a finished sale is ignored', '£12.70' in page and '£9.99' not in page)

save_product(SLUG, {'sale_price': '9.99', 'sale_from': yesterday, 'sale_to': tomorrow})
_, page = get('/product/' + SLUG + '/')
check('a sale inside its dates runs', '£9.99' in page)

save_product(SLUG, {'sale_price': '20.00', 'sale_from': '', 'sale_to': ''})
_, page = get('/product/' + SLUG + '/')
check('a "sale" above the regular price is refused', '£12.70' in page and '£20.00' not in page)

print('\nTHE SERVER CHARGES THE SALE PRICE')
save_product(SLUG, {'sale_price': '9.99', 'sale_from': '', 'sale_to': ''})
cart = json.dumps([{"slug": SLUG, "option": "", "qty": 10}])
_, body = post('/checkout/', {'cart': cart, 'name': 'Rita Cheng', 'company': '', 'email': 'rita@example.com',
                              'phone': '07000 000111', 'address': '1 Test Road', 'city': 'London',
                              'postcode': 'E18 1AN', 'country': 'GB', 'notes': '',
                              'payment': 'proforma', 'website': ''})
m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
check('order placed', m is not None)
if m:
    MADE.append(m.group(1))
    o = json.load(open(os.path.join(ORD, m.group(1) + '.json'), encoding='utf-8'))['order']
    check('priced at the sale price, not the regular one', o['subtotal'] == 9990, str(o['subtotal']))

print('\nSTOCK')
save_product(SLUG, {'sale_price': '', 'manage_stock': 'on', 'stock_qty': '4',
                    'low_stock': '5', 'backorders': 'no'})
_, page = get('/product/' + SLUG + '/')
check('a quantity is shown when stock is low', '4 in stock' in page)
_, shop = get('/shop/')
check('the card flags low stock', 'flash-low' in shop)
check('the button caps the quantity', 'data-max="4"' in shop)

_, html = get('/admin/settings/products')
prod = {'_token': token(html), 'default_sort': 'default', 'shop_notice': '',
        'enable_wishlist': 'on', 'enable_compare': 'on', 'low_stock_qty': '2',
        'stock_display': 'never', 'weight_unit': 'kg', 'dimension_unit': 'cm'}
post('/admin/settings/products', prod)
_, page = get('/product/' + SLUG + '/')
check('"never show a number" is respected', 'In stock' in page and '4 in stock' not in page)
post('/admin/settings/products', {**prod, '_token': token(get('/admin/settings/products')[1]),
                                  'stock_display': 'always'})
check('"always" shows it again', '4 in stock' in get('/product/' + SLUG + '/')[1])

cart = json.dumps([{"slug": SLUG, "option": "", "qty": 25}])
_, body = post('/checkout/', {'cart': cart, 'name': 'Rita Cheng', 'company': '', 'email': 'rita@example.com',
                              'phone': '07000 000111', 'address': '1 Test Road', 'city': 'London',
                              'postcode': 'E18 1AN', 'country': 'GB', 'notes': '',
                              'payment': 'proforma', 'website': ''})
m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
if m:
    MADE.append(m.group(1))
    o = json.load(open(os.path.join(ORD, m.group(1) + '.json'), encoding='utf-8'))['order']
    check('the server caps an order at what is in stock', o['items'][0]['qty'] == 4, str(o['items'][0]['qty']))
else:
    check('the server caps an order at what is in stock', False, 'no order placed')

save_product(SLUG, {'manage_stock': 'on', 'stock_qty': '0', 'backorders': 'no'})
_, page = get('/product/' + SLUG + '/')
check('nothing left means out of stock', 'Out of stock' in page or 'Ask about availability' in page)
_, body = post('/checkout/', {'cart': json.dumps([{"slug": SLUG, "option": "", "qty": 1}]),
                              'name': 'Rita', 'company': '', 'email': 'rita@example.com',
                              'phone': '07000 000111', 'address': '1 Test Road', 'city': 'London',
                              'postcode': 'E18 1AN', 'country': 'GB', 'notes': '',
                              'payment': 'proforma', 'website': ''})
check('and cannot be ordered', 'basket is empty' in body)

save_product(SLUG, {'manage_stock': 'on', 'stock_qty': '0', 'backorders': 'notify'})
check('backorders bring it back', 'Available on backorder' in get('/product/' + SLUG + '/')[1])

save_product(SLUG, {'manage_stock': 'on', 'stock_qty': '9', 'backorders': 'no',
                    'sold_individually': 'on'})
_, page = get('/product/' + SLUG + '/')
# Case-insensitive since it moved: it used to be the tail of "In stock · one
# per order" inside the availability line, and that line is now rewritten by
# the browser as the option changes, so the rule stands beside it instead.
check('sold individually is stated', 'one per order' in page.lower())
_, shop = get('/shop/')
check('and caps the button at one', 'data-max="1"' in shop)

print('\nHIDING WHAT IS OUT OF STOCK')
save_product(SLUG, {'stock': 'outofstock'}, drop=('manage_stock', 'sold_individually'))
check('the out-of-stock flag saved', 'Out of stock' in get('/product/' + SLUG + '/')[1])
def on_shop():
    return get('/shop/')[1].count('href="/product/' + SLUG + '/"')
check('still listed by default', on_shop() > 0, str(on_shop()) + ' links')
_, html = get('/admin/settings/products')
post('/admin/settings/products', {**prod, '_token': token(html), 'hide_out_of_stock': 'on'})
check('hidden once the setting is on', on_shop() == 0, str(on_shop()) + ' links')
# The filter used to live inside the default ordering, so pressing any of the
# four sort buttons brought every hidden product straight back.
def on_shop_sorted(how):
    return get('/shop/?sort=' + how)[1].count('href="/product/' + SLUG + '/"')
for how in ('price-asc', 'price-desc', 'name', 'new'):
    check(f'  and stays hidden when sorted by {how}', on_shop_sorted(how) == 0,
          str(on_shop_sorted(how)) + ' links')
check('but its own page still works', get('/product/' + SLUG + '/')[0] == 200)
_, html = get('/admin/settings/products')
post('/admin/settings/products', {**prod, '_token': token(html)})

print('\nOTHER FIELDS')
save_product(SLUG, {'stock': 'instock', 'weight': '2.5', 'length': '30', 'width': '30',
                    'height': '18', 'shipping_class': 'Bulky', 'menu_order': '-5',
                    'purchase_note': 'Cut lengths ship on a pallet.'})
_, page = get('/product/' + SLUG + '/')
check('weight on the page', '2.5 kg' in page)
check('dimensions on the page', '30 × 30 × 18 cm' in page)
check('shipping class on the page', 'Bulky' in page)
_, shop = get('/shop/')
first = re.search(r'<article class="card"[^>]*data-name="([^"]+)"', shop)
check('catalogue position moves it first', first and 'submersible' in first.group(1),
      first.group(1) if first else '')

print('\nSORTING')
_, html = get('/admin/settings/products')
post('/admin/settings/products', {**prod, '_token': token(html), 'default_sort': 'price-asc'})
_, shop = get('/shop/')
prices = [int(n) for n in re.findall(r'data-price="(\d+)"', shop) if int(n) > 0]
check('default sorting by price is applied',
      len(prices) > 3 and prices == sorted(prices), ','.join(map(str, prices[:6])))
_, html = get('/admin/settings/products')
post('/admin/settings/products', {**prod, '_token': token(html), 'default_sort': 'default'})

print('\nTHE SHORT DESCRIPTION, HOWEVER IT WAS TYPED')

# It is edited in a contenteditable box now, and pressing Enter there makes a
# <div> rather than a <br>. The parser split on <br> and </p> alone, so every
# fact ended up on one line and only the first was recognised.
check('the editor is a rich one, like the full description',
      'name="short" rows="8" data-rich' in get('/admin/products/' + SLUG)[1])

for shape, html in [
    ('breaks', '<p><strong>Tube</strong>: Rubber.<br /><strong>Cover</strong>: Red.</p>'),
    ('divs',   '<div><strong>Tube</strong>: Rubber.</div><div><strong>Cover</strong>: Red.</div>'),
    ('a list', '<ul><li><strong>Tube</strong>: Rubber.</li><li><strong>Cover</strong>: Red.</li></ul>'),
]:
    save_product(SLUG, {'short': html})
    _, page = get('/product/' + SLUG + '/')
    check('  ' + shape + ' give two facts, not one',
          page.count('<p><b>') == 2, str(page.count('<p><b>')))

print('\nWHAT A VARIATION KNOWS THAT THE FORM DOES NOT ASK')

# The editor shows a variation's label, price and sale price. It does not show
# the metre count that decides carriage, the stock state, the ceiling, the
# shipping class or the WooCommerce id — and rebuilding the row from the
# posted fields alone threw all of them away. That reset every length to
# weight 0, which is the under-five-metre band, and put two sold-out
# fifty-metre coils back on sale.
import subprocess

PHP = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))


def variants_of(slug):
    out = subprocess.run(
        [PHP, '-r', 'require "inc/config.php"; $p = find_product("' + slug + '"); '
                    'echo json_encode($p["variants"]);'],
        cwd=ROOT, capture_output=True, text=True)
    return {v['key']: v for v in json.loads(out.stdout or '[]')}


FUEL = 'fuel-hose-din-73379-b'
before = variants_of(FUEL)
check('the hose has its eighteen variations', len(before) == 18, str(len(before)))
check('  one of them is out of stock', before['3-2mm|50m']['stock'] == 'outofstock')
check('  one has a ceiling of ten', before['3-2mm|1m']['stock_qty'] == 10)
check('  a fifty-metre length weighs fifty', before['3-2mm|50m']['weight'] == 50)

save_product(FUEL, {})              # saved untouched, the way an editor would

# The slug is an indexed URL. A save that quietly renames one is the worst
# thing this editor can do, and it did: a loop variable inside the attribute
# merge was called $slug, so the product took the name of whichever value
# was processed last and became /product/50m/.
check('the product still has its own address',
      get('/admin/products/' + FUEL)[0] == 200)

after = variants_of(FUEL)
check('every variation survives the save', len(after) == len(before), str(len(after)))
check('  the metre counts are intact',
      [v['weight'] for v in after.values()] == [v['weight'] for v in before.values()])
check('  the out-of-stock one is still out', after['3-2mm|50m']['stock'] == 'outofstock')
check('  the ceiling is still ten', after['3-2mm|1m']['stock_qty'] == 10)
check('  the WooCommerce ids are kept', after['3-2mm|1m']['id'] == before['3-2mm|1m']['id'])
check('  the attribute map is kept', after['3-2mm|1m']['attrs'] == before['3-2mm|1m']['attrs'])
check('  and the rows did not jump about', list(after.keys()) == list(before.keys()))

OXY = 'oxygen-hose-agoma'
was_oxy = variants_of(OXY)
save_product(OXY, {})
check('a variation keeps its shipping class',
      variants_of(OXY)['6-3mm|1m']['shipping_class'] == was_oxy['6-3mm|1m']['shipping_class'] == '1m')

print('\nA VARIATION HAS ITS OWN STOCK, AND SELLING IT COUNTS DOWN')

# Nothing used to reduce stock when an order was placed — for a product or
# for an option. A shop that counts what is left has to actually count.
FUEL2 = 'fuel-hose-din-73379-b'
_, editor = get('/admin/products/' + FUEL2)
check('the editor opens a variation onto its own fields',
      'data-var-toggle' in editor and 'variant[0][stock_qty]' in editor
      and 'variant[0][image]' in editor and 'variant[0][weight]' in editor)

start = variants_of(FUEL2)['3-2mm|1m']
check('it starts with ten counted', start['manage_stock'] and start['stock_qty'] == 10,
      str(start['stock_qty']))

def buy(option, qty):
    body = post('/checkout/', {'cart': json.dumps([{"slug": FUEL2, "option": option, "qty": qty}]),
                               'name': 'Rita Cheng', 'company': '', 'email': 'rita@example.com',
                               'phone': '07000 000111', 'address': '1 Test Road', 'city': 'London',
                               'postcode': 'E18 1AN', 'country': 'GB', 'notes': '',
                               'payment': 'proforma', 'website': ''})[1]
    m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
    if m: MADE.append(m.group(1))
    return body

buy('3-2mm|1m', 4)
check('  four sold leaves six', variants_of(FUEL2)['3-2mm|1m']['stock_qty'] == 6,
      str(variants_of(FUEL2)['3-2mm|1m']['stock_qty']))
check('  and the option beside it is untouched',
      variants_of(FUEL2)['4mm|1m']['stock_qty'] == start.get('stock_qty', 0) * 0)

buy('3-2mm|1m', 6)
check('  the last six leave none', variants_of(FUEL2)['3-2mm|1m']['stock_qty'] == 0)
check('  and it can no longer be ordered', 'basket is empty' in buy('3-2mm|1m', 1))
check('  while its siblings still can', 'basket is empty' not in buy('4mm|1m', 1))

# A variation can carry its own photo.
save_product(FUEL2, {'variant[0][image]': 'assets/img/products/acetylene-hose.webp'})
check('a variation keeps a picture of its own',
      variants_of(FUEL2)['3-2mm|1m']['image'] == 'assets/img/products/acetylene-hose.webp',
      variants_of(FUEL2)['3-2mm|1m'].get('image', ''))
_, page = get('/product/' + FUEL2 + '/')
check('  and the page hands it to the browser', 'acetylene-hose.webp' in page)

save_product(FUEL2, {'variant[0][image]': 'assets/img/products/../../../etc/passwd'})
check('  a path outside the image folder is refused',
      variants_of(FUEL2)['3-2mm|1m']['image'] == '',
      variants_of(FUEL2)['3-2mm|1m'].get('image', ''))

print('\nAVAILABILITY COMES FROM THE OPTIONS, NOT FROM THE PRODUCT')

# The shop had two answers to one question — the product's own In stock /
# Out of stock switch, and each option's Availability — and it believed the
# product. Every length of a hose could be marked Out of stock and the page,
# the card and the popup all went on saying In stock.

_, editor = get('/admin/products/' + VAR)
check('a variable product has no stock switch of its own',
      'name="stock"' not in editor and 'Taken from the options' in editor)
check('  and says what the shop is saying instead', 'stock-read' in editor)
_, simple = get('/admin/products/' + SLUG)
check('a product with no options keeps its switch', 'name="stock"' in simple)

opts = list(variants_of(VAR))
for i in range(len(opts)):
    save_product(VAR, {f'variant[{i}][stock]': 'outofstock'})
_, page = get('/product/' + VAR + '/')
check('every option sold out puts the product out of stock',
      'Ask about availability' in page and 'data-add-to-cart' not in page)
_, shop = get('/product-category/gas/')
check('  the card says so too', 'Ask when it is back' in shop)
qv = json.loads(get('/quick-view.php?slug=' + VAR)[1])
check('  and the popup agrees', qv['stock']['label'] == 'Out of stock' and not qv['inStock'],
      qv['stock']['label'])

save_product(VAR, {'variant[1][stock]': 'instock'})
_, page = get('/product/' + VAR + '/')
check('one option back on the shelf brings the product back',
      'data-add-to-cart' in page and 'Ask about availability' not in page)
check('  the page hands each option its own availability to the browser',
      '&quot;buy&quot;:false' in page and '&quot;buy&quot;:true' in page)
qv = json.loads(get('/quick-view.php?slug=' + VAR)[1])
check('  the popup carries it as well',
      [v['buy'] for v in qv['variants'].values()].count(True) == 1,
      str([v['buy'] for v in qv['variants'].values()]))
check('  and the parent counts nothing of its own', qv['max'] == 0, str(qv['max']))

# The switch is gone from the form, so nothing is posted for it. A save that
# read the absent field as "in stock" would quietly rewrite the stored flag.
flag = subprocess.run([PHP, '-r', 'require "inc/config.php"; '
                       'echo find_product("' + VAR + '")["stock"];'],
                      cwd=ROOT, capture_output=True, text=True).stdout
save_product(VAR, {'menu_order': '0'})
after = subprocess.run([PHP, '-r', 'require "inc/config.php"; '
                        'echo find_product("' + VAR + '")["stock"];'],
                       cwd=ROOT, capture_output=True, text=True).stdout
check('saving does not invent a value for the switch that is gone',
      after == flag, f'{flag!r} -> {after!r}')

for i in range(len(opts)):
    save_product(VAR, {f'variant[{i}][stock]': 'instock'})

# A basket lives in the customer's browser and can outlast the shelf. The
# server refuses what it cannot sell; the page has to say so and leave it out
# of the money, rather than showing the line at full price beside a total that
# no longer includes it.
save_product(VAR, {'variant[0][stock]': 'outofstock'})
sold, live = opts[0], opts[1]
def quote(lines):
    body = json.dumps({'cart': lines, 'country': 'GB'}).encode()
    req = urllib.request.Request(BASE + '/delivery-quote.php', data=body, method='POST',
                                 headers={'Content-Type': 'application/json'})
    with op.open(req, timeout=40) as r:
        return json.loads(r.read().decode())

both = quote([{'slug': VAR, 'option': sold, 'qty': 1},
              {'slug': VAR, 'option': live, 'qty': 2}])
check('the quote refuses a sold-out line', both['count'] == 1, str(both['count']))
check('  and names the one it did price, with the quantity it priced',
      both['priced'] == [{'key': VAR + '|' + live, 'qty': 2}], str(both['priced']))
only = quote([{'slug': VAR, 'option': live, 'qty': 2}])
check('  the goods figure is the sellable line alone',
      both['subtotal'] == only['subtotal'], f"{both['subtotal']} vs {only['subtotal']}")
check('  and the VAT beside it agrees with it', both['vat'] == only['vat'],
      f"{both['vat']} vs {only['vat']}")

# And the order itself is refused rather than quietly placed short.
before = len([f for f in os.listdir(ORD) if f.endswith('.json')])
body = post('/checkout/', {'cart': json.dumps([{"slug": VAR, "option": sold, "qty": 1},
                                               {"slug": VAR, "option": live, "qty": 1}]),
                           'name': 'Rita Cheng', 'company': '', 'email': 'rita@example.com',
                           'phone': '07000 000111', 'address': '1 Test Road', 'city': 'London',
                           'postcode': 'E18 1AN', 'country': 'GB', 'notes': '',
                           'payment': 'proforma', 'website': ''})[1]
check('an order short of a sold-out line is refused, not placed short',
      'no longer available' in body
      and len([f for f in os.listdir(ORD) if f.endswith('.json')]) == before)
save_product(VAR, {'variant[0][stock]': 'instock'})

# A counted option: asking for more than there is comes back with what the
# shop will actually send, so the basket page can write itself down to it
# instead of showing a quantity nobody will be charged for.
save_product(VAR, {'variant[1][manage_stock]': '1', 'variant[1][stock_qty]': '3',
                   'backorders': 'no'})
capped = quote([{'slug': VAR, 'option': live, 'qty': 99}])
check('the quote says how many of a counted line it priced',
      capped['priced'] == [{'key': VAR + '|' + live, 'qty': 3}], str(capped['priced']))
save_product(VAR, {'variant[1][stock_qty]': '0'}, drop=('variant[1][manage_stock]',))

# Marking one from the list has to reach the options too, or the row changes
# and the shop does not.
_, list_html = get('/admin/products')
post('/admin/products', {'_token': token(list_html), 'bulk': 'outofstock', 'slugs[]': [VAR]})
check('a bulk mark reaches every option',
      all(v['stock'] == 'outofstock' for v in variants_of(VAR).values()),
      str([v['stock'] for v in variants_of(VAR).values()]))
check('  and the list says out of stock',
      'Out of stock' in get('/admin/products?stock=outofstock&q=acetylene')[1])
_, list_html = get('/admin/products')
post('/admin/products', {'_token': token(list_html), 'bulk': 'instock', 'slugs[]': [VAR]})
check('  and back again', all(v['stock'] == 'instock' for v in variants_of(VAR).values()))

# An unticked checkbox posts nothing at all, which is why this one could be
# turned on and never off again: the save carried the old value forward.
save_product(VAR, {'variant[0][manage_stock]': '1', 'variant[0][stock_qty]': '5'})
check('an option can start counting', variants_of(VAR)[opts[0]]['manage_stock'] is True)
save_product(VAR, {}, drop=('variant[0][manage_stock]',))
check('  and can stop again', variants_of(VAR)[opts[0]]['manage_stock'] is False,
      str(variants_of(VAR)[opts[0]]['manage_stock']))

print('\nA DELIVERY PRICE OF ITS OWN')

# A model that costs more to send than its length suggests says so on itself,
# not in a rules file nobody can edit without opening it.
ACET = 'acetylene-hose'
_, ed = get('/admin/products/' + ACET)
check('the product has a Shipping section with the four bands',
      'name="delivery[11]"' in ed and 'name="delivery[16]"' in ed)
check('and so does each of its options',
      'name="variant[0][delivery][11]"' in ed and 'name="variant[0][delivery][16]"' in ed)


def band_costs(slug, option):
    out = subprocess.run(
        [PHP, '-r', 'require "inc/config.php"; $i = price_basket_lines(['
                    '["slug"=>"' + slug + '","option"=>"' + option + '","qty"=>1]]); '
                    '$p = shipping_packages($i, "GB"); '
                    'echo json_encode(array_column($p[0]["rates"], "cost"));'],
        cwd=ROOT, capture_output=True, text=True)
    return json.loads(out.stdout or '[]')


common = band_costs(ACET, '8mm|1m')
save_product(ACET, {'variant[0][delivery][11]': '2.50', 'variant[0][delivery][14]': '1.50'})
check('an option can name its own price', band_costs(ACET, '8mm|1m') == [250, 150],
      str(band_costs(ACET, '8mm|1m')))
check('  and its siblings keep the shop price',
      band_costs(ACET, '8mm|5m') == common, str(band_costs(ACET, '8mm|5m')))

save_product(ACET, {'variant[0][delivery][11]': '', 'variant[0][delivery][14]': ''})
check('  clearing it goes back to the shop price',
      band_costs(ACET, '8mm|1m') == common, str(band_costs(ACET, '8mm|1m')))

save_product(ACET, {'delivery[11]': '9.99'})
check('the product can name one for every option that has none',
      band_costs(ACET, '8mm|1m')[0] == 999, str(band_costs(ACET, '8mm|1m')))
save_product(ACET, {'delivery[11]': ''})

print('\nTIDY UP')
print('  ' + snapshot('restore'))
for r in MADE:
    p = os.path.join(ORD, r + '.json')
    if os.path.exists(p): os.remove(p)
if os.path.exists(os.path.join(ROOT, 'storage/settings.php')):
    os.remove(os.path.join(ROOT, 'storage/settings.php'))
_, page = get('/product/' + SLUG + '/')
check('the product is back to normal', '£12.70' in page and '<s>' not in page)
check('site fine on the defaults', get('/')[0] == 200)

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
