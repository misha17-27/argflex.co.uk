"""Hold every product page to what its own catalogue row says.

    python .data/check_product_pages.py

Needs the local site on http://localhost:8124 (ARGFLEX_BASE); starts one of
its own when nothing answers there, and stops it again. Reads nothing from
the live site and writes nothing anywhere.

Two things on these pages were wrong for as long as the shop has been up and
no check looked at either:

  * the structured data offered one AggregateOffer, "2.04 to 96.52", whose
    low end was a one-metre price and whose high end was a fifty-metre coil;
  * the headline printed the same range over the words "Per metre".

So for every product this works out again, from the raw option rows that
.data/product_facts.php hands over, what should be there, and compares:

  * every JSON-LD block parses;
  * a priced product has exactly one Product, with one Offer per priced
    option and no AggregateOffer: each with the option's own price, GBP, the
    page's canonical URL, a name no other offer shares, NewCondition, and the
    availability of THAT option — an option flagged out of stock must say
    OutOfStock whatever its neighbours say;
  * the price is marked net: valueAddedTaxIncluded is false while the shop
    charges tax, and is never true;
  * referenceQuantity is the option's length in metres against one metre,
    and is absent where the option names no length;
  * no shippingDetails and no hasMerchantReturnPolicy. Not because they are
    unwelcome: delivery is priced on the metres in the basket and the returns
    page excludes cut lengths, so neither can be stated per offer today. This
    fails the day somebody adds one, so that it is added on purpose;
  * the headline says "per metre" only of a one-metre option, names any other
    length as a length, and never prints a range over a unit;
  * the headline quotes only what can be bought: its "from" price and its
    "<length> from" price are the cheapest options that are NOT sold out,
    worked out here from each option's own stock, and fall back to every
    option only when all are sold out ("50m length from £50.00" was printed
    over a hose whose one £50.00 coil could not be ordered);
  * what the browser is given to print once an option is chosen (data-variants
    and /quick-view.php) says the same of each option;
  * a product with no price has no Product block, does not call itself
    og:type "product", and makes no claim about metres.

The local catalogue has no sold-out product, so three such situations are
built in memory by product_facts.php and their output held to the same rules.
"""
import html
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from devserver import DevServer

BASE = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PHP = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))

SCHEMA = 'https://schema.org/'
STATES = {'out': 'OutOfStock', 'backorder': 'BackOrder', 'preorder': 'PreOrder',
          'in': 'InStock', 'low': 'InStock'}
RANGE = '–'            # the dash price_label() joins a range with


def catalogue():
    out = subprocess.run([PHP, os.path.join('.data', 'product_facts.php')], cwd=ROOT,
                         capture_output=True, text=True, encoding='utf-8', errors='replace')
    try:
        return json.loads(out.stdout)
    except ValueError:
        print('product_facts.php did not answer with JSON:\n' + (out.stdout + out.stderr)[:600])
        sys.exit(1)


FACTS = catalogue()


def fetch(path):
    req = urllib.request.Request(BASE + path, headers={'User-Agent': 'argflex-check'})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, str(e)


def metres(length):
    """50.0 for "50m", 0.5 for "0.5m", 0.0 for anything that is not metres."""
    m = re.match(r'^(\d+(?:\.\d+)?)\s*m$', length.strip(), re.I)
    return float(m.group(1)) if m else 0.0


def money(pence):
    return FACTS['money'][str(pence)]


def per_words(length):
    if metres(length) == 1.0:
        return 'per metre'
    return f'for a {length} length' if metres(length) > 0 else ''


def text_of(fragment):
    return re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', fragment))).strip()


def rows_of(product):
    """What can be bought: (label, pence, length, out-of-stock flag, state)."""
    if product['options']:
        return [o for o in product['options'] if o['pence'] > 0]
    if product['pence'] > 0:
        return [{'key': '', 'label': '', 'pence': product['pence'], 'length': '',
                 'flag': product['flag'], 'state': product['state']}]
    return []


def sold_out(row):
    """Its own availability, as offer_problems() reads it: the stored flag
    first, then the shop's answer."""
    return row['flag'] == 'outofstock' or row['state'] == 'out'


