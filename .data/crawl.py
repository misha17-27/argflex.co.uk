"""Walk every internal URL of the local PHP site and report problems."""
import json, os, re, subprocess, sys, collections, pathlib

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))

products   = json.load(open(os.path.join(ROOT, '.data/products.json'),   encoding='utf-8'))
categories = json.load(open(os.path.join(ROOT, '.data/categories.json'), encoding='utf-8'))
posts      = json.load(open(os.path.join(ROOT, '.data/posts.json'),      encoding='utf-8'))

cat_by_id = {c['id']: c for c in categories}
def cat_path(c):
    p = cat_by_id.get(c['parent'])
    return f"{p['slug']}/{c['slug']}" if p else c['slug']

urls = ['/', '/shop/', '/blog/', '/about-us/', '/contacts/', '/cart/', '/wishlist/',
        '/compare/', '/refund_returns/', '/my-account/',
        '/shop/?q=oxygen', '/shop/?cat=rubber-hoses', '/shop/?sort=price-asc',
        '/shop/?q=zzzznothing', '/compare/?a=oxygen-hose-agoma&b=acetylene-hose',
        '/contacts/?product=asfa-clamps', '/definitely-not-a-real-page/',
        '/checkout/', '/checkout/?ok=260101-ABCDEF', '/sitemap.xml', '/robots.txt',
        '/wishlist-items.php?slugs=acetylene-hose', '/cross-sells.php?slugs=acetylene-hose',
        '/cross-sells.php?slugs=']
urls += [f"/product-category/{cat_path(c)}/" for c in categories]
urls += [f"/product/{p['slug']}/" for p in products]
urls += [f"/{p['slug']}/" for p in posts]

def fetch(url):
    r = subprocess.run(['curl', '-sS', '-o', '-', '-w', '\n@@@%{http_code}@@@',
                        '--max-time', '30', BASE + url],
                       capture_output=True, text=True, encoding='utf-8', errors='replace')
    body = r.stdout or ''
    m = re.search(r'@@@(\d+)@@@\s*$', body)
    code = int(m.group(1)) if m else 0
    return code, body[:m.start()] if m else body

problems, results = [], []
for url in urls:
    code, html = fetch(url)
    row = {'url': url, 'code': code, 'bytes': len(html)}

    expect = 404 if 'not-a-real-page' in url else 200
    if code != expect:
        problems.append(f'{url} -> HTTP {code} (expected {expect})')

    for pat, label in [(r'Fatal error', 'PHP fatal'), (r'Parse error', 'PHP parse error'),
                       (r'Warning</b>|<b>Warning', 'PHP warning'),
                       (r'Notice</b>|<b>Notice', 'PHP notice'),
                       (r'Deprecated</b>', 'PHP deprecated')]:
        if re.search(pat, html):
            snippet = re.sub(r'\s+', ' ', re.search(r'.{0,180}' + pat + r'.{0,180}', html, re.S).group(0))
            problems.append(f'{url} -> {label}: {snippet[:240]}')

    # The root endpoints return an HTML fragment for the page to drop in, not
    # a document, so asking them for a title or a single h1 is meaningless.
    # A PHP error in them is still caught above, which is the point of
    # crawling them at all.
    fragment = url.startswith(('/wishlist-items.php', '/cross-sells.php'))
    if url in ('/sitemap.xml', '/robots.txt') or fragment:
        results.append(row); sys.stdout.write('.'); sys.stdout.flush(); continue

    title = re.search(r'<title>(.*?)</title>', html, re.S)
    row['title'] = (title.group(1).strip() if title else '')
    if not row['title']:
        problems.append(f'{url} -> missing <title>')

    h1 = re.findall(r'<h1[^>]*>(.*?)</h1>', html, re.S)
    row['h1'] = len(h1)
    if code == 200 and len(h1) != 1:
        problems.append(f'{url} -> {len(h1)} <h1> tags (expected 1)')

    # local asset references that do not exist on disk
    for src in set(re.findall(r'(?:src|href)="(/assets/[^"?]+)', html)):
        if not os.path.isfile(os.path.join(ROOT, src.lstrip('/'))):
            problems.append(f'{url} -> missing asset {src}')

    row['links'] = len(set(re.findall(r'href="(/[^"#]*)"', html)))
    results.append(row)
    sys.stdout.write('.')
    sys.stdout.flush()

