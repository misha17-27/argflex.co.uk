"""Editing an order's lines, and refunding against it."""
import re, os, json, sys, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
ORD  = os.path.join(ROOT, 'storage/orders')
op   = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

SLUG  = 'submersible-fuel-hose-sae-j30-r10'      # £12.70
EXTRA = 'acetylene-hose'
OPT   = 'Inner Diameter: 8mm, Length: 10m'       # £11.00


def get(url):
    try:
        with op.open(BASE + url, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def post(url, fields):
    data = urllib.parse.urlencode(fields, doseq=True, encoding='utf-8').encode()
    try:
        with op.open(urllib.request.Request(BASE + url, data=data, method='POST'), timeout=60) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def token(html):
    m = re.search(r'name="_token" value="([^"]+)"', html)
    return m.group(1) if m else ''


def record(ref):
    return json.load(open(os.path.join(ORD, ref + '.json'), encoding='utf-8'))


FAILS = []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:54} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')


_, html = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk', 'password': 'Str0ngPass!2026'})
for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))

print('AN ORDER TO WORK ON')
body = post('/checkout/', {'cart': json.dumps([{"slug": SLUG, "option": "", "qty": 10}]),
                           'name': 'Rita Cheng', 'company': '', 'email': 'rita@example.com',
                           'phone': '07000 000111', 'address': '1 Test Road', 'city': 'London',
                           'postcode': 'E18 1AN', 'country': 'GB', 'notes': '',
                           'payment': 'proforma', 'website': ''})[1]
m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
check('placed', m is not None)
REF = m.group(1)
o = record(REF)['order']
# This hose carries no weight — it is one of the two purchasable items that
# do not — so the basket weighs nothing, falls in the under-five-metre band
# and is offered the £4.20 rate first. VAT is on the goods alone: delivery
# is not taxed here, exactly as it is not on the live site.
check('£127.00 of goods, £4.20 delivery, £25.40 VAT on the goods only, £156.60 total',
      (o['subtotal'], o['shipping'], o['vat'], o['total']) == (12700, 420, 2540, 15660),
      str((o['subtotal'], o['shipping'], o['vat'], o['total'])))

print('\nEDITING THE LINES')
_, html = get('/admin/orders/' + REF)
check('the screen offers editing', 'name="line[0][qty]"' in html and 'name="add_slug"' in html
      and 'name="shipping"' in html)

post('/admin/orders/' + REF, {'_token': token(html), 'relines': '1',
                              'line[0][qty]': '4', 'shipping': '12.00'})
o = record(REF)['order']
check('quantity changed', o['items'][0]['qty'] == 4, str(o['items'][0]['qty']))
check('the line follows', o['items'][0]['line'] == 5080, str(o['items'][0]['line']))
check('the totals were worked out again',
      (o['subtotal'], o['vat'], o['total']) == (5080, 1256, 7536),
      str((o['subtotal'], o['vat'], o['total'])))
check('the unit price it was sold at is kept', o['items'][0]['price'] == 1270)

_, html = get('/admin/orders/' + REF)
post('/admin/orders/' + REF, {'_token': token(html), 'relines': '1', 'line[0][qty]': '4',
                              'shipping': '12.00', 'add_slug': EXTRA, 'add_option': OPT,
                              'add_qty': '2'})
o = record(REF)['order']
check('a line was added', len(o['items']) == 2, str(len(o['items'])))
check('at today\'s price', o['items'][1]['price'] == 1100, str(o['items'][1]['price']))
check('totals include it', o['subtotal'] == 5080 + 2200, str(o['subtotal']))

_, html = get('/admin/orders/' + REF)
post('/admin/orders/' + REF, {'_token': token(html), 'relines': '1',
                              'line[0][qty]': '4', 'line[0][remove]': 'on',
                              'line[1][qty]': '2', 'shipping': '0'})
o = record(REF)['order']
check('a line can be dropped', len(o['items']) == 1 and o['items'][0]['slug'] == EXTRA)
check('and delivery zeroed', o['shipping'] == 0)
check('totals follow', (o['subtotal'], o['vat'], o['total']) == (2200, 440, 2640),
      str((o['subtotal'], o['vat'], o['total'])))

