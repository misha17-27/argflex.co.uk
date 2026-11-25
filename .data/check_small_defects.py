"""Fourteen small faults that each reached the live site, held shut.

    python .data/check_small_defects.py

Needs the local site on http://localhost:8124 (ARGFLEX_BASE); starts one of
its own when nothing answers there, and stops it again. Reads nothing from
the live site and writes nothing in the repository: the one part that has to
write a product list (4, what an order does) works on a copy in a temporary
directory and deletes it.

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
      AND AN ORDER DOES NOT MOVE IT. The day came from the time of
      data/products.php, which the shop rewrites whenever it counts stock down
      after a sale, so one order re-dated 68 of 96 addresses. The product list
      is now dated by the day it was last EDITED (storage/products-edited.txt,
      written by save_products()). So, in a throwaway copy of the site
      (.data/lastmod_facts.php): an edit records today; an order writes the
      file and leaves every product's date where it was, even on a server
      that had no day recorded yet; and the next edit moves it again.

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
      Nor is %C2%B3 for %c2%b3: the two are one address, curl sends the
      capitals whatever it was given, and a 301 between them never ends. Both
      answer 200 and name the stored spelling canonical.
      (A missing final slash used to be listed here as "not a different
      spelling, not redirected". It is 12 now.)

  7 a category that never was
      any address under /product-category/ answered 301. So: an unknown one is
      404; the eleven the old shop had still go to their parent or the shop,
      and land on a page that answers; a category's /feed/ goes to the
      category; a real category under the wrong parent goes to its own
      address.

And four in the words the pages print, found on the live site on 2026-11-25:

  8 "Exl vat"
      the note beside every price was misspelt: on the product page, in the
      data-suffix site.js rebuilds it from, in the "Price (Exl vat)" table
      heading, on every card and in the quick view; and the footer of every
      page said "Prices shown exclude Tax." The note is a SETTING, and the
      stored copy still holds the misspelling (it is reported below, not
      failed: storage/ is the shop's). So: price_note_wording() turns every
      spelling of "excluding VAT" into "ex VAT" and leaves anything else
      alone; no page in the sitemap, nor /cart/ or /checkout/, contains
      "exl vat" in any case or calls VAT "Tax" in a sentence;
      a product page that shows a price says "ex VAT" in its data-suffix;
      and no template outside the money rows prints tax_label() in prose.
      Whether a sentence here must say VAT is worked out from what Settings
      HOLDS (the label and the country), not asked of tax_word(): the scan
      of the pages was first switched on by tax_word() answering "VAT", so
      undoing that function switched off the one test written for it.
      tax_word() is then held to the same answer directly. The printed
      invoice (admin/views/document.php) named the VAT registration "Tax
      no." beside a footer saying "VAT no."; it takes tax_word() too, and
      its money row keeps the order's own label. And the admin stores and
      previews the note through price_note_wording() (admin/index.php,
      admin/views/settings/tax.php): nothing fetched here shows either, so
      both lines are read from the source.

  9 "cut 1-1 m"
      the generated description of a hose sold in one length gave that
      length as a range. So: tidy_description() rewrites a range whose two
      ends are equal and no other; on every page the meta description,
      og:description and twitter:description are the same sentence and none
      holds "N-N m"; and .data/write_seo_descriptions.php, which wrote it,
      has a branch for the single length.

  10 the specification card had no name
      every gallery tile was alt="", the card (…-spec.svg) included, and the
      size tables pasted into the descriptions as screenshots likewise. So:
      every <img> of a -spec.svg has an alt that names the product and says
      "specification"; a photo's tile still has alt=""; no picture inside a
      product's description has an empty alt, and one that had words keeps
      them. The first version of that read the tag as one string and went
      wrong three ways, none of them on a picture this copy holds: an alt
      written without quotes (alt=Coupling) gained a second alt in front of
      it, which is the one a browser keeps; alt="" inside a title was
      rewritten in the title; and any file with the letters "spec" in its
      name (inspection-photo.jpg, respect.png) was called a specification
      table. So product_description_html() is given each as fixed input.
      And the option picker put the photo back under the specification
      card's name: a tile gives the large picture its alt, and update() in
      site.js, which restores the photo when a length is chosen, restored
      only the src. It must put the photo's own alt back in the same breath.

  11 a cart link named "Cart" showing "0", and a green too pale
      the name did not contain the visible text (WCAG 2.5.3), and "Order on
      WhatsApp" was #128c3f on white, 4.33:1. So: on every page the cart
      link's aria-label contains the figure it shows; site.js rewrites that
      label wherever it rewrites the figure; and .btn-wa in the built
      stylesheet is at least 4.5:1 on its background, resting and hovered.

And three in addresses and markup, from the same crawl:

  12 the slash at the end
      /product/acetylene-hose answered 200 with a copy of the page, and so
      did /shop, /blog and every article. So: every stored address, and every
      other address in the sitemap, asked for without its final slash answers
      ONE 301 to itself with the slash, query string kept, for GET and HEAD
      and not for POST; capitals and no slash together are still one hop; the
      sandblast hose without its slash goes to the stored %c2%b3 spelling
      from either case of the escapes, and curl arrives in one redirect
      rather than never. An address that is not a page stays a 404 instead of
      becoming a redirect to one. An address that already redirected for its
      own reason (the Settings table, a legacy category, a size only one
      product carries) still takes one hop without its slash, not two. And
      SLASH_EXEMPT is answered exactly as it was before the rule existed: the
      basket, the checkout and the account, the admin, the endpoints forms
      and scripts post to, the sitemap and robots.txt. (The admin answers
      302 to its sign-in, or to first-run setup on a copy with no account
      yet; storage/ does not travel, so either is accepted.)
      A DOUBLED final slash was the same fault and outside the first rule,
      which looked only for a missing one: /shop//, /about-us// and
      /product/acetylene-hose// all answered 200 with the page. So every
      stored address and every other sitemap address with a second slash
      takes the same ONE 301 to the single-slash address, query kept; an
      empty segment in the middle likewise; a POST is not redirected; the
      basket with two slashes still answers 200; the sandblast hose with
      two slashes goes to its stored spelling and curl ends there in one
      redirect; and what is not a page stays a 404.

  13 a search was an indexable page
      /shop/?q=fuel said "index, follow" and named /shop/ canonical, and so
      did a search that found nothing: one indexable copy per phrase, each
      with a canonical its content contradicts. So: any /shop/ address with a
      non-empty q is "noindex, follow" and prints NO canonical, with results
      and without, sorted, filtered or paged; /shop/ itself, an empty q, and
      sorting or filtering without a search are indexable with the canonical
      /shop/ as before; robots.txt does not Disallow a search (Google would
      then never read the noindex); and /search.php, which answers the box in
      the header with JSON, still sends X-Robots-Tag noindex.

  14 the company's numbers were on the page and not in the markup
      the footer, /terms/ and /privacy/ print the company number and the VAT
      number once Settings -> General holds them; Organization said neither.
      So: on every page the block carries "vatID" exactly when the VAT number
      is filled in and "identifier" (a PropertyValue naming Companies House)
      exactly when the company number is, each with the value the footer
      prints, and no key this check does not expect — no legalName, no
      addressRegion, for which Settings has nothing to go on. Both boxes are
      empty in this copy, so organization_registration() is also asked, with
      made-up numbers and nothing written, what a filled box adds: its own
      property and no other; and a blank one, nothing. That alone left the
      pages comparing nothing with nothing, and the merge into the block
      resting on the text of one call in inc/config.php, which a call whose
      result is thrown away satisfies. So the WHOLE block is asked for with
      made-up numbers (organization_block()) and must carry them, and every
      page's block must be the one that same function builds from Settings.
"""
import datetime
import html
import json
import os
import re
import shutil
import socket
import subprocess
import sys
import tempfile
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

