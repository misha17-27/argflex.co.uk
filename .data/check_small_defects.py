"""Seven small faults that each reached the live site, held shut.

    python .data/check_small_defects.py

Needs the local site on http://localhost:8124 (ARGFLEX_BASE); starts one of
its own when nothing answers there, and stops it again. Reads nothing from
the live site and writes nothing anywhere.

None of these was a wrong idea. Each was a page saying something nobody had
looked at since it was written:

  1 share pictures
      the home page, About, Contacts and the blog index offered an og:image
      under /wp-content/uploads/, which stopped existing with WordPress. So:
      every og:image and twitter:image on every page in the sitemap, and on
      the 404 page, is an absolute https://argflex.co.uk address of a file
      that is on disk here; and own_image_url() refuses a dead one whatever
      data/seo.php holds (the repository's copy is repaired, the live copy is
      not, so no local page can show the fault).

  2 social icons
      the footer linked Facebook and Instagram to the networks' front pages.
      So: the footer prints exactly the slots whose address goes past a front
      page, worked out here from the raw settings; Organization "sameAs" is
      that list without WhatsApp, and absent when the list is empty. (Looking
      for the footer on every page is how three pages turned out to have none:
      /delivery/, /terms/ and /privacy/ never required it. Every page must now
      have one and must reach </html>.)

  3 the scrolling strip
      "Fuel &amp;amp; oil" in the source, "Fuel &amp; oil" on screen. So: each
      ticker word and each hero tag is escaped exactly once, and no page in
      the sitemap contains "&amp;amp;" at all.

  4 sitemap dates
      80 of 113 addresses had no <lastmod>. So: every address has one, it is a
      real day that is not in the future, and it is the day THIS check works
      out from the file the page is made of — a product from data/products.php
      (or its own `modified`), an article from its own date. A date() printed
      on every request would fail that the first day a file is older.

  5 the chat picture
      a 1367x370, 63 KB PNG was fetched on every page to fill a 32 px slot.
      So: the picture in the chat header is a file under 5 KB, in the logo's
      proportions, with width, height and loading="lazy"; and no <img> on any
      page asks for logo.png.

  6 one page, one spelling
      /product/ASFA-CLAMPS/ answered 200, named itself canonical and lost its
      title. So: every product, category and article answers 200 with NO
      redirect at the address the catalogue stores — the sandblast hose at its
      lower-case %c2%b3 included — and 301s there from upper case and from a
      %-encoded hyphen, keeping the query string. A POST is not redirected.
      A missing final slash alone is not a different spelling and is not
      redirected either. Nor is %C2%B3 for %c2%b3: the two are one address,
      curl sends the capitals whatever it was given, and a 301 between them
      never ends. Both answer 200 and name the stored spelling canonical.

  7 a category that never was
      any address under /product-category/ answered 301. So: an unknown one is
      404; the eleven the old shop had still go to their parent or the shop,
      and land on a page that answers; a category's /feed/ goes to the
      category; a real category under the wrong parent goes to its own
      address.
"""
import datetime
import html
import json
import os
import re
import socket
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from devserver import DevServer

BASE = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PHP = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))
SITE = 'https://argflex.co.uk'

AVATAR_MAX_BYTES = 5000

