"""Customers: the list, one person's page, and the CSV, against real orders."""
import re, os, json, csv, io, urllib.request, urllib.parse, urllib.error, http.cookiejar, sys

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

def order(email, name, company, qty, city='London', country='GB'):
    body = post('/checkout/', {'cart': json.dumps([{"slug": "submersible-fuel-hose-sae-j30-r10",
                                                    "option": "", "qty": qty}]),
                               'name': name, 'company': company, 'email': email,
                               'phone': '07000 000111', 'address': '1 Test Road', 'city': city,
                               'postcode': 'E18 1AN', 'country': country, 'notes': '',
                               'payment': 'proforma', 'website': ''})[1]
    m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
    if m: MADE.append(m.group(1))
    return m.group(1) if m else None

_, html = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk', 'password': 'Str0ngPass!2026'})

# start from a clean slate so the numbers below are exact
for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))

# Placing an order opens an account, and somebody with an account is a
# customer whether or not their order is still on disk. So the accounts go
# with the orders, or "empty" is never empty on the second run.
ACC = os.path.join(ROOT, 'storage/customers.php')
def forget_accounts():
    try:
        os.remove(ACC)
    except OSError:
        pass

forget_accounts()

print('EMPTY')
code, html = get('/admin/customers')
check('list renders with nobody on it', code == 200 and 'No customers yet' in html)
check('it explains where the list comes from', 'no customer accounts' in html)
check('unknown customer 404s', get('/admin/customers/nobody@example.com')[0] == 404)

print('\nWITH ORDERS')
a1 = order('rita@example.com',  'Rita Cheng',  'Cheng Plant Hire', 10)   # £127.00 net
a2 = order('RITA@Example.com',  'Rita Cheng',  'Cheng Plant Hire', 30)   # same person, different case
b1 = order('omar@example.com',  'Omar Silva',  '', 5, city='Leeds')
check('three orders placed', all([a1, a2, b1]), ', '.join(MADE))

code, html = get('/admin/customers')
check('two customers, not three', html.count('/admin/customers/rita') + html.count('/admin/customers/omar') > 0
      and html.count('<tbody>') == 1 and html.count('Open</a>') == 2, str(html.count('Open</a>')))
check('the address is case-folded into one person', 'rita@example.com' in html and 'RITA@' not in html)
check('order counts shown', re.search(r'<td>\s*2\s*<', html) is not None)
check('company shown beside the name', 'Cheng Plant Hire' in html)
check('town shown', 'Leeds' in html)

_, html = get('/admin/customers?q=leeds')
check('search by town', 'Omar Silva' in html and 'Rita Cheng' not in html)
_, html = get('/admin/customers?q=cheng+plant')
check('search by company', 'Rita Cheng' in html and 'Omar Silva' not in html)
_, html = get('/admin/customers?q=zzz')
check('a search with no hits says so', 'Nobody matched' in html)

_, html = get('/admin/customers?sort=name')
first = re.search(r'<tbody>.*?<b>([^<]+)</b>', html, re.S)
check('sort by name', first and first.group(1).startswith('Omar'), first.group(1) if first else '')
_, html = get('/admin/customers?sort=spent')
first = re.search(r'<tbody>.*?<b>([^<]+)</b>', html, re.S)
check('sort by spend puts Rita first', first and first.group(1).startswith('Rita'), first.group(1) if first else '')

print('\nONE CUSTOMER')
code, html = get('/admin/customers/rita%40example.com')
check('their page opens', code == 200 and 'Rita Cheng' in html)
check('both orders listed', all(ref in html for ref in MADE[:2]))
check('the other customer is not', MADE[2] not in html)
check('what they buy is totted up', 'What they buy' in html and 'Submersible Fuel Hose' in html)
qty = re.search(r'What they buy.*?<td>(\d+)</td>', html, re.S)
check('quantities added across orders', qty and qty.group(1) == '40', qty.group(1) if qty else '')
check('contact details carried over', 'Cheng Plant Hire' in html and '07000 000111' in html)
check('a mailto link is offered', 'mailto:rita@example.com' in html)

print('\nCANCELLED ORDERS')
_, html = get('/admin/orders/' + MADE[0])
post('/admin/orders/' + MADE[0], {'_token': token(html), 'status': 'cancelled', 'note': ''})
_, html = get('/admin/customers/rita%40example.com')
spent = re.findall(r'<span>£([\d,.]+)</span>Spent', html)
check('a cancelled order stops counting towards spend',
      spent and spent[0] not in ('0.00',) and 'cancelled excluded' in html, spent[0] if spent else '')
_, list_html = get('/admin/customers')
check('and is flagged in the list', '1 cancelled' in list_html)

print('\nCSV')
code, body = get('/admin/customers/export')
rows = list(csv.reader(io.StringIO(body.lstrip('﻿'))))
check('export downloads', code == 200 and rows[0][0] == 'name')
check('one row per customer', len(rows) == 3, str(len(rows) - 1) + ' rows')
rita = [r for r in rows if r[2] == 'rita@example.com']
check('the row carries the totals', rita and rita[0][8] == '2' and rita[0][9] == '1',
      ', '.join(rita[0][8:12]) if rita else '')
check('and the order references', rita and ' | ' in rita[0][14])

print('\nDASHBOARD')
_, html = get('/admin/')
check('customer count on the dashboard', re.search(r'<span>2</span>Customers', html) is not None)

print('\nTIDY UP')
for ref in MADE:
    path = os.path.join(ORD, ref + '.json')
    if os.path.exists(path): os.remove(path)
check('test orders removed', not [f for f in os.listdir(ORD) if f.endswith('.json')])
forget_accounts()
check('list is empty again', 'No customers yet' in get('/admin/customers')[1])

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
