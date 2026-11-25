"""A size nobody can buy is not offered, listed or named.

    python .data/check_sold_terms.py

Needs the local site on http://localhost:8124 (ARGFLEX_BASE); starts one of
its own when nothing answers there, and stops it again. Reads only.

The car heater hose said "Length: 1m, 20m, 50m" and was sold in 1m and 50m.
WooCommerce had kept the term when the 20m variation was deleted, and the
import brought both across. So its page had a 20m button that selected
nothing, its specification said 20m, and it stood on /length/20m/ with
nothing at 20m to buy. Found on the live site on 2026-11-25; it had been
there since the move.

The public catalogue now drops a chosen-by term that no option carries
(product_sold_terms() in inc/config.php, applied by all_products()). The
admin still reads the stored record, which is where the owner mends it.

  1 the rule, on made-up records (.data/sold_terms_facts.php "cases")
      an orphan term goes; a fixed specification row is left alone; so is a
      product with no options, an axis where an option says "any", and an
      axis none of whose terms is carried.

  2 the catalogue
      for every published product, every term the public record shows on a
      chosen-by axis is carried by an option (or the axis is one of the
      exceptions above), and nothing else about the record changed.

  3 the product page
      every button and list entry in a chosen-by row is a value some option
      carries, and so is every value the specification row names.

  4 the size listings
      every product on /inner-diameter/<term>/ and /length/<term>/ that is
      chosen by that axis has an option at that term.
"""
import html
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from devserver import DevServer

BASE = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PHP = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))

problems = []


def facts():
    out = subprocess.run([PHP, os.path.join('.data', 'sold_terms_facts.php')], cwd=ROOT,
                         capture_output=True, text=True, encoding='utf-8', errors='replace')
    try:
        return json.loads(out.stdout)
    except ValueError:
        print('sold_terms_facts.php did not answer with JSON:\n' + (out.stdout + out.stderr)[:600])
        sys.exit(1)


def fetch(path):
    req = urllib.request.Request(BASE + path, headers={'User-Agent': 'argflex-check'})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, str(e)


FACTS = facts()

# ------------------------------------------------------------ 1: the rule
for case in FACTS['cases']:
    if case['got'] != case['want']:
        problems.append(f"rule, {case['name']}: kept {case['got']}, should keep {case['want']}")

# ------------------------------------------------------- 2: the catalogue
dropped = []
by_slug = {}
for p in FACTS['products']:
    by_slug[p['slug']] = p
    if not p['same_as_stored_otherwise']:
        problems.append(f"{p['slug']}: the public record differs from the stored one in more than its terms")
    for axis in p['axes']:
        carried = set(axis['carried'])
        exempt = p['options'] == 0 or axis['any'] or not (carried & set(axis['stored']))
        if exempt:
            if axis['shown'] != axis['stored']:
                problems.append(f"{p['slug']}, {axis['name']}: terms were dropped where none should be")
            continue
        orphans = [t for t in axis['shown'] if t not in carried]
        if orphans:
            problems.append(f"{p['slug']}, {axis['name']}: shows {orphans}, which no option carries")
        lost = [t for t in axis['stored'] if t in carried and t not in axis['shown']]
        if lost:
            problems.append(f"{p['slug']}, {axis['name']}: {lost} is sold and is not shown")
        gone = [t for t in axis['stored'] if t not in axis['shown']]
        if gone:
            dropped.append(f"{p['slug']}: {axis['name']} {', '.join(gone)}")

# ---------------------------------------------------------- the pages
ROW = re.compile(r'<div class="sw-row" data-attr="([^"]*)"([^>]*)>(.*?)(?=<div class="sw-row"|</form>)', re.S)
SPEC = re.compile(r'<tr><th>([^<]*)</th>\s*<td>(.*?)</td>', re.S)


def plain(fragment):
    return [html.unescape(re.sub(r'<[^>]+>', '', part)).strip()
            for part in re.split(r',(?![^<]*>)', fragment)]


server = DevServer(PHP, BASE)
if not server.alive() and not server.start():
    print('the local site does not answer on ' + BASE)
    sys.exit(1)

counted = {'pages': 0, 'buttons': 0, 'spec': 0, 'listings': 0, 'cards': 0}
try:
    # ---- 3: the product page
    for p in FACTS['products']:
        chosen = {a['name']: a for a in p['axes']}
        if not chosen or p['options'] == 0:
            continue
        status, page = fetch('/product/' + p['slug'] + '/')
        if status != 200:
            problems.append(f"/product/{p['slug']}/ answers {status}")
            continue
        counted['pages'] += 1

        for name, rest, body in ROW.findall(page):
            name = html.unescape(name)
            if 'data-family-only' in rest or name not in chosen:
                continue
            axis = chosen[name]
            carried = set(axis['carried'])
            if axis['any'] or not (carried & set(axis['stored'])):
                continue
            body = re.sub(r'<noscript>.*?</noscript>', '', body, flags=re.S)
            values = re.findall(r'<button[^>]*data-value="([^"]*)"', body)
            values += [v for v in re.findall(r'<option value="([^"]*)"', body) if not v.startswith('go:')]
            if not values:
                problems.append(f"/product/{p['slug']}/: the {name} row offers nothing")
            for v in values:
                counted['buttons'] += 1
                if html.unescape(v) not in carried:
                    problems.append(f"/product/{p['slug']}/: {name} offers {v}, which no option carries")

        for label, cell in SPEC.findall(page):
            label = html.unescape(label).strip()
            if label not in chosen:
                continue
            axis = chosen[label]
            carried = set(axis['carried'])
            if axis['any'] or not (carried & set(axis['stored'])):
                continue
            allowed = set(axis['names'])
            for value in plain(cell):
                if value == '':
                    continue
                counted['spec'] += 1
                if value not in allowed:
                    problems.append(f"/product/{p['slug']}/: the specification says {label} {value}, "
                                    f"and the options are {sorted(allowed)}")

    # ---- 4: the size listings
    axis_name = FACTS['axis_names']
    for key, slugs in FACTS['listings'].items():
        attribute, term = key.split('/', 1)
        for slug in slugs:
            p = by_slug.get(slug)
            if not p:
                continue
            for axis in p['axes']:
                if axis['name'] != axis_name.get(attribute):
                    continue
                counted['cards'] += 1
                carried = set(axis['carried'])
                if p['options'] and not axis['any'] and (carried & set(axis['stored'])) and term not in carried:
                    problems.append(f"/{key}/ lists {slug}, which has no option at {term}")
        if len(slugs) < 2:
            continue                                  # one product: the address redirects to it
        status, page = fetch('/' + key + '/')
        if status != 200:
            problems.append(f'/{key}/ answers {status}')
            continue
        counted['listings'] += 1
        for slug in slugs:
            if '/product/' + slug + '/' not in page:
                problems.append(f'/{key}/ does not show {slug}')
finally:
    if server.ours():
        server.stop()

print(f"{len(FACTS['cases'])} made-up records, {len(FACTS['products'])} products, {counted['pages']} product pages "
      f"({counted['buttons']} choices, {counted['spec']} specification values), "
      f"{counted['listings']} size listings ({counted['cards']} chosen-by places)")
for line in dropped:
    print('  not sold, so not shown: ' + line)

if problems:
    print(f'\n{len(problems)} problem(s)')
    for p in problems[:80]:
        try:
            print('  ' + p)
        except UnicodeEncodeError:
            print('  ' + p.encode('ascii', 'replace').decode())
    sys.exit(1)

print('every size a page offers, names or is listed under can be bought')
