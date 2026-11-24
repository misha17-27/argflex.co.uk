"""Hold the three link blocks to what they are for.

    python .data/check_link_blocks.py

Needs the local site on http://localhost:8124 (ARGFLEX_BASE); starts one of
its own when nothing answers there, and stops it again. Reads nothing from
the live site and writes nothing anywhere.

Three blocks hand out most of the links between pages, and for as long as the
shop has been up each of them printed the same thing everywhere:

  * "Related products" on a product page was the first four hoses of the
    category in catalogue order: fifteen products of thirty-seven were linked
    from no block, and two of the four were bores the size picker on the same
    page already links;
  * "More from the blog" on an article was the three newest articles: three
    of them had nineteen links each and sixteen had none;
  * a product page linked no article at all.

So this reads every product page and every article as served, and checks,
against the raw catalogue that .data/link_facts.php hands over:

  related products (section#related-products)
    - never the product itself, never a sibling its bore picker links, never
      the same product twice, and only products that share a category;
    - as many as the block holds (four) or as many as there are to show;
    - the hoses nearest to it come first: where the same primary category
      holds other models, up to three different ones are shown;
    - ACROSS THE CATALOGUE every product that some page could show is shown
      by at least one;

  guides (section#product-guides)
    - present exactly when an article refers to the product or to another
      listing of the same model, with up to four cards, each such an article;
    - an article that is about this very listing is never left out for one
      that only mentions it;

  more from the blog (section#more-articles)
    - three other articles, never the one being read, never one twice;
    - the first is the article with the most in common, worked out here
      again from what each article refers to;
    - ACROSS THE BLOG every article is offered by at least two others;

  the hose an article names
    - an article titled after one bore of a hose sold in several links THAT
      bore's page wherever it links the standard by name;

  and every block is the same on a second request: nothing in it is drawn at
  random, so a cache and a crawler see one page.
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

BLOCK = 4          # cards in "Related products" and in "Guides for this hose"
NEAREST = 3        # of which this many go to the nearest models
MORE = 3           # cards in "More from the blog"
WANTED = 2         # blocks that should offer each article


def facts():
    out = subprocess.run([PHP, os.path.join('.data', 'link_facts.php')], cwd=ROOT,
                         capture_output=True, text=True, encoding='utf-8', errors='replace')
    try:
        return json.loads(out.stdout)
    except ValueError:
        print('link_facts.php did not answer with JSON:\n' + (out.stdout + out.stderr)[:600])
        sys.exit(1)


FACTS = facts()
PRODUCTS = FACTS['products']
POSTS = FACTS['posts']
BY_URL = {p['url']: slug for slug, p in PRODUCTS.items()}
POST_BY_URL = {p['url']: slug for slug, p in POSTS.items()}


def fetch(path):
    req = urllib.request.Request(BASE + path, headers={'User-Agent': 'argflex-check'})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, str(e)


def section(page, name):
    """The markup of <section id="name">, or '' when the page has none."""
    m = re.search(r'<section\b[^>]*\bid="' + re.escape(name) + r'"[^>]*>(.*?)</section>', page, re.S)
    return m.group(1) if m else ''


def in_order(found):
    out = []
    for item in found:
        if item not in out:
            out.append(item)
    return out


def product_links(fragment):
    return in_order(html.unescape(h) for h in re.findall(r'href="(/product/[^"/?#]+/)"', fragment))


def post_cards(fragment):
    return [html.unescape(h) for h in re.findall(r'<a class="post" href="([^"]+)"', fragment)]


def group(slug):
    """The model a listing belongs to: its family, else the shortest slug it
    is a renamed copy of, else itself. Worked out here, not asked for."""
    p = PRODUCTS[slug]
    if p['family']:
        return 'family:' + p['family']
    stem = slug
    for other, q in PRODUCTS.items():
        if not q['family'] and len(other) < len(stem) and stem.startswith(other + '-'):
            stem = other
    return stem


GROUP = {slug: group(slug) for slug in PRODUCTS}


def could_show(page_slug):
    """Every product the related block of this page is allowed to hold."""
    page = PRODUCTS[page_slug]
    return [slug for slug, p in PRODUCTS.items()
            if slug != page_slug and p['url'] not in page['picker']
            and set(p['cats']) & set(page['cats'])]


def affinity(a, b):
    ta, tb = POSTS[a]['topics'], POSTS[b]['topics']
    same_products = len(set(ta['products']) & set(tb['products']))
    same_groups = len(set(ta['groups']) & set(tb['groups']))
    return 4 * same_products + 3 * max(0, same_groups - same_products) \
        + 2 * len(set(ta['cats']) & set(tb['cats']))


def bore_number(label):
    m = re.search(r'\d+(?:[.,]\d+)?', label)
    return round(float(m.group(0).replace(',', '.')) * 100) if m else None


def named_listing(post):
    """(standard, product slug) when the title names a standard and exactly one
    bore, and one listing meeting that standard is that bore alone."""
    title = post['title']
    codes = re.findall(r'\b(SAE\s?J\d+\s?R\d+)\b', title, re.I)
    bores = {round(float(n.replace(',', '.')) * 100)
             for n in re.findall(r'(?<![\d.,])(\d+(?:[.,]\d+)?)\s*mm\b', title, re.I)}
    if len(codes) != 1 or len(bores) != 1:
        return None
    code = re.sub(r'\s+', ' ', codes[0].upper())
    wanted = next(iter(bores))
    pattern = re.compile(r'\b' + r'\s*'.join(re.escape(w) for w in code.split()) + r'\b', re.I)
    for slug, p in PRODUCTS.items():
        if pattern.search(p['name']) and [bore_number(b) for b in p['bores']] == [wanted]:
            return code, slug
    return None


server = DevServer(PHP, base=BASE)
if not server.alive() and not server.start():
    print(f'{BASE} is not responding and could not be started')
    sys.exit(1)

problems = []
inbound = {slug: 0 for slug in PRODUCTS}
post_inbound = {slug: 0 for slug in POSTS}
with_guides = named = 0

try:
    # ------------------------------------------------------------ products
    for slug, product in PRODUCTS.items():
        status, page = fetch(product['url'])
        if status != 200:
            problems.append(f"{product['url']}: HTTP {status}")
            continue
        again = fetch(product['url'])[1]

        block = section(page, 'related-products')
        if block != section(again, 'related-products'):
            problems.append(f"{product['url']}: the related block changed between two requests")
        shown = [BY_URL.get(u, u) for u in product_links(block)]
        allowed = could_show(slug)

        for other in shown:
            if other == slug:
                problems.append(f"{product['url']}: offers itself as a related product")
            elif other not in PRODUCTS:
                problems.append(f"{product['url']}: related block links {other}, which is no product")
            elif PRODUCTS[other]['url'] in product['picker']:
                problems.append(f"{product['url']}: related block repeats {other}, which the bore picker links")
            elif other not in allowed:
                problems.append(f"{product['url']}: related block shows {other}, which shares no category")
            else:
                inbound[other] += 1
        if len(shown) != min(BLOCK, len(allowed)):
            problems.append(f"{product['url']}: related block has {len(shown)} products, "
                            f"expected {min(BLOCK, len(allowed))}")

        near = {GROUP[o] for o in allowed
                if product['primary'] and PRODUCTS[o]['primary'] == product['primary']
                and GROUP[o] != GROUP[slug]}
        near_shown = {GROUP[o] for o in shown if o in PRODUCTS and GROUP[o] in near}
        if len(near_shown) < min(NEAREST, len(near), len(shown)):
            problems.append(f"{product['url']}: shows {len(near_shown)} of the {len(near)} other models in "
                            f"its own category, expected {min(NEAREST, len(near))}")

        # guides
        me = GROUP[slug]
        about = [s for s, a in POSTS.items() if a['topics']['products'][:1] == [slug]]
        refer = [s for s, a in POSTS.items()
                 if slug in a['topics']['products'] or me in a['topics']['groups']]
        guides_again = section(again, 'product-guides')
        guides = section(page, 'product-guides')
        if guides != guides_again:
            problems.append(f"{product['url']}: the guides block changed between two requests")
        cards = [POST_BY_URL.get(u, u) for u in post_cards(guides)]
        if not refer and guides:
            problems.append(f"{product['url']}: a guides block with no article that refers to it")
        if refer:
            with_guides += 1
            if len(cards) != min(BLOCK, len(refer)):
                problems.append(f"{product['url']}: {len(cards)} guides shown, expected {min(BLOCK, len(refer))}")
            if len(set(cards)) != len(cards):
                problems.append(f"{product['url']}: the same guide twice")
            for card in cards:
                if card not in refer:
                    problems.append(f"{product['url']}: guide {card} does not refer to this hose")
            for article in about[:BLOCK]:
                if len(about) <= BLOCK and article not in cards:
                    problems.append(f"{product['url']}: {article} is about this hose and is not among its guides")
            if 'Guides for this hose' not in guides:
                problems.append(f"{product['url']}: the guides block lost its heading")

    for slug, count in inbound.items():
        if count == 0 and any(slug in could_show(page) for page in PRODUCTS):
            problems.append(f"{PRODUCTS[slug]['url']}: no related block anywhere links it")

    # ------------------------------------------------------------ articles
    for slug, post in POSTS.items():
        status, page = fetch(post['url'])
        if status != 200:
            problems.append(f"{post['url']}: HTTP {status}")
            continue
        block = section(page, 'more-articles')
        if block != section(fetch(post['url'])[1], 'more-articles'):
            problems.append(f"{post['url']}: the article block changed between two requests")

        cards = [POST_BY_URL.get(u, u) for u in post_cards(block)]
        others = [s for s in POSTS if s != slug]
        if len(cards) != min(MORE, len(others)):
            problems.append(f"{post['url']}: {len(cards)} articles offered, expected {min(MORE, len(others))}")
        if slug in cards:
            problems.append(f"{post['url']}: offers itself as further reading")
        if len(set(cards)) != len(cards):
            problems.append(f"{post['url']}: the same article twice")
        for card in cards:
            if card in post_inbound:
                post_inbound[card] += 1
            else:
                problems.append(f"{post['url']}: offers {card}, which is no article")
        if cards and cards[0] in POSTS and others:
            best = max(affinity(slug, o) for o in others)
            if affinity(slug, cards[0]) < best:
                problems.append(f"{post['url']}: leads with {cards[0]} ({affinity(slug, cards[0])}) "
                                f"while another article has {best} in common")

        target = named_listing(post)
        if target:
            named += 1
            code, wanted = target
            anchors = re.findall(r'<a href="(/product/[^"]+/)">\s*'
                                 + r'\s*'.join(re.escape(w) for w in code.split()) + r'\s*</a>', page, re.I)
            for href in anchors:
                if html.unescape(href) != PRODUCTS[wanted]['url']:
                    problems.append(f"{post['url']}: is titled after {wanted} and links \"{code}\" to {href}")
            if PRODUCTS[wanted]['url'] not in product_links(page):
                problems.append(f"{post['url']}: is titled after {wanted} and never links it")

    if len(POSTS) > MORE:
        for slug, count in post_inbound.items():
            if count < WANTED:
                problems.append(f"{POSTS[slug]['url']}: offered by {count} other article(s), expected {WANTED}")
finally:
    if server.ours():
        server.stop()

spread = sorted(inbound.values())
post_spread = sorted(post_inbound.values())
print(f'{len(PRODUCTS)} product pages, {len(POSTS)} articles')
print(f'  related block: each product linked from {spread[0]} to {spread[-1]} pages' if spread else '')
print(f'  article block: each article offered by {post_spread[0]} to {post_spread[-1]} others' if post_spread else '')
print(f'  guides block on {with_guides} product pages; {named} article(s) titled after one bore')

if problems:
    print(f'\n{len(problems)} problem(s)')
    for p in problems[:60]:
        print('  ' + p)
    sys.exit(1)

print('every link block is chosen for its page and nobody is left out')