# 12: what answers without a final slash ON PURPOSE, and what it answered on
# the day the redirect was added (requested before and after, 2026-11-25).
# A status alone, or a status and the Location. None of these may ever gain a
# hop: a form posts to some, a script fetches others, and the basket, the
# checkout and the account are noindex pages a visitor is in the middle of.
SLASH_EXEMPT = {
    '/cart': 200, '/checkout': 200, '/wishlist': 200, '/compare': 200,
    '/my-account': 200, '/my-account/orders': 200,
    # the admin sends a visitor to its sign-in, or to first-run setup on a copy
    # with no account yet (storage/ never travels with the repository). What
    # is held is that it is one 302 into the admin, not a 301 to /admin/.
    '/admin': (302, ('/admin/login', '/admin/setup')),
    '/chat-lead': 405, '/coupon-check': 405,
    '/contact-send': (302, '/contacts/'),
    '/review-send': (302, '/shop/?review=closed#reviews'),
    '/quick-view.php': 404,          # no slug given; a real file, never routed
    '/search.php': 200,
    '/sitemap.xml': 200, '/sitemap_index.xml': 200, '/robots.txt': 200,
}
# 12: not pages, with or without the slash. A 404 must not become a redirect.
NEVER_PAGES = ['/no-such-page-check-small', '/product/no-such-product-zz', '/product',
               '/inner-diameter', '/product-category/no-such-category-zz', '/shop/no-such-zz']

# 14: the keys an Organization block may carry, and the two the numbers add.
ORGANISATION_KEYS = {'@context', '@type', 'name', 'url', 'logo', 'telephone', 'email', 'address'}
ADDRESS_KEYS = {'@type', 'streetAddress', 'addressLocality', 'postalCode', 'addressCountry'}


def registration(company, vat):
    """What the two boxes of Settings -> General add to Organization.
    Worked out here from the raw values, not asked of the site."""
    out = {}
    if vat.strip():
        out['vatID'] = vat.strip()
    if company.strip():
        out['identifier'] = {'@type': 'PropertyValue', 'propertyID': 'Companies House',
                             'name': 'Company number', 'value': company.strip()}
    return out


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
    # a tuple of locations: any one of them is the expected answer
    allowed = location if isinstance(location, tuple) else (location,)
    if got != status or (status in (301, 302) and to not in allowed):
        want = f"{status} {' or '.join(allowed)}".strip()
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


def products_day():
    """The day the product list was last EDITED: the recorded one, else the
    file's own. Read here from the two files, not asked of the site."""
    try:
        with open(os.path.join(ROOT, 'storage', 'products-edited.txt'), encoding='ascii') as f:
            held = f.read().strip()
    except (OSError, ValueError):
        held = ''
    return held if re.fullmatch(r'\d{4}-\d{2}-\d{2}', held) else day_of('data/products.php')


def order_facts():
    """Run .data/lastmod_facts.php in a copy of inc/ and data/; the real
    product list is never written by a check."""
    sandbox = tempfile.mkdtemp(prefix='argflex-lastmod-')
    try:
        for part in ('inc', 'data'):
            shutil.copytree(os.path.join(ROOT, part), os.path.join(sandbox, part))
        os.mkdir(os.path.join(sandbox, 'storage'))
        out = subprocess.run([PHP, os.path.join('.data', 'lastmod_facts.php'), sandbox], cwd=ROOT,
                             capture_output=True, text=True, encoding='utf-8', errors='replace')
        try:
            return json.loads(out.stdout)
        except ValueError:
            return {'error': (out.stdout + out.stderr)[:600]}
    finally:
        shutil.rmtree(sandbox, ignore_errors=True)


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

# ---- 4: what an order does to the dates
order = order_facts()
if 'error' in order:
    problems.append('lastmod_facts.php did not answer with JSON: ' + order['error'])
else:
    day, old = order['today'], order['old']
    if not order['counted']:
        problems.append('lastmod_facts.php found no product to count stock on')
    if order['after_edit'] != {'recorded': day, 'day': day}:
        problems.append(f"an edit of the product list did not record today: {order['after_edit']}")
    if order['unrecorded'] != {'recorded': '', 'day': old}:
        problems.append(f"with no day recorded the file's own ({old}) should be used: {order['unrecorded']}")
    sold = order['after_order']
    if sold['left'] != 4 or sold['file_day'] != day:
        problems.append(f"the order did not count stock down and write the file ({sold}); "
                        'this check no longer exercises what it is for')
    if sold['recorded'] != old or sold['day'] != old:
        problems.append(f"an order moved the product list's day from {old} to {sold['day']} "
                        f"(recorded {sold['recorded']!r})")
    if list(sold['sitemap']) != [old]:
        problems.append(f"after an order the sitemap dates products {sold['sitemap']}, expected all {old}")
    if order['after_second_order'] != {'left': 2, 'day': old}:
        problems.append(f"a second order: {order['after_second_order']}, expected 2 left and {old}")
    if order['after_second_edit'] != {'day': day}:
        problems.append(f"an edit after the orders did not move the day to {day}: {order['after_second_edit']}")