# The categories WooCommerce served and this shop does not have, and where
# each one goes. Said again here rather than read out of index.php: this is
# the list the report to the owner names, and a line dropped from the router
# has to fail something.
LEGACY_CATEGORIES = {
    '/product-category/': '/shop/',
    '/product-category/composite-hoses/': '/shop/',
    '/product-category/industrial-rubber-sheets/': '/shop/',
    '/product-category/sandblasting/': '/shop/',
    '/product-category/uncategorized/': '/shop/',
    '/product-category/pvcpu-hoses/compressed-air-pvcpu-hoses/': '/product-category/pvcpu-hoses/',
    '/product-category/pvcpu-hoses/suction/': '/product-category/pvcpu-hoses/',
    '/product-category/pvcpu-hoses/wastewater/': '/product-category/pvcpu-hoses/',
    '/product-category/rubber-hoses/compressed-air/': '/product-category/rubber-hoses/',
    '/product-category/rubber-hoses/food-products/': '/product-category/rubber-hoses/',
    '/product-category/rubber-hoses/sand-blasting/': '/product-category/rubber-hoses/',
    '/product-category/rubber-hoses/steam/': '/product-category/rubber-hoses/',
}
# The other redirects the code declares on purpose, which this change must
# leave standing (router.php and .htaccess, the Settings table, index.php).
OTHER_LEGACY = {
    '/product-category/rubber-hoses/page/2/': '/product-category/rubber-hoses/',
    '/shop/page/2/': '/shop/',
    '/blog/page/3/': '/blog/',
    '/category/uncategorized/': '/blog/',
    '/author/admin/': '/blog/',
    '/shop/feed/': '/shop/',
}

problems = []


def facts():
    out = subprocess.run([PHP, os.path.join('.data', 'small_facts.php')], cwd=ROOT,
                         capture_output=True, text=True, encoding='utf-8', errors='replace')
    try:
        return json.loads(out.stdout)
    except ValueError:
        print('small_facts.php did not answer with JSON:\n' + (out.stdout + out.stderr)[:600])
        sys.exit(1)


FACTS = facts()


def fetch(path):
    """A page as a browser gets it, redirects followed."""
    req = urllib.request.Request(BASE + path, headers={'User-Agent': 'argflex-check'})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, str(e)


def hop(path, method='GET', body=b''):
    """One request, exactly these bytes on the wire, nothing followed.

    A socket rather than urllib: the point of several cases is the spelling
    of the address — %C2%B3 against %c2%b3, a raw superscript — and a client
    library is entitled to tidy those before sending.
    """
    target = urllib.parse.urlsplit(BASE)
    raw = path.encode('utf-8') if isinstance(path, str) else path
    head = (method.encode() + b' ' + raw + b' HTTP/1.0\r\nHost: ' + target.netloc.encode()
            + b'\r\nUser-Agent: argflex-check\r\n')
    if method == 'POST':
        head += (b'Content-Type: application/x-www-form-urlencoded\r\nContent-Length: '
                 + str(len(body)).encode() + b'\r\n')
    try:
        with socket.create_connection((target.hostname, target.port or 80), timeout=30) as s:
            s.sendall(head + b'\r\n' + body)
            data = b''
            while b'\r\n\r\n' not in data and len(data) < 65536:
                chunk = s.recv(4096)
                if not chunk:
                    break
                data += chunk
    except OSError as e:
        return 0, str(e)
    lines = data.split(b'\r\n\r\n')[0].decode('latin-1').split('\r\n')
    try:
        status = int(lines[0].split()[1])
    except (IndexError, ValueError):
        return 0, lines[0] if lines else ''
    location = ''
    for line in lines[1:]:
        if line.lower().startswith('location:'):
            location = line.split(':', 1)[1].strip()
    return status, location


def expect_hop(path, status, location='', why='', method='GET'):
    got, to = hop(path, method)
    shown = path if isinstance(path, str) else path.decode('utf-8', 'replace')
    if got != status or (status in (301, 302) and to != location):
        want = f'{status} {location}'.strip()
        problems.append(f'{method} {shown}: expected {want}, got {got} {to}'.rstrip()
                        + (f' ({why})' if why else ''))


def day_of(*relative):
    """The UTC day the newest of these files was written; '' if none exists."""
    newest = 0
    for rel in relative:
        try:
            newest = max(newest, os.path.getmtime(os.path.join(ROOT, rel)))
        except OSError:
            pass
    if not newest:
        return ''
    return datetime.datetime.fromtimestamp(int(newest), datetime.timezone.utc).strftime('%Y-%m-%d')