def expected_headline(product):
    """(what the <b> says, what the small print says before the tax suffix)."""
    rows = rows_of(product)
    lo, hi = min(r['pence'] for r in rows), max(r['pence'] for r in rows)
    spread = money(lo) + (f' {RANGE} {money(hi)}' if hi > lo else '')

    if not product['options']:
        return spread, ['per metre']
    if any(metres(r['length']) <= 0 for r in rows):
        return spread, []

    # A price is quoted only from what can be bought: "50m length from
    # £50.00" stood over a hose whose one £50.00 coil was sold out. Every
    # option counts again only when none of them can be bought.
    rows = [r for r in rows if not sold_out(r)] or rows
    lo, hi = min(r['pence'] for r in rows), max(r['pence'] for r in rows)

    cheapest = min(rows, key=lambda r: (r['pence'], metres(r['length'])))
    note = [per_words(cheapest['length'])]
    longest = max(metres(r['length']) for r in rows)
    if longest > metres(cheapest['length']):
        at = [r for r in rows if metres(r['length']) == longest]
        prices = {r['pence'] for r in at}
        note.append(f"{at[0]['length']} length " + ('from ' if len(prices) > 1 else '') + money(min(prices)))
    return ('From ' if hi > lo else '') + money(lo), note


def offer_problems(offers, rows, name, url):
    """What is wrong with a list of Offer objects, given the rows they stand for."""
    wrong = []
    if len(offers) != len(rows):
        return [f'{len(offers)} offer(s) for {len(rows)} priced option(s)']

    names = [o.get('name', '') for o in offers]
    if len(rows) > 1 and len(set(names)) != len(names):
        wrong.append('two offers share a name, so nothing tells the options apart')

    for offer, row in zip(offers, rows):
        what = row['label'] or 'the offer'
        price = '%.2f' % (row['pence'] / 100)
        spec = offer.get('priceSpecification') or {}

        if offer.get('@type') != 'Offer':
            wrong.append(f'{what}: @type is {offer.get("@type")!r}, not Offer')
        if offer.get('price') != price or float(price) <= 0:
            wrong.append(f'{what}: price {offer.get("price")!r}, the option costs {price}')
        if offer.get('priceCurrency') != FACTS['currency']:
            wrong.append(f'{what}: priceCurrency {offer.get("priceCurrency")!r}')
        if offer.get('url') != url:
            wrong.append(f'{what}: url {offer.get("url")!r} is not the page, {url}')
        if offer.get('itemCondition') != SCHEMA + 'NewCondition':
            wrong.append(f'{what}: itemCondition {offer.get("itemCondition")!r}')
        if row['label'] and (row['label'] not in names[offers.index(offer)]
                             or name not in names[offers.index(offer)]):
            wrong.append(f'{what}: named {offer.get("name")!r}, which does not say which option it is')

        # its OWN availability: the stored flag first, then the shop's answer
        must = 'OutOfStock' if row['flag'] == 'outofstock' else STATES.get(row['state'])
        if offer.get('availability') != SCHEMA + str(must):
            wrong.append(f'{what}: availability {offer.get("availability")!r}, should be {must}')

        if spec.get('@type') != 'UnitPriceSpecification':
            wrong.append(f'{what}: priceSpecification is {spec.get("@type")!r}')
        if spec.get('price') != price or spec.get('priceCurrency') != FACTS['currency']:
            wrong.append(f'{what}: priceSpecification says {spec.get("price")!r} {spec.get("priceCurrency")!r}')
        # net of VAT, and saying so; never a claim that the tax is in the price
        vat = spec.get('valueAddedTaxIncluded', 'absent')
        if FACTS['tax_on'] and vat is not False:
            wrong.append(f'{what}: valueAddedTaxIncluded is {vat!r} on a price the checkout adds tax to')
        if not FACTS['tax_on'] and vat != 'absent':
            wrong.append(f'{what}: valueAddedTaxIncluded is {vat!r} with tax switched off')

        ref = spec.get('referenceQuantity')
        m = metres(row['length'])
        if m <= 0:
            if ref is not None:
                wrong.append(f'{what}: a referenceQuantity on an option that names no length')
        else:
            base = (ref or {}).get('valueReference') or {}
            try:
                same = (float(ref.get('value')) == m and ref.get('unitCode') == 'MTR'
                        and float(base.get('value')) == 1.0 and base.get('unitCode') == 'MTR')
            except (AttributeError, TypeError, ValueError):
                same = False
            if not same:
                wrong.append(f'{what}: referenceQuantity {json.dumps(ref)} is not {m:g} MTR per 1 MTR')

        for claim in ('shippingDetails', 'hasMerchantReturnPolicy'):
            if claim in offer:
                wrong.append(f'{what}: {claim} is stated, and the code holds no rate or '
                             'return window that is true of every option (see the header)')
    return wrong


