"""Walk every admin screen and report anything that is not a clean 200.

A route can be lost without anyone noticing: the sidebar keeps linking to it
and a public crawl never touches /admin/. That is exactly how the Blog screen
started 404ing for two commits, so every screen is walked here instead.

Set the login in the environment first — nothing is stored in this file:

    ARGFLEX_ADMIN_EMAIL=you@example.com ARGFLEX_ADMIN_PASSWORD=... python .data/check_admin.py
"""
import os, re, sys, json, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE  = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
EMAIL = os.environ.get('ARGFLEX_ADMIN_EMAIL', '')
PW    = os.environ.get('ARGFLEX_ADMIN_PASSWORD', '')

if not EMAIL or not PW:
    print('Set ARGFLEX_ADMIN_EMAIL and ARGFLEX_ADMIN_PASSWORD first — see the docstring.')
    sys.exit(2)

op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def get(url):
    try:
        with op.open(BASE + url, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except urllib.error.URLError as e:
        return 0, str(e)


def post(url, fields):
    data = urllib.parse.urlencode(fields, doseq=True, encoding='utf-8').encode()
    try:
        with op.open(urllib.request.Request(BASE + url, data=data, method='POST'), timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


code, html = get('/admin/login')
if code != 200:
    print(f'Could not reach {BASE}/admin/login (HTTP {code}). Is the server running?')
    sys.exit(2)

token = re.search(r'name="_token" value="([^"]+)"', html)
post('/admin/login', {'_token': token.group(1) if token else '', 'email': EMAIL, 'password': PW})

code, home = get('/admin/')
if 'admin/logout' not in home:
    print('Those admin details were not accepted.')
    sys.exit(2)

# every link the sidebar offers, plus the sub-screens the sidebar cannot show
nav = sorted(set(re.findall(r'href="(/admin/[a-z-]*)"', home)))
extra = ['/admin/products/new', '/admin/products/import', '/admin/coupons/new',
         '/admin/posts/new', '/admin/settings/products', '/admin/settings/tax', '/admin/settings/shipping',
         '/admin/settings/payments', '/admin/settings/emails', '/admin/settings/advanced',
         '/admin/pages?p=/', '/admin/seo?url=/', '/admin/account']

problems = []
for url in nav + extra:
    if url.endswith('/logout'):
        continue
    code, body = get(url)
    note = re.findall(r'(Fatal error|Parse error|Warning</b>|Notice</b>|Deprecated</b>)', body)
    if code != 200:
        problems.append(f'{url} -> HTTP {code}')
    elif note:
        problems.append(f'{url} -> {note[0]}')
    else:
        # a screen that renders the layout but no content is a broken view
        if '<main class="main">' not in body:
            problems.append(f'{url} -> rendered without the admin layout')

print(f'screens walked : {len(nav) + len(extra) - 1}')
print(f'problems       : {len(problems)}')
for p in problems:
    print('  !', p)

sys.exit(1 if problems else 0)
