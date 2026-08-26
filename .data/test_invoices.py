"""Proforma invoices and delivery notes, and the numbering behind them."""
import re, os, json, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
ORD  = os.path.join(ROOT, 'storage/orders')
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

def order_file(ref):
    return json.load(open(os.path.join(ORD, ref + '.json'), encoding='utf-8'))

FAILS, MADE = [], []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:52} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')

def place(name, email, qty, opt=''):
    slug = 'acetylene-hose' if opt else 'submersible-fuel-hose-sae-j30-r10'
    body = post('/checkout/', {'cart': json.dumps([{"slug": slug, "option": opt, "qty": qty}]),
                               'name': name, 'company': 'Cheng Plant Hire', 'email': email,
                               'phone': '07000 000111', 'address': '12 Bridge Works',
                               'city': 'Leeds', 'postcode': 'LS1 4AB', 'country': 'GB',
                               'notes': 'Cut to 20m lengths, gate code 4471.',
                               'payment': 'proforma', 'website': ''})[1]
    m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
    if m: MADE.append(m.group(1))
    return m.group(1) if m else None

_, html = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk', 'password': 'Str0ngPass!2026'})

for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))

print('SETTINGS')
_, html = get('/admin/settings/payments')
check('the invoice card is on the Payments tab', 'name="invoice_prefix"' in html and 'name="bank_iban"' in html)
pay = {'_token': token(html),
       'pay[0][id]': 'proforma', 'pay[0][title]': 'Proforma invoice', 'pay[0][order]': '0',
       'pay[0][enabled]': '1', 'pay[0][description]': 'We email a proforma invoice.',
       'pay[0][instructions]': 'Please quote your order reference.',
       'invoice_prefix': 'AF-', 'invoice_next': '41', 'invoice_days': '30',
       'bank_name': 'Arg Flex Ltd', 'bank_sort': '20-00-00', 'bank_account': '12345678',
       'bank_iban': 'GB29NWBK60161331926819', 'bank_bic': 'NWBKGB2L',
       'invoice_terms': 'Goods remain our property until paid for in full.'}
post('/admin/settings/payments', pay)
stored = open(os.path.join(ROOT, 'storage/settings.php'), encoding='utf-8').read()
check('bank details saved', "'bank_iban' => 'GB29NWBK60161331926819'" in stored)
check('the counter saved', "'invoice_next' => 41" in stored)

_, html = get('/admin/settings')
gen = {'_token': token(html), 'site_name': 'Arg Flex Ltd', 'site_tag': 'Solutions for fluid transfer',
       'phone': '+44 (0) 7717 217388', 'phone_href': '+447717217388', 'email': 'sales@argflex.co.uk',
       'address': '1st floor, 107 George Lane, South Woodford, London, E18 1AN',
       'hours_week': 'Mon-Fri 9:00-17:00', 'hours_weekend': 'Sat-Sun 10:00-18:00', 'map_url': '',
       'store_addr1': '1st floor', 'store_addr2': '107 George Lane, South Woodford',
       'store_city': 'London', 'store_postcode': 'E18 1AN', 'store_country': 'GB',
       'company_number': '09876543', 'vat_number': 'GB 123 4567 89',
       'sell_to': 'all', 'ship_to': 'sell', 'default_country': 'GB', 'currency': 'GBP',
       'currency_pos': 'left', 'thousand_sep': ',', 'decimal_sep': '.', 'decimals': '2',
       'enable_taxes': '1', 'soc1_name': '', 'soc1_url': '', 'soc2_name': '', 'soc2_url': '',
       'soc3_name': '', 'soc3_url': '', 'soc4_name': '', 'soc4_url': ''}
post('/admin/settings', gen)
check('company and tax numbers saved', "'vat_number' => 'GB 123 4567 89'" in
      open(os.path.join(ROOT, 'storage/settings.php'), encoding='utf-8').read())

print('\nTHE INVOICE')
ref = place('Rita Cheng', 'rita@example.com', 8, 'Inner Diameter: 8mm, Length: 10m')
check('order placed', ref is not None, ref or '')
check('no invoice number until one is asked for', 'invoice' not in order_file(ref))

_, html = get('/admin/orders/' + ref)
check('the order screen offers both documents',
      '/invoice"' in html and '/note"' in html and 'gives this order its number' in html)