def is_profile(url):
    """Goes past the host's front page. Decided here, not asked of the site."""
    parts = urllib.parse.urlsplit(url.strip())
    if parts.scheme.lower() not in ('http', 'https') or not parts.netloc:
        return False
    return parts.path.strip('/') != '' or parts.query != ''


def local_file(url):
    """The file an absolute address of this site names, or None."""
    if not url.startswith(SITE + '/'):
        return None
    path = urllib.parse.unquote(urllib.parse.urlsplit(url).path)
    if '..' in path:
        return None
    return os.path.join(ROOT, path.lstrip('/').replace('/', os.sep))


def meta(page, attr, name):
    m = re.search(r'<meta\s+' + attr + r'="' + re.escape(name) + r'"\s+content="([^"]*)"', page)
    return html.unescape(m.group(1)) if m else None


# ------------------------------------------------------------ fixed inputs
CASES = FACTS['cases']

own = CASES['own_image_url']
hero = SITE + '/assets/img/site/hero-1.webp'
for case, want in [('dead-wordpress', ''), ('absolute', hero), ('www', hero),
                   ('versioned', hero + '?v=abc123'), ('relative', hero), ('rooted', hero),
                   ('foreign', ''), ('climbing', ''), ('directory', ''), ('blank', ''), ('null', '')]:
    if own.get(case) != want:
        problems.append(f'own_image_url [{case}]: expected {want!r}, got {own.get(case)!r}')

for url, got in CASES['is_profile_url'].items():
    if got != is_profile(url):
        problems.append(f'is_profile_url({url!r}) said {got}, expected {is_profile(url)}')
for bare in ('https://www.facebook.com/', 'https://www.instagram.com', 'https://www.instagram.com//'):
    if is_profile(bare):
        problems.append(f'this check itself takes {bare} for a profile')

days = CASES['files_day']
if days['missing'] != '':
    problems.append(f"files_day of a missing file is {days['missing']!r}, expected ''")
if days['robots'] != day_of('robots.txt'):
    problems.append(f"files_day(robots.txt) is {days['robots']}, the file says {day_of('robots.txt')}")
if days['newest'] != day_of('robots.txt', 'index.php'):
    problems.append(f"files_day of two files is {days['newest']}, the newer says {day_of('robots.txt', 'index.php')}")

lines = CASES['page_lines']
if lines['default'] != ['Fuel &amp; oil', '&lt;b&gt;']:
    problems.append(f"page_lines fallback is not escaped once: {lines['default']!r}")
if lines['blank-default'] != []:
    problems.append(f"page_lines with nothing to show returned {lines['blank-default']!r}")

# ----------------------------------------------------------- the chat file
logo, small = FACTS['logo'], FACTS['logo_chat']
avatar_file = os.path.join(ROOT, 'assets', 'img', 'site', 'logo-chat.webp')
if not small:
    problems.append('assets/img/site/logo-chat.webp is missing: run php .data/make_chat_logo.php')
else:
    if os.path.getsize(avatar_file) > AVATAR_MAX_BYTES:
        problems.append(f'logo-chat.webp is {os.path.getsize(avatar_file)} bytes, over {AVATAR_MAX_BYTES}')
    if logo and abs(small[0] / small[1] - logo[0] / logo[1]) > 0.15:
        problems.append(f'logo-chat.webp is {small[0]}x{small[1]}, not the proportions of logo.png '
                        f'{logo[0]}x{logo[1]}: run php .data/make_chat_logo.php')

# --------------------------------------------------------------- the pages
server = DevServer(PHP, BASE)
if not server.alive() and not server.start():
    print('the local site does not answer on ' + BASE)
    sys.exit(1)

