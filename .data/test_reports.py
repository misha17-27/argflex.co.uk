"""Reports: figures, chart, rankings and the CSV, against orders we place."""
import re, os, json, csv, io, datetime, urllib.request, urllib.parse, urllib.error, http.cookiejar, sys

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

FAILS, MADE = [], []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:52} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')

def place(email, name, qty, slug='submersible-fuel-hose-sae-j30-r10', option=''):
    body = post('/checkout/', {'cart': json.dumps([{"slug": slug, "option": option, "qty": qty}]),
                               'name': name, 'company': '', 'email': email, 'phone': '07000 000111',
                               'address': '1 Test Road', 'city': 'London', 'postcode': 'E18 1AN',
                               'country': 'GB', 'notes': '', 'payment': 'proforma', 'website': ''})[1]
    m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
    if m: MADE.append(m.group(1))
    return m.group(1) if m else None

def backdate(ref, days):
    """Move an order back in time so the ranges can be exercised."""
    path = os.path.join(ORD, ref + '.json')
    rec  = json.load(open(path, encoding='utf-8'))
    when = datetime.datetime.now(datetime.timezone.utc) - datetime.timedelta(days=days)
    rec['placed_at'] = when.strftime('%Y-%m-%dT%H:%M:%S+00:00')
    json.dump(rec, open(path, 'w', encoding='utf-8'), indent=2)

_, html = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk', 'password': 'Str0ngPass!2026'})

for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))

print('EMPTY')
code, html = get('/admin/reports')
check('screen renders with no orders', code == 200 and 'Nothing in last 30 days' in html)
check('range tabs offered', html.count('/admin/reports?range=') == 5)

print('\nWITH ORDERS')
r1 = place('rita@example.com', 'Rita Cheng', 10)                       # £127.00 net today
r2 = place('omar@example.com', 'Omar Silva', 4)                        # £50.80 net today
r3 = place('rita@example.com', 'Rita Cheng', 2)                        # £25.40, backdated
r4 = place('sam@example.com',  'Sam Doyle',  1, 'acetylene-hose', 'Inner Diameter: 8mm, Length: 10m')
check('four orders placed', all([r1, r2, r3, r4]), ', '.join(MADE))
backdate(r3, 200)                                                       # outside 90 days, inside a year

code, html = get('/admin/reports?range=30')
check('30-day view renders', code == 200 and 'Revenue per day' in html)
orders = re.search(r'<span>(\d+)</span>Orders', html)
check('three orders in the last 30 days', orders and orders.group(1) == '3', orders.group(1) if orders else '')
custs = re.search(r'<span>(\d+)</span>Customer', html)
check('three customers', custs and custs.group(1) == '3', custs.group(1) if custs else '')
check('a chart was drawn', '<svg class="chart"' in html and '<rect' in html)
check('bars carry a tooltip', '<title>' in html and 'order' in html)
check('best sellers listed', 'Best sellers' in html and 'Submersible Fuel Hose' in html)
check('categories listed', 'Categories' in html and 'Rubber hoses' in html)
check('the money breakdown adds up', 'Where the money is' in html and 'Delivery' in html)

_, html365 = get('/admin/reports?range=365')
o365 = re.search(r'<span>(\d+)</span>Orders', html365)
check('the backdated order shows in 12 months', o365 and o365.group(1) == '4', o365.group(1) if o365 else '')
check('a year is charted by month', 'Revenue per month' in html365)
_, html90 = get('/admin/reports?range=90')
o90 = re.search(r'<span>(\d+)</span>Orders', html90)
check('but not in 90 days', o90 and o90.group(1) == '3', o90.group(1) if o90 else '')
_, html7 = get('/admin/reports?range=7')
check('7 days is charted by day', 'Revenue per day' in html7)
_, htmlAll = get('/admin/reports?range=0')
check('all time works', 'Revenue per month' in htmlAll)
check('a bad range falls back to 30 days', 'Revenue per day' in get('/admin/reports?range=999')[1])

print('\nCANCELLED ORDERS')
_, html = get('/admin/orders/' + r1)
post('/admin/orders/' + r1, {'_token': token(html), 'status': 'cancelled', 'note': ''})
_, html = get('/admin/reports?range=30')
orders = re.search(r'<span>(\d+)</span>Orders, (\d+) cancelled', html)
check('cancelled is counted separately', orders and orders.groups() == ('2', '1'),
      str(orders.groups()) if orders else 'not shown')
check('and does not sell any items', 'Submersible Fuel Hose' in html)
check('status breakdown shown', 'Order status' in html and 'Cancelled' in html)

print('\nMONEY')
_, html = get('/admin/reports?range=30')
rev = re.search(r'<span>£([\d,.]+)</span>Revenue', html)
# order 2 is 4 x £12.70 = £50.80 + £12 delivery, +20% VAT = £75.36
# order 4 is one 10m acetylene hose at £11.00 + delivery + VAT
check('revenue is the sum of the live orders', rev is not None, rev.group(1) if rev else '')
avg = re.search(r'<span>£([\d,.]+)</span>Average order', html)
check('an average is shown', avg is not None, avg.group(1) if avg else '')

print('\nCSV')
code, body = get('/admin/reports/export?range=30')
rows = list(csv.reader(io.StringIO(body.lstrip('﻿'))))
check('export downloads', code == 200 and rows[0][0] == 'reference')
check('one row per order in the range', len(rows) == 4, str(len(rows) - 1) + ' rows')
check('the cancelled one is still listed', any(r[2] == 'cancelled' for r in rows[1:]))
check('items are spelled out', any(' x ' in r[19] for r in rows[1:]))
check('money is decimal, not pence', all(re.fullmatch(r'\d+\.\d\d', r[18]) for r in rows[1:]))

print('\nTIDY UP')
for ref in MADE:
    path = os.path.join(ORD, ref + '.json')
    if os.path.exists(path): os.remove(path)
check('test orders removed', not [f for f in os.listdir(ORD) if f.endswith('.json')])
check('screen is empty again', 'Nothing in last 30 days' in get('/admin/reports')[1])

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
