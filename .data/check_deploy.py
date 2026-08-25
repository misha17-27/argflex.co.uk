"""Check a site that has just been uploaded, from the outside.

    python .data/check_deploy.py https://new.argflex.co.uk

Fetches the real host over the internet and reports what a browser and a
search engine would actually get: whether the pages render, whether the
private folders are refused, whether the images and stylesheet are served,
and — on anything that is not argflex.co.uk — whether the copy is properly
kept out of the index.
"""
import sys, re, ssl, urllib.request, urllib.error

BASE = (sys.argv[1] if len(sys.argv) > 1 else '').rstrip('/')
if not BASE.startswith('http'):
    print(__doc__)
    sys.exit(2)

LIVE = 'argflex.co.uk'
IS_COPY = LIVE not in BASE.split('//', 1)[-1].split('/')[0].replace('www.', '') \
    or BASE.split('//', 1)[-1].split('/')[0].replace('www.', '') != LIVE

ctx = ssl.create_default_context()
FAILS = []


def fetch(path, method='GET'):
    req = urllib.request.Request(BASE + path, method=method,
                                 headers={'User-Agent': 'argflex-deploy-check'})
    try:
        with urllib.request.urlopen(req, timeout=30, context=ctx) as r:
            body = r.read().decode('utf-8', 'replace') if method == 'GET' else ''
            return r.status, dict(r.headers), body
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, {}, str(e)


def check(label, ok, extra=''):
    if not ok:
        FAILS.append(label)
    print(f'  {"OK  " if ok else "FAIL"}  {label}{("  — " + extra) if extra else ""}')


def head(title):
    print(f'\n{title}')


head(f'Reaching {BASE}')
code, hdrs, body = fetch('/')
if code == 0:
    print(f'  FAIL  could not connect — {body}')
    print('\nNothing else can be checked until it answers.')
    sys.exit(1)
check('the site answers', code == 200, f'HTTP {code}')
ours = 'assets/css/site.css' in body
check('it is this build, not the old one', ours,
      '' if ours else ('the old WordPress site is still being served'
                       if 'wp-content' in body else 'unrecognised page'))

head('Pages')
PAGES = ['/', '/shop/', '/product-category/rubber-hoses/', '/product/acetylene-hose/',
         '/blog/', '/about-us/', '/contacts/', '/cart/', '/checkout/', '/refund_returns/',
         '/sitemap.xml']
bad = []
for path in PAGES:
    code, _, page = fetch(path)
    if code != 200 or 'Fatal error' in page or 'Warning</b>' in page:
        bad.append(f'{path} -> {code}')
check(f'{len(PAGES)} pages render', not bad, '; '.join(bad[:4]))

code, _, page = fetch('/definitely-not-a-real-page/')
check('an unknown address gives the 404 page', code == 404 and 'assets/css/site.css' in page,
      f'HTTP {code}')

head('Private folders')
for path in ['/data/products.php', '/data/seo.php', '/inc/config.php', '/storage/users.php',
             '/pages/product.php', '/partials/product-card.php', '/.data/crawl.py',
             '/storage/orders/']:
    code, _, _ = fetch(path)
    check(f'{path} is refused', code in (403, 404), f'HTTP {code}')

head('Assets')
for path in ['/assets/css/site.css', '/assets/js/site.js',
             '/assets/img/site/logo.png', '/assets/img/products/acetylene-hose.jpg']:
    code, hdrs, _ = fetch(path)
    check(f'{path.split("/")[-1]} is served', code == 200, f'HTTP {code}')

code, hdrs, _ = fetch('/assets/css/site.css')
cache = hdrs.get('Cache-Control', '')
check('stylesheets are cached hard', 'max-age' in cache, cache or 'no Cache-Control — mod_headers may be off')

head('Admin')
code, hdrs, _ = fetch('/admin/')
check('the admin answers', code in (200, 302), f'HTTP {code}')
robots = hdrs.get('X-Robots-Tag', '')
check('the admin is noindex', 'noindex' in robots.lower(), robots or 'missing')

head('Search engines')
code, hdrs, page = fetch('/')
robots = hdrs.get('X-Robots-Tag', '')
_, _, txt = fetch('/robots.txt')
canon = re.search(r'<link rel="canonical" href="([^"]+)"', page)

if IS_COPY:
    check('every page says noindex', 'noindex' in robots.lower(),
          robots or 'MISSING — this copy could be indexed and take traffic from the live shop')
    check('robots.txt disallows everything', 'Disallow: /' in txt and 'Allow: /' not in txt,
          txt.splitlines()[1] if len(txt.splitlines()) > 1 else txt[:40])
else:
    check('the live site is indexable', 'noindex' not in robots.lower(), robots)
    check('robots.txt allows crawling', 'Allow: /' in txt)
    check('the sitemap is listed', 'Sitemap:' in txt)

check('canonicals point at the live domain',
      bool(canon) and LIVE in canon.group(1), canon.group(1) if canon else 'none found')

head('Security headers')
code, hdrs, _ = fetch('/')
for name, want in [('X-Content-Type-Options', 'nosniff'), ('X-Frame-Options', '')]:
    got = hdrs.get(name, '')
    check(f'{name}', got != '', got or 'missing — mod_headers may be off')

FROM_HTACCESS = {'stylesheets are cached hard', 'X-Content-Type-Options', 'X-Frame-Options'}
if FAILS and set(FAILS) <= FROM_HTACCESS and 'localhost' in BASE:
    print("\n  (those come from .htaccess, which PHP's built-in server ignores —"
          "\n   they pass on Apache)")

print('\n' + '-' * 60)
if FAILS:
    print(f'{len(FAILS)} problem(s): ' + ', '.join(FAILS[:6]))
    print('\nDEPLOY.md has what each one usually means.')
else:
    print('Everything checked out.')
    if IS_COPY:
        print('This copy is properly hidden from search engines.')
sys.exit(1 if FAILS else 0)
