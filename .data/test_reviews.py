"""Reviews: writing one, moderating it, and what search engines see."""
import re, os, json, subprocess, sys, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PHP  = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))
ORD  = os.path.join(ROOT, 'storage/orders')
op   = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

# Every public form now carries a token signed for its own action, so these
# suites have to carry one too — see .data/formtoken.py. Scraped from the real
# page, never computed here: a test that signed its own tokens would keep
# passing if the server stopped checking them.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from formtoken import Tokens
TOK = Tokens(op, BASE)

# The suites hammer the same forms from one address, so they trip the counters
# the shop uses against brute force and spam. Clearing them at the start keeps
# a run from failing on its own previous run — never do this on a live server,
# which is why every one of these files says local-only at the top.
try:
    os.remove(os.path.join(ROOT, 'storage', 'rate-limits.json'))
except OSError:
    pass


SLUG = 'submersible-fuel-hose-sae-j30-r10'


def get(url):
    try:
        with op.open(BASE + url, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace'), r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), ''


def post(url, fields):
    if '_form' not in fields and not url.startswith('/admin'):
        fields = {**fields, '_form': TOK.sign(url, fields)}
    data = urllib.parse.urlencode(fields, doseq=True, encoding='utf-8').encode()
    try:
        with op.open(urllib.request.Request(BASE + url, data=data, method='POST'), timeout=60) as r:
            return r.status, r.read().decode('utf-8', 'replace'), r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), ''


def token(html):
    m = re.search(r'name="_token" value="([^"]+)"', html)
    return m.group(1) if m else ''


def reviews_src():
    return open(os.path.join(ROOT, 'data/reviews.php'), encoding='utf-8').read()


def settings(**flags):
    """Save the Products tab with the review flags we want."""
    _, html, _ = get('/admin/settings/products')
    fields = {'_token': token(html), 'default_sort': 'default', 'shop_notice': '',
              'enable_wishlist': 'on', 'enable_compare': 'on', 'low_stock_qty': '2',
              'stock_display': 'low', 'weight_unit': 'kg', 'dimension_unit': 'cm'}
    for k, v in flags.items():
        if v: fields[k] = 'on'
    post('/admin/settings/products', fields)


FAILS = []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:54} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')


def leave(author, email, rating, body, website=''):
    return post('/review-send', {'product': SLUG, 'author': author, 'email': email,
                                 'rating': str(rating), 'body': body, 'website': website})


_, html, _ = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk', 'password': 'Str0ngPass!2026'})
open(os.path.join(ROOT, 'data/reviews.php'), 'w', encoding='utf-8').write(
    "<?php\nif (!defined('ROOT_DIR')) { http_response_code(404); exit; }\n\nreturn [];\n")
for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))

print('SWITCHED OFF')
settings()
_, page, _ = get('/product/' + SLUG + '/')
check('no reviews tab', 'data-tab="reviews"' not in page)
check('no rating in the structured data', '"aggregateRating"' not in page)
_, _, url = leave('Bot', 'bot@example.com', 5, 'Nice')
check('the endpoint refuses', 'review=closed' in url, url[-30:])
check('nothing was stored', reviews_src().count("'author' =>") == 0)

print('\nSWITCHED ON, WITH APPROVAL')
settings(enable_reviews=True, review_approval=True)
_, page, _ = get('/product/' + SLUG + '/')
check('the tab appears', 'data-tab="reviews"' in page)
check('the form is there', 'name="rating"' in page and 'action="/review-send"' in page)
check('it says there are none yet', 'No reviews yet' in page)

_, _, url = leave('Rita Cheng', 'rita@example.com', 5, 'Held 8 bar all summer, no weeping at the clamps.')
check('a review is accepted', 'review=pending' in url, url[-30:])
check('and stored as pending', "'status' => 'pending'" in reviews_src())
_, page, _ = get('/product/' + SLUG + '/')
check('but is not shown yet', 'Held 8 bar' not in page)
check('still no rating claimed', '"aggregateRating"' not in page)

print('\nMODERATING')
_, html, _ = get('/admin/reviews')
check('it is waiting in the admin', 'Held 8 bar' in html and 'Rita Cheng' in html)
check('the sidebar shows a tally', re.search(r'class="tally">1<', html) is not None)
rid = re.search(r'name="ids\[\]" value="([^"]+)"', html)
check('found its id', rid is not None)

