"""Upsells on the product page and cross-sells under the cart."""
import re, os, json, subprocess, sys, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PHP  = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))
op   = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

MAIN  = 'submersible-fuel-hose-sae-j30-r10'
UP    = 'oil-resistant-hose-sae-j30-r6-7mm'
CROSS = 'gbs-clamps'
OTHER = 'asfa-clamps'


def get(url):
    try:
        with op.open(BASE + url, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def snapshot(action):
    out = subprocess.run([PHP, '.data/catalogue_snapshot.php', action],
                         cwd=ROOT, capture_output=True, text=True)
    return (out.stdout or out.stderr).strip()


def php(code):
    out = subprocess.run([PHP, '-r', 'require "inc/config.php"; require "inc/store.php"; ' + code],
                         cwd=ROOT, capture_output=True, text=True)
    return (out.stdout or '') + (out.stderr or '')


FAILS = []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:52} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')


print('SETUP')
print('  ' + snapshot('save'))
print(php(f'''
$ps = all_products(true);
foreach ($ps as $i => $p) {{
    if ($p["slug"] === "{MAIN}") {{
        $ps[$i]["upsells"]    = ["{UP}"];
        $ps[$i]["crosssells"] = ["{CROSS}", "{OTHER}"];
    }}
}}
echo save_products($ps) ? "  linked products set on {MAIN}" : "  FAILED to set";
'''))

print('\nUPSELLS ON THE PRODUCT PAGE')
code, page = get('/product/' + MAIN + '/')
check('the page renders', code == 200)
check('an upsell section appears', 'Worth considering' in page and 'You might prefer' in page)
check('it shows the linked product', f'/product/{UP}/' in page.split('Worth considering')[1][:3000])
check('cross-sells are not shown here', 'Often needed alongside' not in page)

viewed = re.search(r'<h1>([^<]+)</h1>', page)
check('the viewed product is still itself after the loops',
      viewed and 'Submersible' in viewed.group(1), viewed.group(1) if viewed else '')
canon = re.search(r'<link rel="canonical" href="[^"]*/product/([^/"]+)/"', page)
check('and the canonical still points at it', canon and canon.group(1) == MAIN,
      canon.group(1) if canon else 'none')

code, page = get('/product/' + OTHER + '/')
check('a product with no upsells shows no section', 'Worth considering' not in page)

print('\nCROSS-SELLS UNDER THE CART')
code, cart = get('/cart/')
check('the cart has a place for them', code == 200 and 'data-cross' in cart and 'data-cross-grid' in cart)
check('hidden until the basket is known', 'data-cross hidden' in cart or 'data-cross" hidden' in cart
      or re.search(r'data-cross\b[^>]*hidden', cart) is not None)

code, html = get('/cross-sells.php?slugs=' + MAIN)
check('the endpoint answers', code == 200)
check('it returns the two linked products',
      f'/product/{CROSS}/' in html and f'/product/{OTHER}/' in html)
check('and nothing else', html.count('<article class="card"') == 2,
      str(html.count('<article class="card"')))

code, html = get(f'/cross-sells.php?slugs={MAIN},{CROSS}')
check('one already in the basket is left out', f'/product/{CROSS}/' not in html
      and f'/product/{OTHER}/' in html)

code, html = get('/cross-sells.php?slugs=' + OTHER)
check('a product with no cross-sells returns nothing', html.strip() == '')
code, html = get('/cross-sells.php?slugs=')
check('an empty basket returns nothing', html.strip() == '')
code, html = get('/cross-sells.php?slugs=no-such-product')
check('an unknown slug returns nothing', html.strip() == '')

print('\nOUT OF STOCK IS NOT SUGGESTED')
print(php(f'''
$ps = all_products(true);
foreach ($ps as $i => $p) if ($p["slug"] === "{CROSS}") $ps[$i]["stock"] = "outofstock";
save_products($ps); echo "  {CROSS} marked out of stock";
'''))
code, html = get('/cross-sells.php?slugs=' + MAIN)
check('out-of-stock cross-sell dropped', f'/product/{CROSS}/' not in html
      and f'/product/{OTHER}/' in html)
print(php(f'''
$ps = all_products(true);
foreach ($ps as $i => $p) if ($p["slug"] === "{UP}") $ps[$i]["stock"] = "outofstock";
save_products($ps); echo "  {UP} marked out of stock";
'''))
code, page = get('/product/' + MAIN + '/')
check('out-of-stock upsell hides the section', 'Worth considering' not in page)

print('\nTIDY UP')
print('  ' + snapshot('restore'))
code, page = get('/product/' + MAIN + '/')
check('the product page is back to normal', code == 200 and 'Worth considering' not in page)

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
sys.exit(1 if FAILS else 0)
