"""Compare the attribute archives against the live site.

    python .data/check_attribute_pages.py

Thirty-five of these are indexed as /inner-diameter/<size>/ and
/length/<size>/. They used to 404 here, which is the ordinary way a
migration loses its rankings. This asks the live site what each one
answers and holds ours to it: the same status, the same title, the same
canonical, and no meta description, because live sets none.

It reads the live site over the network. Pass --offline to check only that
ours resolve and are internally consistent.

It also holds four things the pages got wrong for months without any check
noticing, each worked out here from the raw catalogue (.data/attribute_facts.php)
rather than by asking the code what it thinks:

  * the "from" price is the cheapest option that really carries the bore or
    the length, and says "per metre" only when that option is 1 m; an option
    that is sold out is passed over unless every option of the size is (no
    local listing has one, so two are built in memory by attribute_facts.php);
  * a listing is indexable, and in sitemap.xml, only with three or more
    products and a product set no category and no earlier listing already
    shows — every other one says "noindex, follow" and is not in the sitemap;
  * the BreadcrumbList has a URL on every step but the last;
  * no page links to a listing that 301s to a single product.
"""
import html
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

BASE = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
LIVE = 'https://argflex.co.uk'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PHP = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))
OFFLINE = '--offline' in sys.argv


def urls():
    """Every archive URL the catalogue implies, and where it should lead.

    Twelve of the thirty-five list a single product and now 301 to it — a page
    describing one item in less detail than the item's own page is worth less
    than the redirect to it. So each row is (path, product URL or ''), and an
    empty second value means the archive is still a page. Asked of the same
    function the routing uses, so the two cannot drift apart.
    """
    return [(t['path'], t['lone']) for t in FACTS['terms']]


def catalogue():
    """The raw catalogue, from .data/attribute_facts.php."""
    out = subprocess.run([PHP, os.path.join('.data', 'attribute_facts.php')], cwd=ROOT,
                         capture_output=True, text=True, encoding='utf-8', errors='replace')
    try:
        return json.loads(out.stdout)
    except ValueError:
        print('attribute_facts.php did not answer with JSON:\n' + (out.stdout + out.stderr)[:600])
        sys.exit(1)


FACTS = catalogue()


def expected_from(term):
    """(pence, metres) of the cheapest option carrying this term, or None.

    Worked out from the option rows, the ones the product page prints under
    "Price per option". A product that fixes the term as a plain spec row
    (the SAE J30 R6 pages and their bore) matches with every option.
    """
    return cheapest_of(term, [FACTS['products'][slug] for slug in term['products']])


def cheapest_of(term, products):
    """The same, over the products given.

    The cheapest option that can be BOUGHT: one that is sold out is passed
    over, and counts only when every option of the size is sold out — "from
    £0.76" off an option nobody can order is not a price.
    """
    best = {'open': None, 'any': None}
    for product in products:
        varies = product['varies'].get(term['axis'], False)
        for option in product['options']:
            if varies and option['attrs'].get(term['axis']) != term['slug']:
                continue
            if option['pence'] <= 0:
                continue
            name = FACTS['lengths'].get(option['attrs'].get(FACTS['length_name'], ''), '')
            m = re.match(r'^([\d.]+)\s*m$', name, re.I)
            row = (option['pence'], float(m.group(1)) if m else 0.0)
            gone = option['flag'] == 'outofstock' or option['state'] == 'out'
            for pool in (['any'] if gone else ['open', 'any']):
                if best[pool] is None or row < best[pool]:
                    best[pool] = row
    return best['open'] or best['any']


def case_problems():
    """The two listings built in memory with sold-out options (see
    attribute_facts.php): what the site said of each, against the rule above."""
    wrong = []
    cases = FACTS.get('cases') or {}          # PHP sends an empty one as []
    for name in ('cheapest_sold_out', 'all_sold_out'):
        case = cases.get(name)
        if not case:
            wrong.append(f'case {name}: no listing whose cheapest option could be marked sold out '
                         'with a dearer one left to buy')
            continue
        want = cheapest_of(case['term'], case['products'])
        said = (case['said']['pence'], case['said']['metres']) if case['said'] else None
        if said != want:
            wrong.append(f"case {name} ({case['term']['path']}): the listing would quote {said}, "
                         f'the cheapest option that can be bought is {want}')
        gone = [o for p in case['products'] for o in p['options']
                if o['flag'] == 'outofstock' or o['state'] == 'out']
        if not gone:
            wrong.append(f'case {name}: nothing in it is sold out, so it tests nothing')
    return wrong