print()

# every internal link must resolve
all_links = set()
for url in urls:
    code, html = fetch(url)
    for href in re.findall(r'href="(/[^"#]*)"', html):
        all_links.add(href.split('?')[0])
checked = {}
for href in sorted(all_links):
    if href.startswith('/assets/'):
        if not os.path.isfile(os.path.join(ROOT, href.lstrip('/'))):
            problems.append(f'dead asset link {href}')
        continue
    code, _ = fetch(href)
    checked[href] = code
    if code >= 400:
        problems.append(f'dead link {href} -> HTTP {code}')

# ---------------------------------------------------------------------------
# Nothing on a page should load from another host. Imported copy kept pointing
# at argflex.co.uk/wp-content for 47 images, which rendered fine only because
# the old WordPress site was still answering — they would all have broken the
# day the domain moved. The asset check above only looked at /assets/ paths,
# so it saw nothing.
# The map on the contacts page is deliberate and set in the admin. Everything
# else that loads from another host is a mistake.
ALLOWED_EXTERNAL = ('https://www.google.com/maps', 'https://maps.google.com/')

for url in urls:
    _, html = fetch(url)
    for tag_src in re.findall(r'<(?:img|script|iframe|source)[^>]+src="([^"]+)"', html):
        if tag_src.startswith(('http://', 'https://')) and not tag_src.startswith(ALLOWED_EXTERNAL):
            problems.append(f'{url} loads {tag_src} from another host')
    for css in re.findall(r'<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"', html):
        if css.startswith(('http://', 'https://')):
            problems.append(f'{url} loads a stylesheet from {css}')

# ---------------------------------------------------------------------------
# A page that calls a function from an include it never required is a fatal
# error, but only on the path that calls it — a crawl of GET requests walks
# straight past it. This has bitten four times now (add_submission,
# record_coupon_use, and twice before), so it is checked statically.
#
# inc/config.php is loaded by the front controller and pulls in commerce.php,
# so those two are free; anything else a page uses it must require itself.
ALWAYS = {'config', 'commerce'}
defined = {}
for inc in pathlib.Path(ROOT, 'inc').glob('*.php'):
    for fn in re.findall(r'^function ([a-z_0-9]+)\(', inc.read_text(encoding='utf-8'), re.M):
        defined[fn] = inc.stem

# The endpoints in the root — wishlist-items.php, cross-sells.php — are pages
# in every sense that matters here, so they are checked the same way.
serve = sorted(pathlib.Path(ROOT, 'pages').glob('*.php')) + [
    f for f in sorted(pathlib.Path(ROOT).glob('*.php')) if f.name != 'router.php']

for page in serve:
    src = page.read_text(encoding='utf-8')
    missing = collections.defaultdict(list)
    for fn in sorted(set(re.findall(r'\b([a-z_0-9]+)\s*\(', src))):
        home = defined.get(fn)
        if home is None or home in ALWAYS or f'inc/{home}.php' in src:
            continue
        missing[home].append(fn)
    for home, fns in missing.items():
        where = page.name if page.parent == pathlib.Path(ROOT) else f'pages/{page.name}'
        problems.append(f'{where} calls {", ".join(fns)} '
                        f'but never requires inc/{home}.php')

print(f'pages crawled : {len(results)}')
print(f'links checked : {len(checked)}')
print(f'problems      : {len(problems)}')
for p in problems[:60]:
    print('  !', p)

json.dump({'results': results, 'problems': problems, 'links': checked},
          open(os.path.join(ROOT, '.data/crawl-report.json'), 'w', encoding='utf-8'), indent=1)