code, doc = get(f'/admin/orders/{ref}/invoice')
check('the invoice renders', code == 200 and '<h1>Proforma invoice</h1>' in doc)
check('it stands alone, without the admin chrome', 'class="shell"' not in doc and 'print.css' in doc)
num = re.search(r'<dd><b>(AF-\d+)</b></dd>', doc)
check('it took the next number', num and num.group(1) == 'AF-00041', num.group(1) if num else 'none')
check('the number is on the order now', order_file(ref).get('invoice', {}).get('number') == 'AF-00041')
check('and the counter moved on', "'invoice_next' => 42" in
      open(os.path.join(ROOT, 'storage/settings.php'), encoding='utf-8').read())

code, again = get(f'/admin/orders/{ref}/invoice')
num2 = re.search(r'<dd><b>(AF-\d+)</b></dd>', again)
check('opening it again keeps the same number', num2 and num2.group(1) == 'AF-00041',
      num2.group(1) if num2 else 'none')
check('and does not move the counter', "'invoice_next' => 42" in
      open(os.path.join(ROOT, 'storage/settings.php'), encoding='utf-8').read())

print('\nWHAT IS ON IT')
check('our address', '107 George Lane' in doc and 'E18 1AN' in doc)
check('company and tax numbers', '09876543' in doc and 'GB 123 4567 89' in doc)
check('the customer', 'Rita Cheng' in doc and 'Cheng Plant Hire' in doc and 'LS1 4AB' in doc)
check('the line, with its options', 'Acetylene hose' in doc and 'Length: 10m' in doc)
check('unit price and amount', doc.count('class="money"') >= 4)
# The rate names its own speed and length band, and the tax line is labelled
# "Tax" — both taken from the live shop, which does not call it VAT.
check('delivery line names the rate', 'days' in doc and ('1-2' in doc or '3-4' in doc))
check('tax line', 'Tax at 20%' in doc)
check('total due', 'Total due' in doc)
check('the order notes', 'gate code 4471' in doc)
check('bank details', 'GB29NWBK60161331926819' in doc and '20-00-00' in doc)
check('a payment reference to quote', 'quote <b>AF-00041</b>' in doc)
check('terms', 'until paid for in full' in doc)
check('a due date, 30 days out', 'Payment due' in doc)

o = order_file(ref)['order']
total = re.search(r'Total due</th>\s*<td class="money">([^<]+)</td>', doc)
want  = f"£{o['total'] / 100:,.2f}"
check('the total matches the stored order', total and total.group(1).strip() == want,
      f"{total.group(1).strip() if total else '?'} vs {want}")

print('\nTHE DELIVERY NOTE')
code, note = get(f'/admin/orders/{ref}/note')
check('it renders', code == 200 and '<h1>Delivery note</h1>' in note)
check('no prices anywhere', '£' not in note.split('<main')[1])
check('quantities are still there', 'Qty' in note and '>8<' in note)
check('somewhere to sign', 'Received in good condition' in note and 'Signature' in note)
check('it says it is not a bill', 'not a request for payment' in note)
check('it did not issue a second number', "'invoice_next' => 42" in
      open(os.path.join(ROOT, 'storage/settings.php'), encoding='utf-8').read())

print('\nNUMBERING')
ref2 = place('Omar Silva', 'omar@example.com', 3)
get(f'/admin/orders/{ref2}/invoice')
check('the next order takes the next number', order_file(ref2)['invoice']['number'] == 'AF-00042')
check('the first one is untouched', order_file(ref)['invoice']['number'] == 'AF-00041')

check('an unknown order 404s', get('/admin/orders/000000-ZZZZZZ/invoice')[0] == 404)
check('a made-up sub-page falls back to the order',
      'Proforma invoice</a>' in get(f'/admin/orders/{ref}/nonsense')[1])

print('\nTIDY UP')
for r in MADE:
    p = os.path.join(ORD, r + '.json')
    if os.path.exists(p): os.remove(p)
os.remove(os.path.join(ROOT, 'storage/settings.php'))
check('test orders and settings removed',
      not [f for f in os.listdir(ORD) if f.endswith('.json')]
      and not os.path.exists(os.path.join(ROOT, 'storage/settings.php')))
check('site still fine on the defaults', get('/')[0] == 200)

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