def expected_indexable():
    """Which listings may be indexed — the rule, written out a second time.

    Three products or more, and a product set that is not a category's and
    not that of a listing earlier in data/attributes.php order.
    """
    taken = {frozenset(slugs) for slugs in FACTS['categories'].values()}
    verdict = {}
    for term in FACTS['terms']:
        mine = frozenset(term['products'])
        verdict[term['path']] = len(mine) >= FACTS['minimum'] and mine not in taken
        taken.add(mine)
    return verdict


def crumb_problems(body):
    """What is wrong with the page's BreadcrumbList, as Google reads it."""
    wrong, found = [], False
    for block in re.findall(r'<script type="application/ld\+json">(.*?)</script>', body, re.S):
        try:
            data = json.loads(block)
        except ValueError:
            wrong.append('a JSON-LD block does not parse')
            continue
        for node in (data if isinstance(data, list) else [data]):
            if not isinstance(node, dict) or node.get('@type') != 'BreadcrumbList':
                continue
            found = True
            items = node.get('itemListElement', [])
            for n, item in enumerate(items):
                if item.get('position') != n + 1:
                    wrong.append(f'breadcrumb step {n + 1} is numbered {item.get("position")}')
                # every step but the last needs somewhere to go
                if n < len(items) - 1 and not item.get('item'):
                    wrong.append(f'breadcrumb step {n + 1} ({item.get("name")!r}) has no URL')
    if not found:
        wrong.append('no BreadcrumbList')
    return wrong


def hrefs(body):
    return set(re.findall(r'href="(/[^"#?]*)', body))


