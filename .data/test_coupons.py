"""Discount codes: the admin screen, the rules, the cart endpoint and an order."""
import re, os, json, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
op   = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

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

def coupons_src():
    return open(os.path.join(ROOT, 'data/coupons.php'), encoding='utf-8').read()

CART = [{"slug": "submersible-fuel-hose-sae-j30-r10", "option": "", "qty": 10}]   # 10 x £12.70 = £127.00

def check_code(code, cart=None):
    code_, body = post('/coupon-check', {'code': code, 'cart': json.dumps(cart if cart is not None else CART)})
    try:
        return json.loads(body)
    except Exception:
        return {'ok': False, 'error': 'not json: ' + body[:120]}

FAILS = []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:50} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')

_, html = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk', 'password': 'Str0ngPass!2026'})

print('ADMIN SCREEN')
code, html = get('/admin/coupons')
check('list renders when empty', code == 200 and 'No codes yet' in html)
check('warns that codes are switched off', 'switched off' in html)
check('nav lists it', 'Discount codes' in html)
code, html = get('/admin/coupons/new')
check('editor renders', code == 200 and 'name="usage_limit"' in html)
check('categories and products offered', 'name="categories[]"' in html and 'name="products[]"' in html)
check('unknown code 404s', get('/admin/coupons/NOPE')[0] == 404)

print('\nCREATING CODES')
_, html = get('/admin/coupons/new')
post('/admin/coupons/new', {'_token': token(html), 'code': 'spring-10', 'description': 'Spring promotion',
                            'enabled': '1', 'type': 'percent', 'amount': '10',
                            'min_spend': '0', 'max_spend': '0', 'starts': '', 'expires': '',
                            'usage_limit': '0'})
src = coupons_src()
check('code upper-cased and kept', "'code' => 'SPRING-10'" in src)
check('percentage stored as a percentage', "'amount' => 10" in src)

_, html = get('/admin/coupons/new')
_, body = post('/admin/coupons/new', {'_token': token(html), 'code': 'SPRING-10', 'enabled': '1',
                                      'type': 'percent', 'amount': '5', 'min_spend': '0',
                                      'max_spend': '0', 'usage_limit': '0'})
check('a duplicate code is refused', 'already in use' in body)
check('and nothing was written', coupons_src().count("'code' =>") == 1)

_, html = get('/admin/coupons/new')
_, body = post('/admin/coupons/new', {'_token': token(html), 'code': 'BADDATES', 'enabled': '1',
                                      'type': 'percent', 'amount': '5', 'min_spend': '0', 'max_spend': '0',
                                      'starts': '2026-06-01', 'expires': '2026-01-01', 'usage_limit': '0'})
check('end before start is refused', 'before the start date' in body)

_, html = get('/admin/coupons/new')
post('/admin/coupons/new', {'_token': token(html), 'code': 'BIGORDER', 'description': '',
                            'enabled': '1', 'type': 'fixed', 'amount': '25.50',
                            'min_spend': '200', 'max_spend': '0', 'usage_limit': '2'})
check('fixed amount stored in pence', "'amount' => 2550" in coupons_src())
check('minimum spend stored in pence', "'min_spend' => 20000" in coupons_src())

_, html = get('/admin/coupons/new')
post('/admin/coupons/new', {'_token': token(html), 'code': 'CLAMPS', 'enabled': '1',
                            'type': 'percent', 'amount': '50', 'min_spend': '0', 'max_spend': '0',
                            'usage_limit': '0', 'categories[]': ['oil-products']})
check('a category limit is stored', "'oil-products'," in coupons_src())

_, html = get('/admin/coupons/new')
post('/admin/coupons/new', {'_token': token(html), 'code': 'GONE', 'enabled': '1', 'type': 'percent',
                            'amount': '10', 'min_spend': '0', 'max_spend': '0',
                            'usage_limit': '0', 'expires': '2020-01-01'})
_, html = get('/admin/coupons')
check('an expired code is flagged in the list', 'cstate expired' in html)
check('four codes listed', html.count('name="codes[]"') == 4, str(html.count('name="codes[]"')))