post('/admin/reviews', {'_token': token(html), 'one': 'approved:' + rid.group(1)})
check('publishing it works', "'status' => 'approved'" in reviews_src())
_, page, _ = get('/product/' + SLUG + '/')
check('now it shows on the page', 'Held 8 bar' in page)
check('with the author', 'Rita Cheng' in page)
check('the tab counts it', 'Reviews (1)' in page)
check('stars appear under the title', 'p-rating' in page)

print('\nWHAT SEARCH ENGINES GET')
schema = re.search(r'"aggregateRating":\{[^}]*\}', page)
check('an aggregate rating is claimed', schema is not None, schema.group(0)[:70] if schema else 'none')
check('the value is right', '"ratingValue":"5"' in page and '"reviewCount":"1"' in page)
check('the review itself is included', '"@type":"Review"' in page)

print('\nMORE REVIEWS')
leave('Omar Silva', 'omar@example.com', 3, 'Fine hose, delivery took a week.')
_, html, _ = get('/admin/reviews')
rid2 = re.search(r'name="ids\[\]" value="([^"]+)"', html)
post('/admin/reviews', {'_token': token(html), 'one': 'approved:' + rid2.group(1)})
_, page, _ = get('/product/' + SLUG + '/')
check('the average is the mean of the two', '"ratingValue":"4"' in page,
      re.search(r'"ratingValue":"([\d.]+)"', page).group(1))
check('both are listed', 'Held 8 bar' in page and 'delivery took a week' in page)
check('the summary counts two', 'Reviews (2)' in page)

print('\nSPAM AND RUBBISH')
_, _, url = leave('Spammer', 'spam@example.com', 5, 'buy things', website='http://spam.example')
check('the honeypot is caught quietly', 'review=thanks' in url, url[-26:])
check('and nothing was stored', reviews_src().count("'author' => 'Spammer'") == 0)
_, _, url = leave('', 'nope', 5, '')
check('an incomplete review is refused', 'review=incomplete' in url, url[-30:])

_, html, _ = get('/admin/reviews?status=approved')
ids = re.findall(r'name="ids\[\]" value="([^"]+)"', html)
post('/admin/reviews?status=approved', {'_token': token(html), 'bulk': 'spam', 'ids[]': ids})
check('bulk marking as spam works', reviews_src().count("'status' => 'spam'") == 2)
_, page, _ = get('/product/' + SLUG + '/')
check('spam is off the page', 'Held 8 bar' not in page and 'delivery took a week' not in page)
check('and the rating claim goes with it', '"aggregateRating"' not in page)

print('\nVERIFIED PURCHASES ONLY')
settings(enable_reviews=True, review_approval=True, review_verified=True)
_, _, url = leave('Sam Doyle', 'sam@example.com', 5, 'Never bought it.')
check('someone who has not ordered is refused', 'review=unverified' in url, url[-30:])

body = post('/checkout/', {'cart': json.dumps([{"slug": SLUG, "option": "", "qty": 1}]),
                           'name': 'Sam Doyle', 'company': '', 'email': 'sam@example.com',
                           'phone': '07000 000111', 'address': '1 Test Road', 'city': 'London',
                           'postcode': 'E18 1AN', 'country': 'GB', 'notes': '',
                           'payment': 'proforma', 'website': ''})[1]
ref = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
check('an order is placed for them', ref is not None)
_, _, url = leave('Sam Doyle', 'sam@example.com', 4, 'Bought it, works.')
check('now they may review', 'review=pending' in url, url[-30:])
check('and it is badged as verified', "'verified' => true" in reviews_src())
if ref:
    os.remove(os.path.join(ORD, ref.group(1) + '.json'))

print('\nTIDY UP')
open(os.path.join(ROOT, 'data/reviews.php'), 'w', encoding='utf-8').write(
    "<?php\n/**\n * Product reviews, written by the site and moderated in the admin panel.\n"
    " * Ratings are whole stars, 1 to 5.\n */\nif (!defined('ROOT_DIR')) { http_response_code(404); exit; }\n\nreturn [];\n")
if os.path.exists(os.path.join(ROOT, 'storage/settings.php')):
    os.remove(os.path.join(ROOT, 'storage/settings.php'))
_, page, _ = get('/product/' + SLUG + '/')
check('the product page is back to normal', 'data-tab="reviews"' not in page)
check('site fine on the defaults', get('/')[0] == 200)

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
sys.exit(1 if FAILS else 0)
