"""A blog article's search appearance, from the editor to the page.

Every post already carried a title and a description — brought over from the
old WordPress site into data/seo.php, and served on the article page all
along. The editor had no field for any of it, so the only way to read one was
to open the data file, and the only way to change one was to edit it by hand.

These checks cover the round trip: the card is on the screen, what it shows is
what the page serves, saving a new wording changes the page, clearing it goes
back to the article's own title, and renaming an article takes its entry with
it rather than leaving it behind for whoever next takes that address.

Local only — it edits the real data/seo.php and puts it back.
"""
import re, os, json, sys, shutil, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SEO  = os.path.join(ROOT, 'data', 'seo.php')
POSTS = os.path.join(ROOT, 'data', 'posts.php')
op   = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

try:
    os.remove(os.path.join(ROOT, 'storage', 'rate-limits.json'))
except OSError:
    pass


def get(url):
    try:
        with op.open(BASE + url, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def post(url, fields):
    data = urllib.parse.urlencode(fields, doseq=True, encoding='utf-8').encode()
    try:
        with op.open(urllib.request.Request(BASE + url, data=data, method='POST'), timeout=60) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def token(html):
    m = re.search(r'name="_token" value="([^"]+)"', html)
    return m.group(1) if m else ''


def field(html, name):
    """The value an input or textarea currently holds."""
    m = re.search(r'<input[^>]*name="' + re.escape(name) + r'"[^>]*value="([^"]*)"', html)
    if m:
        return m.group(1)
    m = re.search(r'<textarea[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</textarea>', html, re.S)
    return m.group(1) if m else None


def meta(html):
    m = re.search(r'<meta name="description" content="([^"]*)"', html)
    return m.group(1) if m else ''


def title_of(html):
    m = re.search(r'<title>(.*?)</title>', html, re.S)
    return m.group(1).strip() if m else ''


FAILS = []
def check(label, ok, extra=''):
    if not ok:
        FAILS.append(label)
    print(f'  {label:56} {"OK" if ok else "FAILED"}{("  " + extra) if extra else ""}')


# The data files are restored byte for byte at the end. Reposting a post form
# re-escapes its content, so tidying by hand would leave the copy buried under
# layers of &amp; — a file copy cannot.
shutil.copy2(SEO, SEO + '.bak')
shutil.copy2(POSTS, POSTS + '.bak')

_, html = get('/admin/login')
post('/admin/login', {'_token': token(html), 'email': 'admin@argflex.co.uk',
                      'password': 'Str0ngPass!2026'})

SLUG = 'industrial-hose-types-and-applications'

print('THE CARD IS THERE')
code, editor = get('/admin/posts/' + SLUG)
check('the post editor opens', code == 200, str(code))
check('it offers a search-engine card',
      'name="seo_title"' in editor and 'name="seo_description"' in editor
      and 'name="seo_robots"' in editor and 'name="seo_canonical"' in editor)

was_title = field(editor, 'seo_title')
was_desc  = field(editor, 'seo_description')
check('and it is filled in from what the old site served',
      bool(was_title) and bool(was_desc), (was_title or '')[:44])

print('\nWHAT IT SHOWS IS WHAT THE PAGE SERVES')
_, page = get('/' + SLUG + '/')
import html as H
check('the title matches the page', H.unescape(was_title) == title_of(page),
      title_of(page)[:50])
check('so does the description', H.unescape(was_desc)[:60] == meta(page)[:60],
      meta(page)[:50])


def save(changes):
    _, ed = get('/admin/posts/' + SLUG)
    fields = {
        '_token': token(ed),
        'title': H.unescape(field(ed, 'title') or ''),
        'slug': H.unescape(field(ed, 'slug') or SLUG),
        'date': field(ed, 'date') or '',
        'image': H.unescape(field(ed, 'image') or ''),
        'excerpt': H.unescape(field(ed, 'excerpt') or ''),
        'content': H.unescape(re.search(r'name="content"[^>]*>(.*?)</textarea>', ed, re.S).group(1)),
        'seo_title': H.unescape(field(ed, 'seo_title') or ''),
        'seo_description': H.unescape(field(ed, 'seo_description') or ''),
        'seo_canonical': H.unescape(field(ed, 'seo_canonical') or ''),
        'seo_robots': '',
    }
    fields.update(changes)
    return post('/admin/posts/' + SLUG, fields)


print('\nEDITING IT CHANGES THE PAGE')
save({'seo_title': 'Industrial hose types — a buyer\'s guide | Arg Flex',
      'seo_description': 'Which industrial hose for fuel, air, water or abrasives, '
                         'and the pressure and temperature each one is rated for.'})
_, page = get('/' + SLUG + '/')
check('the new title is served', title_of(page).startswith('Industrial hose types'),
      title_of(page)[:50])
check('the new description is served',
      meta(page).startswith('Which industrial hose for fuel'), meta(page)[:50])

_, editor = get('/admin/posts/' + SLUG)
check('and the editor reads it back', 'buyer' in (field(editor, 'seo_title') or ''))

print('\nCLEARING IT FALLS BACK TO THE ARTICLE ITSELF')
save({'seo_title': '', 'seo_description': ''})
_, page = get('/' + SLUG + '/')
check('the title is the article\'s own again',
      title_of(page).startswith('Industrial Hose Types and Applications'), title_of(page)[:50])
check('and the description is its excerpt', meta(page) != '', meta(page)[:50])

print('\nKEEPING IT OUT OF SEARCH')
save({'seo_robots': 'noindex, follow'})
_, page = get('/' + SLUG + '/')
check('the robots tag is served', 'noindex, follow' in page)
save({'seo_robots': ''})
_, page = get('/' + SLUG + '/')
check('and taken away again', 'noindex, follow' not in page)

print('\nRENAMING TAKES THE ENTRY WITH IT')
save({'seo_title': 'A title that belongs to one article',
      'seo_description': 'And a description that belongs with it.'})
_, ed = get('/admin/posts/' + SLUG)
post('/admin/posts/' + SLUG, {
    '_token': token(ed),
    'title': H.unescape(field(ed, 'title') or ''),
    'slug': SLUG + '-renamed',
    'date': field(ed, 'date') or '',
    'image': H.unescape(field(ed, 'image') or ''),
    'excerpt': H.unescape(field(ed, 'excerpt') or ''),
    'content': H.unescape(re.search(r'name="content"[^>]*>(.*?)</textarea>', ed, re.S).group(1)),
    'seo_title': 'A title that belongs to one article',
    'seo_description': 'And a description that belongs with it.',
    'seo_canonical': '', 'seo_robots': '',
})
_, moved = get('/' + SLUG + '-renamed/')
check('the entry followed the article to its new address',
      title_of(moved).startswith('A title that belongs'), title_of(moved)[:50])
seo_src = open(SEO, encoding='utf-8').read()
check('  and nothing was left behind at the old one',
      f"'/{SLUG}/'" not in seo_src)

print('\nTIDY UP')
shutil.move(SEO + '.bak', SEO)
shutil.move(POSTS + '.bak', POSTS)
_, page = get('/' + SLUG + '/')
check('the article is back exactly as it was',
      title_of(page).startswith('Industrial Hose Types and Applications') and meta(page) != '',
      title_of(page)[:50])

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
sys.exit(1 if FAILS else 0)