print('\nRULES (codes still switched off site-wide)')
r = check_code('SPRING-10')
check('nothing applies while the toggle is off', not r['ok'] and 'not being accepted' in r['error'])

_, html = get('/admin/settings')
base = {'_token': token(html), 'site_name': 'Arg Flex Ltd', 'site_tag': 'Solutions for fluid transfer',
        'phone': '+44 (0) 7717 217388', 'phone_href': '+447717217388', 'email': 'sales@argflex.co.uk',
        'address': '1st floor, 107 George Lane, South Woodford, London, E18 1AN',
        'hours_week': 'Mon-Fri 9:00-17:00', 'hours_weekend': 'Sat-Sun 10:00-18:00', 'map_url': '',
        'store_addr1': '1st floor', 'store_addr2': '107 George Lane', 'store_city': 'London',
        'store_postcode': 'E18 1AN', 'store_country': 'GB', 'sell_to': 'all', 'ship_to': 'sell',
        'default_country': 'GB', 'currency': 'GBP', 'currency_pos': 'left', 'thousand_sep': ',',
        'decimal_sep': '.', 'decimals': '2', 'enable_taxes': '1', 'enable_coupons': '1',
        'soc1_name': '', 'soc1_url': '', 'soc2_name': '', 'soc2_url': '',
        'soc3_name': '', 'soc3_url': '', 'soc4_name': '', 'soc4_url': ''}
post('/admin/settings', base)
check('the toggle saved', "'enable_coupons' => true" in open(os.path.join(ROOT, 'storage/settings.php'), encoding='utf-8').read())

print('\nRULES (switched on)')
r = check_code('SPRING-10')
check('10% off £127.00 is £12.70', r['ok'] and r['discount'] == 1270, str(r.get('discount')))
check('the description is returned', r.get('title') == 'Spring promotion')
r = check_code('spring-10')
check('the code is case-insensitive', r['ok'] and r['discount'] == 1270)
r = check_code('NOSUCHCODE')
check('an unknown code is refused', not r['ok'] and 'not recognised' in r['error'])
r = check_code('GONE')
check('an expired code is refused', not r['ok'] and 'expired' in r['error'])
r = check_code('BIGORDER')
check('under the minimum is refused', not r['ok'] and '£200.00' in r['error'], r.get('error', ''))
r = check_code('BIGORDER', [{"slug": "submersible-fuel-hose-sae-j30-r10", "option": "", "qty": 100}])
check('over the minimum applies', r['ok'] and r['discount'] == 2550, str(r.get('discount')))
r = check_code('CLAMPS', [{"slug": "acetylene-hose", "option": "Inner Diameter: 6mm, Length: 5m", "qty": 4}])
check('a category limit excludes other products', not r['ok'] and 'does not apply' in r['error'],
      r.get('error', ''))
r = check_code('CLAMPS')
check('and it applies to a product in that category', r['ok'] and r['discount'] == 6350,
      str(r.get('discount')))

r = check_code('SPRING-10', [{"slug": "submersible-fuel-hose-sae-j30-r10", "option": "", "qty": 999999}])
check('quantity is clamped to 999, not trusted', r['ok'] and r['discount'] == 126873,
      str(r.get('discount')))
r = check_code('SPRING-10', [{"slug": "submersible-fuel-hose-sae-j30-r10", "option": "", "qty": 10, "price": 999999}])
check('a tampered price is ignored', r['ok'] and r['discount'] == 1270, str(r.get('discount')))
code, body = get('/coupon-check')
check('GET is refused', code == 405)

print('\nSTOREFRONT')
_, cart = get('/cart/')
check('the code box shows on the cart', 'data-coupon' in cart and 'Discount code' in cart)
check('a discount row is ready', 'data-discount-row' in cart)
_, co = get('/checkout/')
check('the checkout carries the hidden field', 'data-coupon-field' in co)

