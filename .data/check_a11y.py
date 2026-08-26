"""Check every page for the accessibility faults a machine can see.

    python .data/check_a11y.py            the local server
    python .data/check_a11y.py --verbose  every instance, not just the first few

A machine cannot tell you whether alt text is any good, or whether a page
makes sense read aloud. It can tell you that an image has no alt at all, that
a form field has no label, that a button has no name, that the headings skip
a level, or that two elements share an id — and those are the faults that
actually stop somebody using a keyboard or a screen reader.

Colour contrast and focus rings need a browser to compute, so they are not
here; they are checked separately against the rendered page.
"""
import re, os, sys, json, subprocess, urllib.request, urllib.error, collections, html as H

BASE    = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
ROOT    = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
VERBOSE = '--verbose' in sys.argv

problems = collections.defaultdict(list)


def fetch(url):
    try:
        with urllib.request.urlopen(BASE + url, timeout=30) as r:
            return r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.read().decode('utf-8', 'replace')
    except Exception:
        return ''


def note(kind, url, detail):
    problems[kind].append(f'{url}  {detail}')


def text_of(fragment):
    """The words a screen reader would actually say.

    An image's alt text counts: a link wrapped round a pictured product is
    named by that picture, and treating it as nameless would bury the real
    faults under hundreds of false ones.
    """
    fragment = re.sub(r'<img\b[^>]*\balt="([^"]*)"[^>]*>', r' \1 ', fragment)
    fragment = re.sub(r'<svg\b[^>]*>.*?</svg>', ' ', fragment, flags=re.S)
    fragment = re.sub(r'<[^>]+>', ' ', fragment)
    return re.sub(r'\s+', ' ', H.unescape(fragment)).strip()


def attr(tag, name):
    m = re.search(r'\b' + name + r'="([^"]*)"', tag)
    return m.group(1) if m else None


# ------------------------------------------------------------------- checks

def check_page(url, html):
    if not html or '<html' not in html:
        return

    # A tag that never closed. This is not strictly an accessibility fault,
    # but it is the fault that hides them: the skip link went in after a
    # regex matched <body[^>]*> and stopped at the ?> of the PHP inside it,
    # so every page shipped a torn <body> that the checks still called a
    # pass, because the browser quietly put it back together.
    bare = re.sub(r'<(script|style).*?</>', '', html, flags=re.S | re.I)
    for m in re.finditer(r'<[a-zA-Z][a-zA-Z0-9]*[^<>]*<', bare):
        note('tag that never closed', url, m.group(0).replace(chr(10), ' ')[:70])
        break

    # the document itself
    if not re.search(r'<html[^>]+lang="[a-z]', html, re.I):
        note('no language on the document', url, '<html> has no lang')
    if '<main' not in html:
        note('no main landmark', url, 'nothing marked as the main content')

    # images
    for tag in re.findall(r'<img\b[^>]*>', html):
        if attr(tag, 'alt') is None:
            note('image with no alt', url, (attr(tag, 'src') or tag)[:70])

    # every control needs a name a screen reader can say
    for tag in re.findall(r'<a\b[^>]*>.*?</a>', html, re.S):
        opening = re.match(r'<a\b[^>]*>', tag).group(0)
        said = text_of(tag) or attr(opening, 'aria-label') or attr(opening, 'title') or ''
        if said == '':
            note('link with no name', url, (attr(opening, 'href') or '')[:70])
        elif said.lower() in ('click here', 'here', 'read more', 'more', 'link'):
            note('link named only by a filler word', url, f'"{said}" -> {attr(opening, "href")}')

    for tag in re.findall(r'<button\b[^>]*>.*?</button>', html, re.S):
        opening = re.match(r'<button\b[^>]*>', tag).group(0)
        said = text_of(tag) or attr(opening, 'aria-label') or attr(opening, 'title') or ''
        if said == '':
            note('button with no name', url, opening[:80])

    # form fields need a label, one way or another
    ids = re.findall(r'\bid="([^"]+)"', html)
    labelled = set(re.findall(r'<label\b[^>]*\bfor="([^"]+)"', html))

    # A field wrapped in its own label is named by it and needs no "for".
    # Reporting those was wrong: the payment radios are written that way and
    # they read correctly — the checker simply was not looking for it.
    wrapped = set()
    for block in re.findall(r'<label\b[^>]*>.*?</label>', html, re.S):
        for field in re.findall(r'<(?:input|select|textarea)\b[^>]*>', block):
            wrapped.add(field)

    for tag in re.findall(r'<(?:input|select|textarea)\b[^>]*>', html):
        kind = (attr(tag, 'type') or 'text').lower()
        if kind in ('hidden', 'submit', 'button', 'image'):
            continue
        named = (attr(tag, 'aria-label') or attr(tag, 'aria-labelledby')
                 or (attr(tag, 'id') in labelled) or tag in wrapped)
        if not named:
            note('field with no label', url,
                 f'{kind} name={attr(tag, "name")} id={attr(tag, "id")}')

    # ids have to be unique or a label can point at the wrong thing
    for dupe, count in collections.Counter(ids).items():
        if count > 1:
            note('id used more than once', url, f'{dupe} × {count}')

    # heading order
    levels = [int(m) for m in re.findall(r'<h([1-6])\b', html)]
    if levels.count(1) != 1:
        note('not exactly one h1', url, f'{levels.count(1)} of them')
    last = 0
    for level in levels:
        if last and level > last + 1:
            note('heading level skipped', url, f'h{last} then h{level}')
            break
        last = level

    # a keyboard user should be able to jump past the navigation
    if not re.search(r'<a[^>]+href="#(content|main)"', html):
        note('no skip link', url, 'no way past the navigation with a keyboard')

    # tables that carry data need headers
    for table in re.findall(r'<table\b[^>]*>.*?</table>', html, re.S):
        if '<th' not in table and len(re.findall(r'<tr', table)) > 1:
            note('table with no header cells', url, text_of(table)[:60])

    # something that behaves like a control but is not one
    for tag in re.findall(r'<div\b[^>]*onclick=[^>]*>', html):
        if 'role=' not in tag and 'tabindex=' not in tag:
            note('clickable div that a keyboard cannot reach', url, tag[:70])


# --------------------------------------------------------------------- run

report = os.path.join(ROOT, '.data', 'crawl-report.json')
if os.path.exists(report):
    urls = [r['url'] for r in json.load(open(report, encoding='utf-8'))['results']]
else:
    urls = ['/', '/shop/', '/product/acetylene-hose/', '/blog/', '/contacts/',
            '/cart/', '/checkout/', '/my-account/', '/about-us/']

urls = [u for u in urls if not u.endswith(('.xml', '.txt')) and '.php' not in u]

print(f'reading {len(urls)} pages')
for url in urls:
    check_page(url, fetch(url))
    sys.stdout.write('.')
    sys.stdout.flush()
print('\n')

if not problems:
    print('Nothing a machine can see. Contrast and focus still need a browser.')
    sys.exit(0)

total = sum(len(v) for v in problems.values())
for kind, hits in sorted(problems.items(), key=lambda kv: -len(kv[1])):
    print(f'{len(hits):4}  {kind}')
    for hit in (hits if VERBOSE else hits[:3]):
        print(f'        {hit}')
    if not VERBOSE and len(hits) > 3:
        print(f'        … and {len(hits) - 3} more')

print(f'\n{total} in {len(problems)} kind(s). Pass --verbose for every one.')
sys.exit(1)