lines = CASES['page_lines']
if lines['default'] != ['Fuel &amp; oil', '&lt;b&gt;']:
    problems.append(f"page_lines fallback is not escaped once: {lines['default']!r}")
if lines['blank-default'] != []:
    problems.append(f"page_lines with nothing to show returned {lines['blank-default']!r}")

# ---- 8: one spelling of "ex VAT", whatever Settings holds
EX_VAT = ['Exl vat', 'Exl. VAT', 'excl vat', 'EXCL. VAT', 'ex vat', 'Ex. VAT', 'exc VAT',
          'Excluding VAT', 'exclusive of VAT', ' exl  vat ', 'ex VAT', 'exclude Tax']
KEPT_NOTES = ['+ VAT', 'incl. VAT', 'excl. VAT and delivery', 'trade price', '']
notes = CASES['price_note_wording']
for stored in EX_VAT:
    if notes.get(stored) != 'ex VAT':
        problems.append(f'price_note_wording({stored!r}) is {notes.get(stored)!r}, expected "ex VAT"')
for stored in KEPT_NOTES:
    if notes.get(stored) != stored:
        problems.append(f'price_note_wording({stored!r}) is {notes.get(stored)!r}; a note that is not '
                        '"excluding VAT" is the shop\'s own and must come back untouched')
MISSPELT = re.compile(r'\bexl\.?\s*vat\b', re.I)
# "Tax" doing the work of the word VAT in a sentence. "Tax at 20%" is a money
# row and is not this.
TAX_IN_PROSE = re.compile(r'\b(?:exclud\w+|excl\.?|ex|plus|of)\s+Tax\b|\bTax (?:is|no\.|number)\b')
if FACTS['tax_enabled'] and MISSPELT.search(FACTS['price_suffix']):
    problems.append(f"price_suffix() prints {FACTS['price_suffix']!r}")
with open(os.path.join(ROOT, 'inc', 'config.php'), encoding='utf-8', errors='replace') as f:
    default_note = re.search(r"'price_suffix'\s*=>\s*'([^']*)'", f.read())
if not default_note or default_note.group(1) != 'ex VAT':
    problems.append(f"inc/config.php: the default price_suffix is {default_note.group(1) if default_note else None!r}, "
                    'expected "ex VAT" (what a new shop starts with)')
# A sentence takes tax_word(); only a money row or an invoice takes the label.
for rel in ('inc/footer.php', 'pages/delivery.php', 'pages/terms.php', 'partials/buy-extras.php'):
    with open(os.path.join(ROOT, rel), encoding='utf-8', errors='replace') as f:
        if 'tax_label()' in f.read():
            problems.append(f'{rel}: prints tax_label() in a sentence; the stored label is the generic '
                            '"Tax", so prose uses tax_word()')

# What a sentence must call the tax, worked out from what Settings HOLDS and
# not from tax_word() itself: a gate that asks the function under test
# switches itself off the moment that function is undone. An empty label
# counts as VAT because tax_label() falls back to it.
_label = FACTS['tax_label_stored'].strip()
_home = FACTS['store_country_stored'].strip().upper()
WANT_VAT = _label.lower() in ('', 'vat') or (_label.lower() == 'tax' and _home in ('GB', ''))
if WANT_VAT and FACTS['tax_word'] != 'VAT':
    problems.append(f"tax_word() is {FACTS['tax_word']!r} with the label stored as {_label!r} in "
                    f"{_home or 'no country'}; a sentence here says VAT")
if not WANT_VAT and FACTS['tax_word'] != _label:
    problems.append(f"tax_word() is {FACTS['tax_word']!r}; a label somebody chose ({_label!r}) is used as it stands")

# The invoice names the VAT registration in the same word as the footer. It
# cannot be fetched with a number on it here (the box is empty and storage/
# is the shop's), so the line is read. Its money row, "… at 20%", stays on
# the label the order was placed under.
with open(os.path.join(ROOT, 'admin', 'views', 'document.php'), encoding='utf-8', errors='replace') as f:
    document = f.read()
if not re.search(r"<\?= e\(tax_word\(\)\) \?> no\. <\?= e\(\$values\['vat_number'\]\) \?>", document) \
        or re.search(r'tax_label\(\)\) \?> no\.', document):
    problems.append('admin/views/document.php: the invoice names the VAT registration with tax_label() '
                    '("Tax no."); the footer says "VAT no." for the same number, so this takes tax_word()')
if not re.search(r"\$o\['tax_label'\] \?\? tax_label\(\)", document):
    problems.append("admin/views/document.php: the money row no longer prints the order's own tax label "
                    "($o['tax_label'] ?? tax_label()); that row is the shop's accounting and keeps it")

# The admin stores and previews the note in the spelling the pages print.
# Neither is shown by anything fetched here, so each line is read.
with open(os.path.join(ROOT, 'admin', 'index.php'), encoding='utf-8', errors='replace') as f:
    if not re.search(r"^\s*\$v\['price_suffix'\]\s*=\s*price_note_wording\(\$str\('price_suffix'", f.read(), re.M):
        problems.append('admin/index.php: save_settings_tab() stores price_suffix without price_note_wording(), '
                        'so Settings -> Tax would save "Exl vat" again')
with open(os.path.join(ROOT, 'admin', 'views', 'settings', 'tax.php'), encoding='utf-8', errors='replace') as f:
    if not re.search(r"e\(price_note_wording\(\s*\(string\)\s*\$values\['price_suffix'\]\s*\)\)", f.read()):
        problems.append('admin/views/settings/tax.php: the preview prints the stored price_suffix raw; '
                        'it must show what the shop prints (price_note_wording)')

# ---- 9: one length is not a range
EQUAL_RANGE = re.compile(r'(?<![\d.,])(\d+(?:\.\d+)?)\s*[-–]\s*(\d+(?:\.\d+)?)\s*m\b')


def equal_ranges(text):
    return [m.group(0) for m in EQUAL_RANGE.finditer(text) if float(m.group(1)) == float(m.group(2))]