print('\nAN ORDER WITH A CODE')
cart = json.dumps([{"slug": "submersible-fuel-hose-sae-j30-r10", "option": "", "qty": 10}])
_, body = post('/checkout/', {'cart': cart, 'coupon': 'SPRING-10', 'name': 'Jane Tester',
                              'company': '', 'email': 'jane@example.com', 'phone': '07000 000000',
                              'address': '1 Test Road', 'city': 'London', 'postcode': 'E18 1AN',
                              'country': 'GB', 'notes': '', 'payment': 'proforma', 'website': ''})
ref = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
check('order placed', ref is not None)
if ref:
    rec = json.load(open(os.path.join(ROOT, 'storage/orders', ref.group(1) + '.json'), encoding='utf-8'))
    o = rec['order']
    check('the code is on the order', o['coupon'] == 'SPRING-10')
    check('the discount is £12.70', o['discount'] == 1270, str(o['discount']))
    # Delivery is priced on the metres in the basket, not on what is being
    # paid for the goods, so a discount does not move it. This hose has no
    # weight, so the basket falls in the under-five-metre band at £4.20.
    check('a discount does not change the delivery', o['shipping'] == 420, str(o['shipping']))
    check('tax is on the goods less the discount, and not on delivery',
          o['vat'] == round((12700 - 1270) * 0.20), str(o['vat']))
    check('the total adds up', o['total'] == 12700 - 1270 + 420 + o['vat'], str(o['total']))
    check('the use was counted', "'used' => 1" in coupons_src())
    os.remove(os.path.join(ROOT, 'storage/orders', ref.group(1) + '.json'))

_, body = post('/checkout/', {'cart': cart, 'coupon': 'GONE', 'name': 'Jane Tester', 'company': '',
                              'email': 'jane@example.com', 'phone': '07000 000000',
                              'address': '1 Test Road', 'city': 'London', 'postcode': 'E18 1AN',
                              'country': 'GB', 'notes': '', 'payment': 'proforma', 'website': ''})
ref = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
if ref:
    rec = json.load(open(os.path.join(ROOT, 'storage/orders', ref.group(1) + '.json'), encoding='utf-8'))
    check('an expired code posted straight to the server is ignored',
          rec['order']['discount'] == 0 and rec['order']['coupon'] == '')
    os.remove(os.path.join(ROOT, 'storage/orders', ref.group(1) + '.json'))
else:
    check('an expired code posted straight to the server is ignored', False, 'no order placed')

print('\nUSAGE LIMIT')
_, html = get('/admin/coupons/BIGORDER')
big = [{"slug": "submersible-fuel-hose-sae-j30-r10", "option": "", "qty": 100}]
post('/admin/coupons/BIGORDER', {'_token': token(html), 'code': 'BIGORDER', 'description': '',
                                 'enabled': '1', 'type': 'fixed', 'amount': '25.50',
                                 'min_spend': '200', 'max_spend': '0', 'usage_limit': '1'})
_, body = post('/checkout/', {'cart': json.dumps(big), 'coupon': 'BIGORDER', 'name': 'Jane Tester',
                              'company': '', 'email': 'jane@example.com', 'phone': '07000 000000',
                              'address': '1 Test Road', 'city': 'London', 'postcode': 'E18 1AN',
                              'country': 'GB', 'notes': '', 'payment': 'proforma', 'website': ''})
ref = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
if ref: os.remove(os.path.join(ROOT, 'storage/orders', ref.group(1) + '.json'))
r = check_code('BIGORDER', big)
check('a code stops once its limit is reached', not r['ok'] and 'used up' in r['error'], r.get('error', ''))

print('\nTIDY UP')
_, html = get('/admin/coupons')
post('/admin/coupons', {'_token': token(html), 'bulk': 'delete',
                        'codes[]': ['SPRING-10', 'BIGORDER', 'CLAMPS', 'GONE']})
check('bulk delete works', coupons_src().count("'code' =>") == 0, coupons_src().count("'code' =>"))
os.remove(os.path.join(ROOT, 'storage/settings.php'))
check('site fine on the defaults again', get('/')[0] == 200)
check('and the code box is hidden again', 'data-coupon' not in get('/cart/')[1])

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