counted = {'pages': 0, 'images': set(), 'lastmod': 0, 'stored': 0, 'variants': 0}
try:
    # ---- 4: the sitemap, which is also the list of pages for 1, 3 and 5
    code, sitemap = fetch('/sitemap.xml')
    entries = re.findall(r'<url>(.*?)</url>', sitemap, re.S)
    listed = {}
    for entry in entries:
        loc = re.search(r'<loc>([^<]+)</loc>', entry)
        mod = re.search(r'<lastmod>([^<]*)</lastmod>', entry)
        if loc:
            listed[html.unescape(loc.group(1)).replace(SITE, '', 1)] = mod.group(1) if mod else None
    if code != 200 or not listed:
        problems.append(f'/sitemap.xml: {code}, {len(listed)} addresses')

    today = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d')
    catalogue = day_of('data/products.php', 'data/categories.php')
    saved = set(FACTS['saved_pages'])

    def page_day(path, view, *also):
        files = ['pages/' + view + '.php', *also]
        if path in saved:
            files.append('data/pages.php')
        return day_of(*files)

    newest_post = max([p['date'][:10] for p in FACTS['posts']] or [''])
    expected = {
        '/': page_day('/', 'home', 'data/products.php', 'data/categories.php'),
        '/shop/': page_day('/shop/', 'shop', 'data/products.php', 'data/categories.php'),
        '/about-us/': page_day('/about-us/', 'about'),
        '/contacts/': page_day('/contacts/', 'contacts'),
        '/blog/': max(page_day('/blog/', 'blog'), newest_post),
        '/delivery/': page_day('/delivery/', 'delivery', 'data/shipping.php'),
        '/refund_returns/': page_day('/refund_returns/', 'refund-returns'),
        '/terms/': page_day('/terms/', 'terms'),
        '/privacy/': page_day('/privacy/', 'privacy'),
    }
    for p in FACTS['products']:
        own_day = p['modified'][:10] if re.match(r'\d{4}-\d{2}-\d{2}', p['modified']) else ''
        expected[p['url']] = own_day or day_of('data/products.php')
    for c in FACTS['categories']:
        expected[c['url']] = catalogue
    for p in FACTS['posts']:
        expected[p['url']] = p['date']
    sizes = day_of('data/products.php', 'data/attributes.php')

    for path, mod in listed.items():
        if not mod:
            problems.append(f'sitemap: {path} has no <lastmod>')
            continue
        counted['lastmod'] += 1
        if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', mod):
            problems.append(f'sitemap: {path} lastmod {mod!r} is not a day')
            continue
        if mod > today:
            problems.append(f'sitemap: {path} lastmod {mod} is in the future')
        want = expected.get(path)
        if want is None and re.match(r'/(inner-diameter|length)/', path):
            want = sizes
        if want is None:
            problems.append(f'sitemap: {path} is a kind of page this check has no date for')
        elif mod != want:
            problems.append(f'sitemap: {path} lastmod {mod}, the files it is made of say {want}')
    for path in list(expected):
        if path not in listed:
            problems.append(f'sitemap: {path} is not listed')

    # ---- 2: what the footer should print, from the raw settings
    wanted_social = [s['url'].strip() for s in FACTS['social']
                     if s['name'].strip() and is_profile(s['url'])]
    wanted_same = []
    for url in wanted_social:
        host = urllib.parse.urlsplit(url).netloc.lower()
        if host not in ('wa.me', 'api.whatsapp.com') and url not in wanted_same:
            wanted_same.append(url)

    ticker_default = ['Fuel & oil', 'Gas & welding', 'Water & irrigation', 'Chemicals', 'Compressed air',
                      'Ventilation', 'Abrasive media', 'Cooling systems', 'Hose couplings']
    tags_default = ['SAE J30 R6 / R10', 'DIN 73379', 'SAE J20 R3', 'Cut to length', 'Trade pricing']

    def saved_lines(raw, default):
        out = [l.strip() for l in re.split(r'\r?\n', raw) if l.strip()]
        return out or default

    # ---- 1, 2, 3, 5 on every page the sitemap offers, and on the 404 page
    for path in list(listed) + ['/no-such-page-check-small/']:
        code, page = fetch(path)
        is_404 = path == '/no-such-page-check-small/'
        if code != (404 if is_404 else 200):
            problems.append(f'{path}: answered {code}')
            continue
        counted['pages'] += 1

        for attr, name in (('property', 'og:image'), ('name', 'twitter:image')):
            image = meta(page, attr, name)
            if not image:
                problems.append(f'{path}: no {name}')
                continue
            counted['images'].add(image.split('?')[0])
            file = local_file(image)
            if file is None:
                problems.append(f'{path}: {name} {image} is not an https address of this site')
            elif not os.path.isfile(file):
                problems.append(f'{path}: {name} {image} is not a file here')

        if '&amp;amp;' in page:
            at = page.index('&amp;amp;')
            problems.append(f'{path}: escaped twice: …{page[max(0, at - 30):at + 20]}…')

        footer = re.search(r'<footer class="ftr">(.*?)</footer>', page, re.S)
        soc = re.search(r'<div class="soc"[^>]*>(.*?)</div>', footer.group(1), re.S) if footer else None
        shown = [html.unescape(h) for h in re.findall(r'<a href="([^"]*)"', soc.group(1))] if soc else []
        if shown != wanted_social:
            problems.append(f'{path}: footer social links {shown}, settings say {wanted_social}')
        for link in shown:
            if not is_profile(link):
                problems.append(f'{path}: footer links the front page {link}')

        organisation = None
        for block in re.findall(r'<script type="application/ld\+json">(.*?)</script>', page, re.S):
            try:
                parsed = json.loads(block)
            except ValueError:
                problems.append(f'{path}: a JSON-LD block does not parse')
                continue
            if isinstance(parsed, dict) and parsed.get('@type') == 'Organization':
                organisation = parsed
        if organisation is None:
            problems.append(f'{path}: no Organization block')
        elif organisation.get('sameAs', []) != wanted_same or ('sameAs' in organisation and not wanted_same):
            problems.append(f"{path}: sameAs {organisation.get('sameAs')}, expected {wanted_same or 'none'}")

        for tag in re.findall(r'<img\b[^>]*>', page):
            if re.search(r'src="[^"]*/logo\.png', tag):
                problems.append(f'{path}: an <img> fetches logo.png: {tag[:120]}')
        if not footer:
            problems.append(f'{path}: the page has no footer')
        if '</html>' not in page or '</main>' not in page:
            problems.append(f'{path}: the page is not finished (no </main> or </html>)')
        avatar = re.search(r'<span class="chat-av"[^>]*>(.*?)</span>', page, re.S)
        if avatar:
            tag = re.search(r'<img\b[^>]*>', avatar.group(1))
            if not tag:
                problems.append(f'{path}: the chat header has no picture')
            else:
                tag = tag.group(0)
                src = re.search(r'src="([^"?]*)', tag)
                file = os.path.join(ROOT, src.group(1).lstrip('/')) if src else ''
                if not src or not os.path.isfile(file):
                    problems.append(f'{path}: chat picture is not a file here: {tag[:120]}')
                elif os.path.getsize(file) > AVATAR_MAX_BYTES:
                    problems.append(f'{path}: chat picture {src.group(1)} is {os.path.getsize(file)} bytes')
                if 'loading="lazy"' not in tag:
                    problems.append(f'{path}: chat picture is not lazy: {tag[:120]}')
                if not re.search(r'\bwidth="\d+"', tag) or not re.search(r'\bheight="\d+"', tag):
                    problems.append(f'{path}: chat picture has no width and height: {tag[:120]}')
        elif not is_404 and path == '/':
            problems.append('/: no chat header found, so its picture was not checked')

        if path == '/':
            strip = re.search(r'<div class="tick"[^>]*>(.*?)</div>\s*</div>', page, re.S)
            words = re.findall(r'<i>(.*?)</i>', strip.group(1), re.S) if strip else []
            want_words = [html.escape(w, quote=True).replace('&#x27;', '&#039;')
                          for w in saved_lines(FACTS['ticker_saved'], ticker_default)] * 2
            if words != want_words:
                problems.append(f'/: the scrolling strip prints {words[:4]}…, expected {want_words[:4]}…')
            tags = re.search(r'<div class="hero-tags">(.*?)</div>', page, re.S)
            got_tags = [t.strip() for t in re.findall(r'<span>(.*?)</span>', tags.group(1), re.S)] if tags else []
            want_tags = [html.escape(t, quote=True).replace('&#x27;', '&#039;')
                         for t in saved_lines(FACTS['hero_tags_saved'], tags_default)]
            if got_tags != want_tags:
                problems.append(f'/: the hero tags print {got_tags}, expected {want_tags}')

    # ---- 6: one page, one spelling
    stored = ([p['url'] for p in FACTS['products']] + [c['url'] for c in FACTS['categories']]
              + [p['url'] for p in FACTS['posts']])

    def shout(path):
        """The same address in capitals, percent-escapes left as they are."""
        return re.sub(r'%[0-9a-fA-F]{2}|[a-z]+', lambda m: m.group(0) if m.group(0)[0] == '%' else m.group(0).upper(), path)

    for path in stored:
        expect_hop(path, 200, why='the address the catalogue stores')
        expect_hop(path.rstrip('/'), 200, why='no final slash is not a different spelling')
        counted['stored'] += 1

        prefix = re.match(r'/(product|product-category)/', path)
        head = prefix.group(0) if prefix else '/'
        loud = head + shout(path[len(head):])
        if loud != path:
            expect_hop(loud, 301, path, 'capitals')
            expect_hop(loud.rstrip('/'), 301, path, 'capitals, no final slash')
            expect_hop(loud + '?length=5m&utm_source=x', 301, path + '?length=5m&utm_source=x',
                       'the query string goes along')
            got, to = hop(loud, 'POST')
            if got in (301, 302, 307, 308):
                problems.append(f'POST {loud}: redirected to {to}; only GET and HEAD may be')
            expect_hop(loud, 301, path, 'HEAD', method='HEAD')
            counted['variants'] += 4
        dashed = head + path[len(head):].replace('-', '%2D', 1)
        if dashed != path:
            expect_hop(dashed, 301, path, 'a hyphen percent-encoded')
            counted['variants'] += 1

    odd = [p for p in stored if '%' in p]
    if not any(p == '/product/sandblast-hose-56-mm%c2%b3/' for p in odd) and \
            any('sandblast-hose-56-mm' in p for p in stored):
        problems.append('the sandblast hose is no longer stored as …%c2%b3: check its links and the sitemap')
    for path in odd:
        if path not in listed:
            problems.append(f'{path}: stored percent-encoded but the sitemap spells it otherwise')
        # %C2%B3 is the same address as %c2%b3 (RFC 3986) and a client may
        # send the capitals whatever the link said; curl does. Redirecting
        # one to the other sent such a client round for ever, so both are
        # served, and both name the stored spelling canonical.
        upper = re.sub(r'%[0-9a-f]{2}', lambda m: m.group(0).upper(), path)
        if upper != path:
            expect_hop(upper, 200, why='percent-escapes in capitals are the same address; a 301 loops curl')
            raw =urllib.request.Request(BASE + upper, headers={'User-Agent': 'argflex-check'})
            try:
                with urllib.request.urlopen(raw, timeout=30) as r:
                    shouted = r.read().decode('utf-8', 'replace')
            except Exception as e:
                shouted = ''
                problems.append(f'{upper}: {e}')
            named = re.search(r'<link rel="canonical" href="([^"]*)"', shouted)
            if not named or html.unescape(named.group(1)) != SITE + path:
                problems.append(f'{upper}: canonical {named.group(1) if named else None}, expected {SITE + path}')
        # and through curl, which is what crawl.py uses and what looped
        try:
            curled = subprocess.run(['curl', '-sS', '-L', '--max-redirs', '5', '-o', os.devnull,
                                     '-w', '%{http_code} %{num_redirects}', BASE + path],
                                    capture_output=True, text=True, timeout=60).stdout.strip()
            if curled != '200 0':
                problems.append(f'{path}: curl got "{curled}", expected "200 0" (status, redirects)')
        except (OSError, subprocess.TimeoutExpired) as e:
            problems.append(f'{path}: curl could not be run: {e}')
        # The raw character (…mm³/ as bytes) is not asked for: PHP's built-in
        # server drops a request line that is not ASCII before any code runs,
        # so nothing here could tell a pass from a fault.
        code, page = fetch(path)
        canonical = re.search(r'<link rel="canonical" href="([^"]*)"', page)
        if code != 200 or not canonical or html.unescape(canonical.group(1)) != SITE + path:
            problems.append(f'{path}: {code}, canonical {canonical.group(1) if canonical else None}')

    # a spelling variant must not have become a page of its own somewhere else
    for path in ('/SHOP/', '/Blog/', '/About-Us/'):
        got, _ = hop(path)
        if got == 200:
            problems.append(f'{path}: answers 200')

    # ---- 7: a category that never was
    for path in ('/product-category/no-such-category-zz/', '/product-category/rubber-hoses/no-such-zz/',
                 '/product-category/rubber-hoses/oil-products/no-such-zz/',
                 '/product-category/no-such-zz/also-none/', '/product-category/feed/'):
        expect_hop(path, 404, why='there is no such category')
        code, page = fetch(path)
        if 'noindex' not in (meta(page, 'name', 'robots') or ''):
            problems.append(f'{path}: a 404 that is not noindex')

    for path, lands in {**LEGACY_CATEGORIES, **OTHER_LEGACY}.items():
        expect_hop(path, 301, lands, 'a redirect declared on purpose')
        if hop(lands)[0] != 200:
            problems.append(f'{path}: lands on {lands}, which does not answer 200')
    for path in LEGACY_CATEGORIES:
        if path != '/product-category/':
            expect_hop(path.upper().replace('/PRODUCT-CATEGORY/', '/product-category/'), 301,
                       LEGACY_CATEGORIES[path], 'a legacy category in capitals')

    tops = [c for c in FACTS['categories'] if not c['parent']]
    kids = [c for c in FACTS['categories'] if c['parent']]
    for c in FACTS['categories']:
        expect_hop(c['url'] + 'feed/', 301, c['url'], "a category's feed")
    for kid in kids:
        for top in tops:
            if top['slug'] != kid['parent']:
                expect_hop(f"/product-category/{top['slug']}/{kid['slug']}/", 301, kid['url'],
                           'a real category under the wrong parent')
                break
        expect_hop(f"/product-category/{kid['slug']}/", 301, kid['url'], 'a sub-category asked for at the top')
finally:
    if server.ours():
        server.stop()

print(f"{counted['pages']} pages read, {len(counted['images'])} different share pictures, "
      f"{counted['lastmod']} of {len(listed)} sitemap addresses dated")
print(f"  {counted['stored']} stored addresses answer 200 with no redirect; "
      f"{counted['variants']} other spellings go to them")
print(f"  {len(LEGACY_CATEGORIES)} legacy category redirects and {len(OTHER_LEGACY)} others still stand")

if problems:
    print(f'\n{len(problems)} problem(s)')
    for p in problems[:80]:
        try:
            print('  ' + p)
        except UnicodeEncodeError:
            print('  ' + p.encode('ascii', 'replace').decode())
    sys.exit(1)

print('share pictures, social links, the strip, the sitemap, the chat picture and every address hold')