_, html = get('/admin/orders/' + REF)
_, body = post('/admin/orders/' + REF, {'_token': token(html), 'relines': '1',
                                        'line[0][qty]': '2', 'line[0][remove]': 'on', 'shipping': '0'})
check('an order cannot be emptied', 'needs at least one line' in body
      or len(record(REF)['order']['items']) == 1)

print('\nTHE TAX RATE IS THE ONE IT WAS PLACED ON')
rec = record(REF)
rec['order']['tax_rate'] = 5
json.dump(rec, open(os.path.join(ORD, REF + '.json'), 'w', encoding='utf-8'), indent=2)
_, html = get('/admin/orders/' + REF)
post('/admin/orders/' + REF, {'_token': token(html), 'relines': '1', 'line[0][qty]': '2', 'shipping': '0'})
o = record(REF)['order']
check('recalculated at the order\'s own rate, not the shop\'s', o['vat'] == 110, str(o['vat']))
rec = record(REF); rec['order']['tax_rate'] = 20
json.dump(rec, open(os.path.join(ORD, REF + '.json'), 'w', encoding='utf-8'), indent=2)
_, html = get('/admin/orders/' + REF)
post('/admin/orders/' + REF, {'_token': token(html), 'relines': '1', 'line[0][qty]': '2', 'shipping': '0'})
check('back to 20%', record(REF)['order']['vat'] == 440, str(record(REF)['order']['vat']))

print('\nREFUNDS')
total = record(REF)['order']['total']
_, html = get('/admin/orders/' + REF)
check('a refund panel is offered', 'name="refund_amount"' in html and 'name="refund_reason"' in html)

post('/admin/orders/' + REF, {'_token': token(html), 'refund': '1',
                              'refund_amount': '5.00', 'refund_reason': 'Two metres short'})
rec = record(REF)
check('the refund is recorded', len(rec.get('refunds', [])) == 1
      and rec['refunds'][0]['amount'] == 500, str(rec.get('refunds')))
check('with its reason', rec['refunds'][0]['reason'] == 'Two metres short')
check('and who did it', rec['refunds'][0]['by'] == 'admin@argflex.co.uk', rec['refunds'][0].get('by', ''))
check('the order is not yet refunded in full', rec['status'] != 'refunded', rec['status'])

_, html = get('/admin/orders/' + REF)
check('the screen shows what is still owed',
      'Still owed' in html and 'Refunded' in html)

_, html = get('/admin/orders/' + REF)
_, body = post('/admin/orders/' + REF, {'_token': token(html), 'refund': '1',
                                        'refund_amount': '9999.00', 'refund_reason': 'too much'})
check('more than is owed is refused', 'more than is still owed' in body
      or len(record(REF).get('refunds', [])) == 1)

_, html = get('/admin/orders/' + REF)
post('/admin/orders/' + REF, {'_token': token(html), 'refund': '1',
                              'refund_amount': '0', 'refund_reason': 'nothing'})
check('a zero refund is refused', len(record(REF).get('refunds', [])) == 1)

owed = total - 500
_, html = get('/admin/orders/' + REF)
post('/admin/orders/' + REF, {'_token': token(html), 'refund': '1',
                              'refund_amount': f'{owed / 100:.2f}', 'refund_reason': 'Returned'})
rec = record(REF)
check('refunding the rest closes it', rec['status'] == 'refunded', rec['status'])
check('two refunds on file', len(rec['refunds']) == 2)
_, html = get('/admin/orders/' + REF)
check('and the panel stops offering more', 'Fully refunded' in html)

print('\nWHAT THE REST OF THE ADMIN MAKES OF IT')
_, html = get('/admin/orders')
check('Refunded is a status it knows', 'Refunded' in html)
_, html = get('/admin/reports?range=30')
check('reports still render', 'Revenue' in html or 'Nothing in' in html)

print('\nTIDY UP')
for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))
check('orders removed', not [f for f in os.listdir(ORD) if f.endswith('.json')])

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
sys.exit(1 if FAILS else 0)
