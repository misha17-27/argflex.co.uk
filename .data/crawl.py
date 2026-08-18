"""Walk every internal URL of the local PHP site and report problems."""
import json, os, re, subprocess, sys, collections

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
        '/checkout/', '/checkout/?ok=260101-ABCDEF', '/sitemap.xml', '/robots.txt']
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

    if url in ('/sitemap.xml', '/robots.txt'):
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

print(f'pages crawled : {len(results)}')
print(f'links checked : {len(checked)}')
print(f'problems      : {len(problems)}')
for p in problems[:60]:
    print('  !', p)

json.dump({'results': results, 'problems': problems, 'links': checked},
          open(os.path.join(ROOT, '.data/crawl-report.json'), 'w', encoding='utf-8'), indent=1)