lengths = CASES['single_length_wording']
for stored, printed in lengths.items():
    if equal_ranges(stored):
        if equal_ranges(printed) or printed == stored:
            problems.append(f'tidy_description left a range with one end: {printed!r}')
        if 'ex VAT' not in printed or printed.count('£') != stored.count('£'):
            problems.append(f'tidy_description changed more than the range: {stored!r} -> {printed!r}')
    elif printed != stored:
        problems.append(f'tidy_description rewrote a description with no such range: {stored!r} -> {printed!r}')
if not any(equal_ranges(s) for s in lengths):
    problems.append('this check no longer gives tidy_description a range with equal ends')
with open(os.path.join(ROOT, '.data', 'write_seo_descriptions.php'), encoding='utf-8', errors='replace') as f:
    if '$low === $high' not in f.read():
        problems.append('.data/write_seo_descriptions.php: price_phrase() has no branch for a product '
                        'sold in one length, so it writes "cut 1-1 m" again')

# ---- 10: the picture of the specification has a name
alts = CASES['product_image_alt']
if alts['card'] != 'Acetylene hose specification' or alts['photo'] != '':
    problems.append(f'product_image_alt: {alts}, expected the card named and the photo left empty')
described = CASES['product_description_html']
if 'alt="Fuel &quot;R6&quot; hose specification table"' not in described['empty'] or 'alt=""' in described['empty']:
    problems.append(f"product_description_html did not name an alt=\"\" screenshot: {described['empty']}")
if described['missing'].count('alt="Fuel hose"') != 1:
    problems.append(f"product_description_html did not name a picture with no alt: {described['missing']}")
if 'alt="Acetylene hose"' not in described['kept'] or described['kept'].count('alt=') != 1:
    problems.append(f"product_description_html replaced an alt somebody wrote: {described['kept']}")
if described['none'] != '<p>No picture.</p>':
    problems.append(f"product_description_html changed a description with no picture: {described['none']}")
# The tag read one attribute at a time: what the exact answer is for each.
# (name "Hose X"; see small_facts.php for the inputs.)
TABLE = 'Hose X specification table'
for case, want, why in [
    ('unquoted', '<p><img src=a.jpg alt=Coupling></p>',
     'an alt written without quotes is somebody\'s, and gained a second alt in front of it'),
    ('in_title', '<img alt="Hose X" src="a.png" title=\'x alt="" y\'>',
     'alt="" inside a title is the title\'s text; the title was rewritten and the picture left unnamed'),
    ('gt_in_value', '<img src="a.png" title="6mm -> 8mm" alt="Owner">',
     'a > inside a quoted value does not end the tag; the alt after it is somebody\'s'),
    ('gt_then_empty', '<img src="a.png" title="a > b" alt="Hose X">',
     'an empty alt after a > in a value is filled where it stands, once'),
    ('bare', '<img src="a.png" alt="Hose X">', 'a bare alt is an empty one and is filled where it stands'),
    ('single_quotes', '<img alt="Hose X" src=\'b.png\'>', "alt='' is empty as well"),
    ('inspection', '<img src="/x/inspection-photo.jpg" alt="Hose X">',
     '"inspection" has the letters spec in it and is no specification table'),
    ('respect', '<img src="respect.png" alt="Hose X">', '"respect" likewise'),
    ('special', '<img alt="Hose X" src="special-offer.webp">', '"special" likewise'),
    ('spec_word', f'<img src="/x/hose-spec.png" alt="{TABLE}">', 'spec as a word of the file name is a table'),
    ('specs_word', f'<img src="specs_table.jpg" alt="{TABLE}">', 'specs as a word of the file name is a table'),
    ('unquoted_shot', f'<img alt="{TABLE}" src=Screenshot-1.png>',
     'a src without quotes is read too, and a screenshot is a table'),
]:
    if described.get(case) != want:
        problems.append(f'product_description_html [{case}]: {described.get(case)!r}, expected {want!r} ({why})')

# ---- 14: what a filled box adds to Organization, and a blank one does not
said_registration = CASES['organization_registration']
for case, (company, vat) in {'none': ('', ''), 'blank': ('  ', '\t '), 'company': (' 01234567 ', ''),
                             'vat': ('', ' GB123456789 '), 'both': ('01234567', 'GB123456789')}.items():
    got = said_registration.get(case)
    got = {} if got == [] else got          # PHP's empty array is a JSON list
    if got != registration(company, vat):
        problems.append(f'organization_registration [{case}]: {got}, expected {registration(company, vat)}')
if registration('', '') != {} or set(registration('1', '2')) != {'vatID', 'identifier'}:
    problems.append('this check itself no longer works out the two register numbers')
# The block itself, built with made-up numbers: they must be INSIDE it. (This
# was a search of inc/config.php for the text of the call, which is there
# just the same when the call's result goes nowhere.) The pages are then held
# to this same builder further down, at "---- 14".
ORGANISATION = CASES['organization_block']
both = ORGANISATION['both']
if {k: both[k] for k in ('vatID', 'identifier') if k in both} != registration('01234567', 'GB123456789'):
    problems.append('organization_block(): handed a company number and a VAT number, the Organization block '
                    f"states {({k: both[k] for k in ('vatID', 'identifier') if k in both}) or 'neither'}; "
                    'a number filled in would not reach the markup')
if set(both) != ORGANISATION_KEYS | {'vatID', 'identifier'}:
    problems.append(f'organization_block(): with both numbers the block has the keys {sorted(both)}, expected '
                    f"{sorted(ORGANISATION_KEYS | {'vatID', 'identifier'})}")
if {k: v for k, v in both.items() if k not in ('vatID', 'identifier')} != \
        {k: v for k, v in ORGANISATION['as_set'].items() if k not in ('vatID', 'identifier')}:
    problems.append('organization_block(): the numbers changed something in the block besides their own two keys')