def headline_problems(price, note, product):
    """The same rule on the two strings the visitor reads."""
    wrong = []
    want_price, want_note = expected_headline(product)
    want_note = ' · '.join([n for n in want_note if n] + ([FACTS['suffix']] if FACTS['suffix'] else []))
    if price != want_price:
        wrong.append(f'headline price reads {price!r}, should read {want_price!r}')
    if note != want_note:
        wrong.append(f'headline small print reads {note!r}, should read {want_note!r}')
    # stated once more, bluntly, so a change to both workings cannot hide it
    if RANGE in price and 'per metre' in note.lower():
        wrong.append(f'a price range is labelled per metre: {price!r} {note!r}')
    return wrong


def page_problems(slug, product):
    status, body = fetch(product['url'])
    if status != 200:
        return [f'answered {status}']
    wrong = []
    rows = rows_of(product)
    canonical = re.search(r'<link rel="canonical" href="([^"]+)"', body)
    canonical = html.unescape(canonical.group(1)) if canonical else ''
    og_type = re.search(r'<meta property="og:type" content="([^"]*)"', body)
    og_type = og_type.group(1) if og_type else ''
    if not re.search(r'<h1[^>]*>\s*\S', body):
        wrong.append('no <h1>')

    nodes = []
    for block in re.findall(r'<script type="application/ld\+json">(.*?)</script>', body, re.S):
        try:
            data = json.loads(block)
        except ValueError:
            wrong.append('a JSON-LD block does not parse')
            continue
        nodes += data if isinstance(data, list) else [data]
    if 'AggregateOffer' in json.dumps(nodes):
        wrong.append('an AggregateOffer, which is for one product sold by several merchants')
    products = [n for n in nodes if isinstance(n, dict) and n.get('@type') == 'Product']

    box = re.search(r'<div class="p-price">(.*?)</div>', body, re.S)
    box = box.group(1) if box else ''
    bold = re.search(r'<b>(.*?)</b>', box, re.S)
    small = re.search(r'<small[^>]*>(.*?)</small>', box, re.S)
    price, note = text_of(bold.group(1)) if bold else '', text_of(small.group(1)) if small else ''

    if not rows:
        if products:
            wrong.append('a Product block on a product with no price to offer')
        if og_type == 'product':
            wrong.append('og:type is "product" with no price on the page')
        if price != 'Price on request':
            wrong.append(f'headline reads {price!r} on a product with no price')
        if 'metre' in note.lower():
            wrong.append(f'the small print talks about metres with no price: {note!r}')
        return wrong

    if og_type != 'product':
        wrong.append(f'og:type is {og_type!r} on a product with a price')
    if len(products) != 1:
        return wrong + [f'{len(products)} Product blocks']
    node = products[0]
    if node.get('name') != product['name']:
        wrong.append(f'Product name {node.get("name")!r}')
    if not canonical.endswith(urllib.parse.urlparse(product['url']).path) or node.get('url') != canonical:
        wrong.append(f'Product url {node.get("url")!r} against canonical {canonical!r}')
    offers = node.get('offers')
    offers = offers if isinstance(offers, list) else [offers] if isinstance(offers, dict) else []
    wrong += offer_problems(offers, rows, product['name'], canonical)
    wrong += headline_problems(price, note, product)

    # what site.js prints once an option is chosen: that option's own words
    chooser = re.search(r"data-variants='([^']*)'", body)
    if chooser:
        chosen = json.loads(html.unescape(chooser.group(1)))
        for row in product['options']:
            said = (chosen.get(row['key']) or {}).get('per')
            if said != per_words(row['length']):
                wrong.append(f'{row["label"]}: once chosen it is labelled {said!r}, '
                             f'should be {per_words(row["length"])!r}')
        if 'data-price-note' not in box:
            wrong.append('the small print has no data-price-note, so it cannot follow the chosen option')

    # the popup on every listing draws the same line from its own endpoint
    status, quick = fetch('/quick-view.php?slug=' + urllib.parse.quote(urllib.parse.unquote(slug)))
    try:
        quick = json.loads(quick)
    except ValueError:
        return wrong + [f'/quick-view.php answered {status} and no JSON']
    wrong += ['quick view: ' + w for w in headline_problems(quick.get('price', ''), quick.get('suffix', ''), product)]
    for key, option in (quick.get('variants') or {}).items():
        row = next((r for r in product['options'] if r['key'] == key), None)
        if row and option.get('per') != per_words(row['length']):
            wrong.append(f'quick view: {row["label"]} is labelled {option.get("per")!r}')
    return wrong