def fetch(base, path):
    req = urllib.request.Request(base + path, headers={'User-Agent': 'argflex-parity-check'})
    try:
        with urllib.request.urlopen(req, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, str(e)


def fetch_no_follow(base, path):
    """Status and Location, without following — here the redirect IS the answer."""
    class Still(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None

    op  = urllib.request.build_opener(Still)
    req = urllib.request.Request(base + path, headers={'User-Agent': 'argflex-parity-check'})
    try:
        with op.open(req, timeout=40) as r:
            return r.status, ''
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location', '')
    except Exception:
        return 0, ''


def facts(body):
    title = re.search(r'<title>(.*?)</title>', body, re.S)
    desc = re.search(r'<meta name="description" content="(.*?)"', body, re.S)
    robots = re.search(r'<meta name="robots" content="(.*?)"', body)
    head = re.search(r'<section class="pg-head">(.*?)</section>', body, re.S)
    canon = re.search(r'<link rel="canonical" href="(.*?)"', body)
    h1 = re.search(r'<h1[^>]*>(.*?)</h1>', body, re.S)
    return {
        'title': html.unescape(title.group(1)).strip() if title else '',
        'description': html.unescape(desc.group(1)).strip() if desc else '',
        'canonical': canon.group(1) if canon else '',
        'robots': robots.group(1) if robots else '',
        # the heading block: the count line and the lede that quotes the price
        'head': ' '.join(re.sub(r'<[^>]+>', ' ', html.unescape(head.group(1))).split()) if head else '',
        'h1': re.sub(r'<[^>]+>', '', html.unescape(h1.group(1))).strip() if h1 else '',
    }


rows  = urls()
paths = [p for p, _ in rows]
print(f'{len(rows)} archive URL(s) from the catalogue '
      f'({sum(1 for _, lone in rows if lone)} of them redirect to a single product)\n')

problems = case_problems()
TERMS = {t['path']: t for t in FACTS['terms']}
INDEXABLE = expected_indexable()
LONE = {p for p, lone in rows if lone}

# The site's own verdict against the rule written out above. They are two
# workings of one rule; if they part, one of them has been changed alone.
for t in FACTS['terms']:
    if t['indexable'] != INDEXABLE[t['path']]:
        problems.append(f'{t["path"]}  attribute_term_indexable() says '
                        f'{"index" if t["indexable"] else "noindex"}, the rule says '
                        f'{"index" if INDEXABLE[t["path"]] else "noindex"}')

# A generated sentence stamped on 25 pages is the duplication these archives
# were meant to escape, so identical titles and descriptions are a failure.
seen = {}
for path, lone in rows:

    # An archive listing ONE product redirects to it. The live site still
    # serves the archive, so this is a deliberate divergence from live and the
    # title and canonical checks below would rightly disagree. What is checked
    # instead: the redirect exists, points where the catalogue says it should,
    # and lands on a page rather than another redirect or a 404.
    if lone:
        status, where = fetch_no_follow(BASE, path)
        if status != 301:
            problems.append(f'{path}  answered {status}, expected 301 to {lone}')
            print('  !', path, f'{status}, not 301')
        elif where != lone:
            problems.append(f'{path}  301s to {where!r}, expected {lone!r}')
            print('  !', path, 'redirects to the wrong product')
        else:
            landed, _ = fetch(BASE, where)
            if landed != 200:
                problems.append(f'{path}  301s to {where}, which answers {landed}')
                print('  !', path, f'lands on {landed}')
            else:
                print('  .', path, '-> 301')
        continue

    status, body = fetch(BASE, path)
    mine = facts(body)

    if status != 200:
        problems.append(f'{path}  ours answered {status}')
        print('  !', path, status)
        continue

    # A page can answer 200, carry the right title, and still be broken below
    # the fold. That is exactly what happened: an int passed to a function
    # wanting a string killed every one of these pages after the heading, and
    # this check said they all matched because it only ever read the title.
    fatal = re.search(r'(Fatal error|Parse error|Uncaught \w+)[^<]{0,120}', body)
    if fatal:
        problems.append(f'{path}  PHP {fatal.group(0).strip()[:100]}')
        print('  !', path, 'PHP error')
        continue
    if '</html>' not in body:
        problems.append(f'{path}  the page stops before it closes — something died mid-render')
        print('  !', path, 'truncated')
        continue

    # These used to be held byte-identical to WordPress: bare-term title, no
    # meta description. That carried the rankings across the migration and
    # then stopped paying — 23 indexed pages earning nothing, because a title
    # that is only a number matches no commercial search. The term still leads
    # the title, so whatever they match on survives; what is checked now is
    # that the additions are real rather than stamped.
    if not mine['description']:
        problems.append(f'{path}  no meta description — Google will quote the navigation')
    elif len(mine['description']) < 60:
        problems.append(f'{path}  meta description is only {len(mine["description"])} chars')

    want_canonical = LIVE + path
    if mine['canonical'] != want_canonical:
        problems.append(f'{path}  canonical is {mine["canonical"]!r}, expected {want_canonical!r}')

    term = path.strip('/').split('/')[-1]
    if not mine['title'].endswith(' - argflex.co.uk'):
        problems.append(f'{path}  title {mine["title"]!r} lost the site suffix')
    if mine['h1'] != mine['title'].replace(' - argflex.co.uk', ''):
        problems.append(f'{path}  h1 {mine["h1"]!r} does not match the title')
    if 'cut to length' not in mine['title'].lower():
        problems.append(f'{path}  title {mine["title"]!r} does not say what is being sold')

    # --- the price it quotes --------------------------------------------
    # Thirteen of the fifteen bore pages quoted the cheapest option of some
    # OTHER bore, and called a 20 m coil "per metre". Every pound figure in
    # the description and the heading block must be the one worked out here.
    want = expected_from(TERMS[path])
    for where, text in (('description', mine['description']), ('heading block', mine['head'])):
        quoted = re.findall(r'from (\S*\d[\d.,]*)', text, re.I)
        if want is None:
            if quoted:
                problems.append(f'{path}  {where} quotes {quoted[0]} but no option of this size has a price')
            continue
        pence, metres = want
        price = FACTS['money'][str(pence)]
        if not quoted:
            problems.append(f'{path}  {where} quotes no price; the cheapest option is {price}')
        elif any(q.rstrip('.,') != price for q in quoted):
            problems.append(f'{path}  {where} says from {quoted[0]}, the cheapest option '
                            f'of this size is {price}')
        if ('per metre' in text.lower()) != (metres == 1.0):
            problems.append(f'{path}  {where} '
                            + ('does not say "per metre" for a 1 m option' if metres == 1.0 else
                               f'says "per metre" but the cheapest option is {metres:g} m long'))

    # --- may it be indexed ------------------------------------------------
    said_no = 'noindex' in mine['robots'].lower()
    if INDEXABLE[path] and said_no:
        problems.append(f'{path}  is noindex but has {len(TERMS[path]["products"])} products '
                        'and mirrors nothing')
    if not INDEXABLE[path] and not said_no:
        problems.append(f'{path}  is thin or a duplicate ({len(TERMS[path]["products"])} products) '
                        f'and should be "noindex, follow"; robots is {mine["robots"]!r}')
    if not INDEXABLE[path] and 'nofollow' in mine['robots'].lower():
        problems.append(f'{path}  is nofollow — a noindex listing must still pass its links')

    # --- the breadcrumb, and where its links go ---------------------------
    for wrong in crumb_problems(body):
        problems.append(f'{path}  {wrong}')
    for link in sorted(hrefs(body) & LONE):
        problems.append(f'{path}  links to {link}, which only redirects to a product')

    seen.setdefault(mine['description'], []).append(path)
    seen.setdefault(mine['title'], []).append(path)

    if OFFLINE:
        sys.stdout.write('.')
        sys.stdout.flush()
        continue

    live_status, _ = fetch(LIVE, path)
    if live_status != 200:
        problems.append(f'{path}  live answered {live_status} — is this URL still real?')

    sys.stdout.write('.')
    sys.stdout.flush()

print('\n')

# The sitemap offers exactly the indexable listings: no redirect, no noindex
# page, and none of the good ones forgotten.
status, sitemap = fetch(BASE, '/sitemap.xml')
if status != 200:
    problems.append(f'/sitemap.xml  answered {status}')
else:
    offered = {loc[len(LIVE):] for loc in re.findall(r'<loc>(.*?)</loc>', sitemap)
               if re.match(re.escape(LIVE) + r'/(inner-diameter|length)/', loc)}
    should = {p for p, ok in INDEXABLE.items() if ok}
    for p in sorted(offered - should):
        problems.append(f'{p}  is in sitemap.xml but is '
                        + ('a redirect' if p in LONE else 'noindex'))
    for p in sorted(should - offered):
        problems.append(f'{p}  is indexable and missing from sitemap.xml')

# Nothing else on the site links to a listing that only redirects. The spec
# table on every product page did, 178 times over, twelve of them to itself.
for page in [p['url'] for p in FACTS['products'].values()] + FACTS['posts']:
    status, body = fetch(BASE, page)
    if status != 200:
        continue        # crawl.py's business, not this check's
    for link in sorted(hrefs(body) & LONE):
        problems.append(f'{page}  links to {link}, which only redirects to a product')

kept = [p for p, ok in INDEXABLE.items() if ok]
print(f'indexable ({len(kept)}): ' + ' '.join(kept))
print(f'noindex, still served ({len(INDEXABLE) - len(kept) - len(LONE)}): '
      + ' '.join(p for p, ok in INDEXABLE.items() if not ok and p not in LONE))
print()

for text, where in seen.items():
    if len(where) > 1 and text:
        problems.append('the same line on %d archives: %r\n        %s'
                        % (len(where), text[:70], ', '.join(where)))

if problems:
    print(f'{len(problems)} problem(s):')
    for p in problems:
        print('  ' + p)
    sys.exit(1)

print(f'all {len(paths)} archives match the live site'
      + (' (titles checked offline)' if OFFLINE else ''))