# ---- 11: the green of "Order on WhatsApp", and who rewrites the cart's name
def luminance(colour):
    colour = colour.lstrip('#')
    if len(colour) == 3:
        colour = ''.join(c * 2 for c in colour)
    parts = [int(colour[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    parts = [c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4 for c in parts]
    return 0.2126 * parts[0] + 0.7152 * parts[1] + 0.0722 * parts[2]


def contrast(one, two):
    a, b = sorted((luminance(one), luminance(two)), reverse=True)
    return (a + 0.05) / (b + 0.05)


for sheet in ('pages.css', 'site.css'):
    with open(os.path.join(ROOT, 'assets', 'css', sheet), encoding='utf-8', errors='replace') as f:
        css = re.sub(r'/\*.*?\*/', '', f.read(), flags=re.S)
    states = {name: re.search(re.escape(name) + r'\{([^}]*)\}', css) for name in ('.btn-wa', '.btn-wa:hover')}
    for name, rule in states.items():
        ink = re.search(r'(?<![-\w])color:\s*(#[0-9a-fA-F]{3,6})\b', rule.group(1)) if rule else None
        paper = re.search(r'background:\s*(#[0-9a-fA-F]{3,6})\b', rule.group(1)) if rule else None
        if not ink or not paper:
            problems.append(f'{sheet}: {name} no longer states a colour and a background this check can read')
        elif contrast(ink.group(1), paper.group(1)) < 4.5:
            problems.append(f'{sheet}: {name} is {ink.group(1)} on {paper.group(1)}, '
                            f'{contrast(ink.group(1), paper.group(1)):.2f}:1, under 4.5:1'
                            + (' (run python .data/build_css.py)' if sheet == 'site.css' else ''))

with open(os.path.join(ROOT, 'assets', 'js', 'site.js'), encoding='utf-8', errors='replace') as f:
    script = f.read()
badges = re.search(r'function badges\(\) \{(.*?)\n  \}', script, re.S)
if not badges or not re.search(r"setAttribute\('aria-label',\s*'Cart, ' \+ count", badges.group(1)):
    problems.append("site.js: badges() rewrites the cart's figure but not the link's aria-label, "
                    'so the name stops containing what is shown as soon as something is added')
if "main.alt = btn.dataset.alt" not in script:
    problems.append('site.js: a gallery tile swaps the large picture but not its alt, so the '
                    "specification card is announced under the photo's name")
# …and the other way about: update() puts the photo back when an option is
# chosen, and has to put the photo's own words back with it. Read as code,
# comments out, and inside the one branch that writes the src.
bare_script = re.sub(r'/\*.*?\*/|(?<![:\'"\\])//[^\n]*', '', script, flags=re.S)
kept = re.search(r"if \(!mainImg\.dataset\.base\) \{(.*?)\}", bare_script, re.S)
if not kept or not re.search(r"mainImg\.dataset\.baseAlt = mainImg\.getAttribute\('alt'\)", kept.group(1)):
    problems.append("site.js: update() remembers the photo's address (dataset.base) but not its alt beside it, "
                    'so there is nothing to put back')
restored = re.search(r"if \(want && mainImg\.getAttribute\('src'\) !== want\) \{(.*?)\n        \}", bare_script, re.S)
if not restored or "mainImg.setAttribute('src', want)" not in restored.group(1) \
        or not re.search(r"mainImg\.setAttribute\('alt', mainImg\.dataset\.baseAlt", restored.group(1)):
    problems.append("site.js: update() puts the photo back without its alt, so after the specification card "
                    "has been looked at, choosing a length shows the photo under the card's name")
elif not re.search(r"classList\.toggle\('on', b\.dataset\.src === want\)", restored.group(1)):
    problems.append('site.js: update() puts the photo back and leaves the specification tile marked as the one on show')

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

counted = {'pages': 0, 'images': set(), 'lastmod': 0, 'stored': 0, 'variants': 0,
           'cards': 0, 'described': 0, 'suffixes': 0, 'carts': 0,
           'slashless': 0, 'doubled': 0, 'exempt': 0, 'searches': 0, 'organisations': 0}
wanted_registration = registration(FACTS['company_number'], FACTS['vat_number'])


def head_of(path):
    """Status, robots meta, every canonical the page prints, the page itself
    and the response headers. Nothing followed: a search that redirected
    would not be the address that was asked about."""
    target = urllib.request.Request(BASE + path, headers={'User-Agent': 'argflex-check'})

    class Stay(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None

    try:
        with urllib.request.build_opener(Stay).open(target, timeout=30) as r:
            code, page, sent = r.status, r.read().decode('utf-8', 'replace'), r.headers
    except urllib.error.HTTPError as e:
        code, page, sent = e.code, e.read().decode('utf-8', 'replace'), e.headers
    except Exception as e:
        return 0, None, [], str(e), {}
    canonicals = [html.unescape(c) for c in re.findall(r'<link\s+rel="canonical"\s+href="([^"]*)"', page)]
    return code, meta(page, 'name', 'robots'), canonicals, page, sent


def words_on(path, page):
    """8 to 11 on one page: the words it prints, and the names it gives."""
    plain = html.unescape(re.sub(r'<[^>]+>', ' ', re.sub(r'<(script|style)\b.*?</\1>', ' ', page, flags=re.S)))
    for text in (page, plain):
        found = MISSPELT.search(text)
        if found:
            problems.append(f'{path}: says "{found.group(0)}": …{text[max(0, found.start() - 40):found.end() + 10]}…')
            break
    if WANT_VAT:        # from the raw settings; see where it is worked out
        found = TAX_IN_PROSE.search(plain)
        if found:
            problems.append(f'{path}: calls VAT "Tax" in a sentence: …{plain[max(0, found.start() - 40):found.end() + 20]}…')
    for suffix in re.findall(r'data-suffix="([^"]*)"', page):
        counted['suffixes'] += 1
        if html.unescape(suffix) != FACTS['price_suffix']:
            problems.append(f"{path}: data-suffix is {suffix!r}, price_suffix() is {FACTS['price_suffix']!r}")

    said = [meta(page, 'name', 'description'), meta(page, 'property', 'og:description'),
            meta(page, 'name', 'twitter:description')]
    if len(set(said)) != 1 or not said[0]:
        problems.append(f'{path}: description, og:description and twitter:description are not one sentence: {said}')
    for one in equal_ranges(said[0] or ''):
        problems.append(f'{path}: the description gives one length as a range, "{one}": {said[0]}')

    heading = re.search(r'<h1[^>]*>(.*?)</h1>', page, re.S)
    name = html.unescape(re.sub(r'<[^>]+>', '', heading.group(1))).strip() if heading else ''
    is_product = path.startswith('/product/')
    for tag in re.findall(r'<img\b[^>]*>', page):
        src = re.search(r'\bsrc="([^"?]*)', tag)
        alt = re.search(r'\balt="([^"]*)"', tag)
        said_alt = html.unescape(alt.group(1)).strip() if alt else ''
        if src and src.group(1).endswith('-spec.svg'):
            counted['cards'] += 1
            if not said_alt.endswith(' specification') or len(said_alt) <= len(' specification'):
                problems.append(f'{path}: the specification card has alt {said_alt!r}: {tag[:110]}')
        elif is_product and src and '/assets/img/content/' in src.group(1):
            counted['described'] += 1
            if said_alt == '':
                problems.append(f'{path}: a picture in the description has no alt: {src.group(1)}')
    if is_product:
        for tile in re.findall(r'<button\b[^>]*data-src="[^"]*"[^>]*>\s*<img\b[^>]*>', page):
            photo = '-spec.svg' not in tile
            # The gallery's own card, as opposed to one on a card of some
            # other product further down the page, is named after THIS one.
            if not photo and f'alt="{html.escape(name + " specification", quote=True)}"'.replace('&#x27;', '&#039;') not in tile:
                problems.append(f'{path}: the specification card in the gallery of {name!r} is not named '
                                f'after it: {tile[-130:]}')
            if photo and not re.search(r'<img\b[^>]*\balt=""', tile):
                problems.append(f"{path}: a photo's gallery tile repeats the photo and should stay alt=\"\": {tile[-110:]}")
            if not re.search(r'data-alt="[^"]+"', tile):
                problems.append(f'{path}: a gallery tile has no data-alt for the large picture to take: {tile[:110]}')

    carts = re.findall(r'<a\b[^>]*href="/cart/"[^>]*>(?:(?!</a>).)*?data-count="cart"[^>]*>([^<]*)<', page, re.S)
    labels = re.findall(r'<a\b[^>]*href="/cart/"[^>]*aria-label="([^"]*)"[^>]*>(?:(?!</a>).)*?data-count="cart"', page, re.S)
    if len(carts) != 1 or len(labels) != 1:
        problems.append(f'{path}: {len(carts)} cart link(s) with a count, {len(labels)} of them labelled; expected one')
    else:
        counted['carts'] += 1
        shown, label = carts[0].strip(), html.unescape(labels[0])
        if not shown or not re.search(r'(?<!\d)' + re.escape(shown) + r'(?!\d)', label) or 'cart' not in label.lower():
            problems.append(f'{path}: the cart link shows "{shown}" and is named "{label}"; '
                            'the name must contain what is shown')


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
    catalogue = max(products_day(), day_of('data/categories.php'))
    saved = set(FACTS['saved_pages'])

    def page_day(path, view, *also):
        files = ['pages/' + view + '.php', *also]
        if path in saved:
            files.append('data/pages.php')
        return day_of(*files)

    newest_post = max([p['date'][:10] for p in FACTS['posts']] or [''])
    expected = {
        '/': max(page_day('/', 'home'), catalogue),
        '/shop/': max(page_day('/shop/', 'shop'), catalogue),
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
        expected[p['url']] = own_day or products_day()
    for c in FACTS['categories']:
        expected[c['url']] = catalogue
    for p in FACTS['posts']:
        expected[p['url']] = p['date']
    sizes = max(products_day(), day_of('data/attributes.php'))

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

    # ---- 4b: "Last updated" on a policy is that same day, not today's.
    # The three policy pages printed date('j F Y') on every request, so each
    # claimed daily to have been revised that day (seen on the live site on
    # 2026-11-25: the 24th in one crawl, the 25th in the next). The line is
    # now the page's own day, the one the sitemap gives for it.
    for path, view in (('/privacy/', 'privacy'), ('/terms/', 'terms'), ('/refund_returns/', 'refund-returns')):
        want = page_day(path, view)
        code, page = fetch(path)
        stated = re.findall(r'Last updated <time datetime="([^"]*)">([^<]*)</time>\.', page)
        if code != 200 or len(stated) != 1:
            problems.append(f'{path}: {code}, says "Last updated" {len(stated)} time(s) with a dated <time>')
            continue
        machine, printed = stated[0]
        if machine != want:
            problems.append(f'{path}: says it was last updated {machine}, the files it is made of say {want}')
        spoken = datetime.datetime.strptime(want, '%Y-%m-%d')
        spoken = f"{spoken.day} {spoken.strftime('%B %Y')}"
        if printed != spoken:
            problems.append(f'{path}: prints "{printed}" for {want}, which is {spoken}')
        if listed.get(path) and listed[path] != machine:
            problems.append(f'{path}: the page says {machine} and the sitemap says {listed[path]}')
    # No template may print the day of the request as a fact about the page.
    for folder in ('pages', 'partials'):
        for name in sorted(os.listdir(os.path.join(ROOT, folder))):
            if not name.endswith('.php'):
                continue
            with open(os.path.join(ROOT, folder, name), encoding='utf-8', errors='replace') as f:
                source = f.read()
            for call in re.findall(r"\bdate\(\s*'[^']*'\s*\)", source):
                if call not in ("date('Y')",):
                    problems.append(f'{folder}/{name}: prints {call}, the day of the request, as if it were a date of the page')

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
        words_on(path, page)

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
        if organisation is not None:
            # ---- 14: the two register numbers, exactly as Settings has them
            counted['organisations'] += 1
            stated = {k: organisation[k] for k in ('vatID', 'identifier') if k in organisation}
            if stated != wanted_registration:
                problems.append(f'{path}: Organization states {stated or "no register number"}, '
                                f'Settings give {wanted_registration or "none"}')
            # and the block is the one organization_block() builds from the
            # settings, so the pages cannot be drawing theirs some other way
            # while the made-up numbers above go through a builder nobody uses
            if {k: v for k, v in organisation.items() if k != 'sameAs'} != ORGANISATION['as_set']:
                problems.append(f'{path}: the Organization block is not the one organization_block() builds, '
                                'so a number filled in would not reach the markup')
            extra = set(organisation) - ORGANISATION_KEYS - {'sameAs'} - set(wanted_registration)
            if extra:
                problems.append(f'{path}: Organization carries {sorted(extra)}, which no setting stands behind')
            place = organisation.get('address')
            if not isinstance(place, dict) or set(place) - ADDRESS_KEYS:
                problems.append(f'{path}: Organization address is {place}; Settings hold one line with no '
                                'county, so nothing beyond street, town, postcode and country can be said')
            # and a number in the markup is one the page shows
            foot = html.unescape(re.sub(r'<[^>]+>', ' ', footer.group(1))) if footer else ''
            for number in (wanted_registration.get('vatID'),
                           (wanted_registration.get('identifier') or {}).get('value')):
                if number and number not in foot:
                    problems.append(f'{path}: Organization states {number} and the footer does not print it')

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

    # ---- 8 to 11 where the sitemap does not go: the basket and the checkout
    # print the note too ("Subtotal (ex VAT)") and carry the same header.
    for path in ('/cart/', '/checkout/'):
        code, page = fetch(path)
        if code != 200:
            problems.append(f'{path}: answered {code}')
        else:
            words_on(path, page)
    if not counted['cards'] or not counted['described'] or not counted['suffixes']:
        problems.append(f"found {counted['cards']} specification cards, {counted['described']} pictures in "
                        f"descriptions and {counted['suffixes']} price notes: one of these checks reads nothing")

    # ---- 6: one page, one spelling
    stored = ([p['url'] for p in FACTS['products']] + [c['url'] for c in FACTS['categories']]
              + [p['url'] for p in FACTS['posts']])

    def shout(path):
        """The same address in capitals, percent-escapes left as they are."""
        return re.sub(r'%[0-9a-fA-F]{2}|[a-z]+', lambda m: m.group(0) if m.group(0)[0] == '%' else m.group(0).upper(), path)

    for path in stored:
        expect_hop(path, 200, why='the address the catalogue stores')
        counted['stored'] += 1

        # ---- 12: without its slash, one 301 to itself
        bare = path.rstrip('/')
        expect_hop(bare, 301, path, 'no final slash')
        expect_hop(bare, 301, path, 'no final slash, HEAD', method='HEAD')
        expect_hop(bare + '?length=5m&utm_source=x', 301, path + '?length=5m&utm_source=x',
                   'no final slash; the query string goes along')
        got, to = hop(bare, 'POST')
        if got in (301, 302, 307, 308) and to == path:
            problems.append(f'POST {bare}: redirected to {to}; only GET and HEAD may be')
        counted['slashless'] += 1

        # ---- 12: and with a slash too many, the same one hop
        expect_hop(path + '/', 301, path, 'a doubled final slash')
        expect_hop(path + '/?length=5m', 301, path + '?length=5m', 'a doubled final slash; the query string goes along')
        counted['doubled'] += 1

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
            # 12: and without the slash it goes to the STORED spelling, where
            # a client that sends capitals back is served, not sent on again
            expect_hop(upper.rstrip('/'), 301, path, 'capital escapes, no final slash')
            expect_hop(upper.rstrip('/') + '?x=1', 301, path + '?x=1', 'capital escapes, no final slash, a query')
            expect_hop(upper + '/', 301, path, 'capital escapes, a doubled final slash')
        # a slash too many ends as a slash too few does: one redirect, then
        # the page, even for a client that sends the escapes back in capitals
        try:
            curled = subprocess.run(['curl', '-sS', '-L', '--max-redirs', '5', '-o', os.devnull,
                                     '-w', '%{http_code} %{num_redirects}', BASE + path + '/'],
                                    capture_output=True, text=True, timeout=60).stdout.strip()
            if curled != '200 1':
                problems.append(f'{path}/: curl got "{curled}", expected "200 1" (status, redirects): '
                                'the doubled slash must cost one hop and end')
        except (OSError, subprocess.TimeoutExpired) as e:
            problems.append(f'{path}/: curl could not be run: {e}')
        try:
            curled = subprocess.run(['curl', '-sS', '-L', '--max-redirs', '5', '-o', os.devnull,
                                     '-w', '%{http_code} %{num_redirects}', BASE + path.rstrip('/')],
                                    capture_output=True, text=True, timeout=60).stdout.strip()
            if curled != '200 1':
                problems.append(f"{path.rstrip('/')}: curl got \"{curled}\", expected \"200 1\" (status, redirects): "
                                'the missing slash must cost one hop and end')
        except (OSError, subprocess.TimeoutExpired) as e:
            problems.append(f"{path.rstrip('/')}: curl could not be run: {e}")
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

    # ---- 12: the slash at the end, everywhere else
    # every other page the sitemap offers: /shop, /blog, the policies, sizes
    for path in listed:
        if path == '/' or path in stored:
            continue
        expect_hop(path.rstrip('/'), 301, path, 'no final slash')
        expect_hop(path.rstrip('/') + '?page=2', 301, path + '?page=2', 'no final slash; the query string goes along')
        counted['slashless'] += 1
        expect_hop(path + '/', 301, path, 'a doubled final slash')
        counted['doubled'] += 1
    if '/shop/' not in listed or '/blog/' not in listed:
        problems.append('the sitemap no longer lists /shop/ and /blog/, so their slashless forms went unasked')

    # A slash too many, spelled out for the addresses it was found on, and the
    # other ways of writing one: three of them, HEAD, a query, and an empty
    # segment in the middle of an address that is not a stored one.
    expect_hop('/shop//', 301, '/shop/', 'a doubled final slash')
    expect_hop('/shop///', 301, '/shop/', 'three final slashes: still one hop, not one per slash')
    expect_hop('/shop//', 301, '/shop/', 'a doubled final slash, HEAD', method='HEAD')
    expect_hop('/about-us//', 301, '/about-us/', 'a doubled final slash')
    expect_hop('/blog//?x=1', 301, '/blog/?x=1', 'a doubled final slash; the query string goes along')
    a_size = next((p for p in listed if re.match(r'/(inner-diameter|length)/[^/]+/$', p)), None)
    if a_size is None:
        problems.append('the sitemap lists no size, so an empty segment inside one went unasked')
    else:
        inside = a_size.replace('/', '//', 2).replace('//', '/', 1)      # /length//50m/
        expect_hop(inside, 301, a_size, 'an empty segment in the middle')
    for path in ('/shop//', '/about-us//'):
        got, to = hop(path, 'POST')
        if got in (301, 302, 307, 308):
            problems.append(f'POST {path}: redirected to {to}; only GET and HEAD may be')
    # the basket and the checkout are exempt with one slash missing, and with
    # one too many: noindex pages a visitor is in the middle of
    for path in ('/cart//', '/checkout//'):
        expect_hop(path, 200, why='exempt from the slash rule; a doubled slash must not add a hop either')

    # not a page with the slash, so not a redirect without it
    for path in NEVER_PAGES:
        expect_hop(path, 404, why='not a page; a missing slash must not turn a 404 into a redirect')
        expect_hop(path + '/', 404, why='not a page')
        expect_hop(path + '//', 404, why='not a page; a doubled slash must not turn a 404 into a redirect')

    # an address with a redirect of its own keeps to one hop without its slash
    own_reason = {**LEGACY_CATEGORIES, **OTHER_LEGACY, **FACTS['redirects'], **FACTS['lone_sizes']}
    for path, lands in own_reason.items():
        if path.rstrip('/'):
            expect_hop(path.rstrip('/'), 301, lands, 'a redirect of its own, asked for without the slash: one hop')
    if not FACTS['lone_sizes'] or not FACTS['redirects']:
        problems.append('small_facts.php names no lone size or no stored redirect: one-hop is not being asked of them')

    # and what works without a slash on purpose answers as it always did
    for path, was in SLASH_EXEMPT.items():
        status, location = was if isinstance(was, tuple) else (was, '')
        expect_hop(path, status, location, 'answers without a slash on purpose; it must not gain a hop')
        counted['exempt'] += 1

    # ---- 13: a search is not a page of the shop
    code, shop_robots, shop_canonical, shop_page, _ = head_of('/shop/')
    if code != 200 or not (shop_robots or '').startswith('index, follow') or shop_canonical != [SITE + '/shop/']:
        problems.append(f'/shop/: {code}, robots {shop_robots!r}, canonical {shop_canonical}; '
                        'the shop itself must stay indexable and canonical to itself')
    a_category = FACTS['categories'][0]['slug'] if FACTS['categories'] else ''
    searches = {'/shop/?q=fuel': True, '/shop/?q=zzqqxx-nothing-sells-as-this': False,
                '/shop/?q=fuel&sort=name': True, '/shop/?q=hose&page=2': True,
                '/shop/?q=hose&cat=' + a_category: None, '/shop/?q=%20hose%20&max=5': None}
    for path, finds in searches.items():
        code, robots, canonical, page, _ = head_of(path)
        counted['searches'] += 1
        if code != 200 or robots != 'noindex, follow':
            problems.append(f'{path}: {code}, robots {robots!r}; a search must be "noindex, follow"')
        if canonical:
            problems.append(f'{path}: prints canonical {canonical} beside its noindex; a search names none')
        empty = 'Nothing matched that search' in (page or '')
        if finds is not None and empty == finds:
            problems.append(f"{path}: {'found nothing' if empty else 'found something'}; this check no longer "
                            'asks for one search with results and one without')
    for path in ('/shop/?sort=name', '/shop/?sort=price-asc', '/shop/?cat=' + a_category, '/shop/?max=5',
                 '/shop/?q=', '/shop/?q=%20%20'):
        code, robots, canonical, page, _ = head_of(path)
        if code != 200 or robots != shop_robots or canonical != [SITE + '/shop/']:
            problems.append(f'{path}: {code}, robots {robots!r}, canonical {canonical}; with no search it is '
                            f'the shop, reordered or narrowed: robots {shop_robots!r}, canonical {SITE}/shop/')
    # A q that is a list (?q[]=a) is not a search and must not be a crash:
    # the header's search box printed it through e() and every page with a
    # header died on it. It is read as not sent, so these are the plain pages.
    for path in ('/shop/?q[]=a', '/shop/?q[x]=a&sort[]=b&cat[]=c&max[]=1', '/?q[]=a', '/about-us/?q[]=a'):
        code, robots, canonical, page, _ = head_of(path)
        if code != 200 or '</html>' not in page or re.search(r'Fatal error|TypeError|Warning:', page):
            problems.append(f'{path}: {code}, and the page is not whole; a q that is a list must be read as not sent')
        elif not (robots or '').startswith('index, follow'):
            problems.append(f'{path}: robots {robots!r}; a q that is a list is no search, so this is the plain page')
    with open(os.path.join(ROOT, 'robots.txt'), encoding='utf-8', errors='replace') as f:
        for rule in re.findall(r'(?im)^\s*Disallow:\s*(\S+)', f.read()):
            shape = '^' + '.*'.join(re.escape(piece) for piece in rule.rstrip('$').split('*')) + ('$' if rule.endswith('$') else '')
            if re.match(shape, '/shop/?q=fuel'):
                problems.append(f'robots.txt: "Disallow: {rule}" stops Google fetching a search, so it never '
                                'reads the noindex and may list the address anyway')
    # one letter: answered before the rate counter, so a run costs it nothing
    code, _, _, body, sent = head_of('/search.php?q=f')
    if code != 200 or 'noindex' not in (sent.get('X-Robots-Tag') or '') or not (body or '').lstrip().startswith('{'):
        problems.append(f"/search.php: {code}, X-Robots-Tag {sent.get('X-Robots-Tag')!r}; the answer to the "
                        'header search box is JSON and must stay out of the index')

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
print(f"  {counted['suffixes']} price notes say {FACTS['price_suffix']!r}; {counted['cards']} specification cards "
      f"and {counted['described']} pictures in descriptions are named; {counted['carts']} cart links name their count")
print(f"  {counted['slashless']} addresses without their final slash take one 301 to it, and "
      f"{counted['doubled']} with a slash too many; {counted['exempt']} that "
      f"work without one answer as before; {counted['searches']} searches are noindex with no canonical")
print(f"  {counted['organisations']} Organization blocks state "
      f"{', '.join(sorted(wanted_registration)) or 'no register number'}, as Settings -> General does")
if MISSPELT.search(FACTS['price_suffix_stored']):
    print(f"  NOTE: Settings still holds the note as {FACTS['price_suffix_stored']!r}; it is corrected as it is "
          'read (price_note_wording). Saving Settings -> Tax once stores the corrected spelling.')

if problems:
    print(f'\n{len(problems)} problem(s)')
    for p in problems[:80]:
        try:
            print('  ' + p)
        except UnicodeEncodeError:
            print('  ' + p.encode('ascii', 'replace').decode())
    sys.exit(1)

print('share pictures, social links, the strip, the sitemap, the chat picture, every address '
      'with and without its slash, searches, the company numbers and the words the pages print hold')