def case_problems():
    """The situations built in memory, held to the rules above."""
    wrong, cases = [], FACTS['cases']
    for name in ('every_option_sold_out', 'no_options_sold_out', 'options_without_a_length'):
        if name not in cases:
            wrong.append(f'case {name}: the catalogue has no product to build it from')
    for name in ('every_option_sold_out', 'no_options_sold_out'):
        offers = cases.get(name, {}).get('offers', [])
        if not offers:
            wrong.append(f'case {name}: a priced product that is sold out lost its offers')
        for offer in offers:
            if offer.get('availability') != SCHEMA + 'OutOfStock':
                wrong.append(f'case {name}: {offer.get("name", "the offer")} says {offer.get("availability")}')
            if (offer.get('priceSpecification') or {}).get('valueAddedTaxIncluded', False) is not False:
                wrong.append(f'case {name}: claims a VAT-inclusive price')
    bare = cases.get('options_without_a_length', {})
    for offer in bare.get('offers', []):
        if 'referenceQuantity' in (offer.get('priceSpecification') or {}):
            wrong.append('case options_without_a_length: a referenceQuantity nobody stated')
    if bare and (bare['headline']['per'] or bare['headline']['also']):
        wrong.append(f'case options_without_a_length: the headline claims {bare["headline"]!r}')

    # the headline quotes what can be bought, in each situation built for it
    for name in ('cheapest_option_sold_out', 'cheapest_long_length_sold_out', 'every_option_sold_out'):
        case = cases.get(name)
        if not case:
            if name != 'every_option_sold_out':        # that one is reported above
                wrong.append(f'case {name}: the catalogue has no product to build it from')
            continue
        gone = [o for o in case['facts']['options'] if sold_out(o)]
        if not gone:
            wrong.append(f'case {name}: no option in it is sold out, so it tests nothing')
        want_price, want_note = expected_headline(case['facts'])
        got = case['headline']
        got_note = [got['per']] + ([got['also']] if got['also'] else [])
        if got['price'] != want_price or got_note != want_note:
            wrong.append(f'case {name}: headline {got["price"]!r} {got_note!r}, '
                         f'what can be bought says {want_price!r} {want_note!r}')
        if name != 'every_option_sold_out':
            # and, said without the working above: no figure in it belongs
            # to a sold-out option alone
            buyable = {money(o['pence']) for o in case['facts']['options'] if not sold_out(o)}
            for o in gone:
                figure = money(o['pence'])
                if figure not in buyable and figure in got['price'] + ' ' + got['also']:
                    wrong.append(f'case {name}: the headline quotes {figure}, '
                                 f'which only the sold-out {o["label"]} costs')
    return wrong


server = DevServer(PHP, base=BASE)
if not server.alive() and not server.start():
    print(f'{BASE} is not responding and could not be started')
    sys.exit(1)

problems, kinds = [], {'no options': 0, 'one bore, several lengths': 0, 'several bores': 0,
                       'an option out of stock': 0, 'no price': 0}
try:
    for slug, product in FACTS['products'].items():
        rows = rows_of(product)
        if not rows:
            kinds['no price'] += 1
        elif not product['options']:
            kinds['no options'] += 1
        elif len(rows) > len({r['length'] for r in rows}):      # more options than lengths
            kinds['several bores'] += 1
        else:
            kinds['one bore, several lengths'] += 1
        if any(r['flag'] == 'outofstock' for r in rows):
            kinds['an option out of stock'] += 1
        problems += [f'{product["url"]}  {w}' for w in page_problems(slug, product)]
finally:
    if server.ours():
        server.stop()

problems += case_problems()

print(f'{len(FACTS["products"])} product pages: ' + ', '.join(f'{n} with {k}' for k, n in kinds.items()))
print('built in memory: ' + ', '.join(FACTS['cases']))
print()

if problems:
    print(f'{len(problems)} problem(s):')
    for p in problems:
        print('  ' + p.encode('ascii', 'backslashreplace').decode('ascii'))
    sys.exit(1)

print(f'all {len(FACTS["products"])} product pages offer what their options are')
