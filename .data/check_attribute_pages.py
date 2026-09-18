"""Compare the attribute archives against the live site.

    python .data/check_attribute_pages.py

Thirty-five of these are indexed as /inner-diameter/<size>/ and
/length/<size>/. They used to 404 here, which is the ordinary way a
migration loses its rankings. This asks the live site what each one
answers and holds ours to it: the same status, the same title, the same
canonical, and no meta description, because live sets none.

It reads the live site over the network. Pass --offline to check only that
ours resolve and are internally consistent.
"""
import html
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

BASE = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
LIVE = 'https://argflex.co.uk'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PHP = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))
OFFLINE = '--offline' in sys.argv


def urls():
    """Every archive URL the catalogue implies, and where it should lead.

    Twelve of the thirty-five list a single product and now 301 to it — a page
    describing one item in less detail than the item's own page is worth less
    than the redirect to it. So each row is (path, product URL or ''), and an
    empty second value means the archive is still a page. Asked of the same
    function the routing uses, so the two cannot drift apart.
    """
    code = ('require "inc/config.php"; '
            'foreach (all_attributes() as $a) foreach ($a["terms"] as $t) { '
            '$lone = attribute_term_lone_product($a["slug"], $t["slug"]); '
            'echo attribute_term_url($a["slug"], $t["slug"]), "\\t", '
            '$lone ? product_url($lone) : "", "\\n"; }')
    out = subprocess.run([PHP, '-r', code], cwd=ROOT, capture_output=True, text=True)

    rows = []
    for line in out.stdout.splitlines():
        if not line.strip():
            continue
        path, _, lone = line.partition('\t')
        rows.append((path.strip(), lone.strip()))
    return rows


def fetch(base, path):
    req = urllib.request.Request(base + path, headers={'User-Agent': 'argflex-parity-check'})
    try:
        with urllib.request.urlopen(req, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, str(e)


def fetch_no_follow(base, path):
    """Status and Location, without following — here the redirect IS the answer."""
    class Still(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None

    op  = urllib.request.build_opener(Still)
    req = urllib.request.Request(base + path, headers={'User-Agent': 'argflex-parity-check'})
    try:
        with op.open(req, timeout=40) as r:
            return r.status, ''
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location', '')
    except Exception:
        return 0, ''


def facts(body):
    title = re.search(r'<title>(.*?)</title>', body, re.S)
    desc = re.search(r'<meta name="description" content="(.*?)"', body, re.S)
    canon = re.search(r'<link rel="canonical" href="(.*?)"', body)
    h1 = re.search(r'<h1[^>]*>(.*?)</h1>', body, re.S)
    return {
        'title': html.unescape(title.group(1)).strip() if title else '',
        'description': html.unescape(desc.group(1)).strip() if desc else '',
        'canonical': canon.group(1) if canon else '',
        'h1': re.sub(r'<[^>]+>', '', html.unescape(h1.group(1))).strip() if h1 else '',
    }


rows  = urls()
paths = [p for p, _ in rows]
print(f'{len(rows)} archive URL(s) from the catalogue '
      f'({sum(1 for _, lone in rows if lone)} of them redirect to a single product)\n')

problems = []
for path, lone in rows:

    # An archive listing ONE product redirects to it. The live site still
    # serves the archive, so this is a deliberate divergence from live and the
    # title and canonical checks below would rightly disagree. What is checked
    # instead: the redirect exists, points where the catalogue says it should,
    # and lands on a page rather than another redirect or a 404.
    if lone:
        status, where = fetch_no_follow(BASE, path)
        if status != 301:
            problems.append(f'{path}  answered {status}, expected 301 to {lone}')
            print('  !', path, f'{status}, not 301')
        elif where != lone:
            problems.append(f'{path}  301s to {where!r}, expected {lone!r}')
            print('  !', path, 'redirects to the wrong product')
        else:
            landed, _ = fetch(BASE, where)
            if landed != 200:
                problems.append(f'{path}  301s to {where}, which answers {landed}')
                print('  !', path, f'lands on {landed}')
            else:
                print('  .', path, '-> 301')
        continue

    status, body = fetch(BASE, path)
    mine = facts(body)

    if status != 200:
        problems.append(f'{path}  ours answered {status}')
        print('  !', path, status)
        continue

    # A page can answer 200, carry the right title, and still be broken below
    # the fold. That is exactly what happened: an int passed to a function
    # wanting a string killed every one of these pages after the heading, and
    # this check said they all matched because it only ever read the title.
    fatal = re.search(r'(Fatal error|Parse error|Uncaught \w+)[^<]{0,120}', body)
    if fatal:
        problems.append(f'{path}  PHP {fatal.group(0).strip()[:100]}')
        print('  !', path, 'PHP error')
        continue
    if '</html>' not in body:
        problems.append(f'{path}  the page stops before it closes — something died mid-render')
        print('  !', path, 'truncated')
        continue

    if mine['description']:
        problems.append(f'{path}  ours has a meta description; live sets none')

    want_canonical = LIVE + path
    if mine['canonical'] != want_canonical:
        problems.append(f'{path}  canonical is {mine["canonical"]!r}, expected {want_canonical!r}')

    if OFFLINE:
        if not mine['title'].endswith(' - argflex.co.uk'):
            problems.append(f'{path}  title {mine["title"]!r} is not in the live form')
        if mine['h1'] != mine['title'].replace(' - argflex.co.uk', ''):
            problems.append(f'{path}  h1 {mine["h1"]!r} does not match the title')
        sys.stdout.write('.')
        sys.stdout.flush()
        continue

    live_status, live_body = fetch(LIVE, path)
    theirs = facts(live_body)

    if live_status != 200:
        problems.append(f'{path}  live answered {live_status} — is this URL still real?')
    elif theirs['title'] != mine['title']:
        problems.append(f'{path}\n        live:  {theirs["title"]!r}\n        ours:  {mine["title"]!r}')
    elif theirs['h1'] != mine['h1']:
        problems.append(f'{path}  h1 live {theirs["h1"]!r} vs ours {mine["h1"]!r}')

    sys.stdout.write('.')
    sys.stdout.flush()

print('\n')
if problems:
    print(f'{len(problems)} problem(s):')
    for p in problems:
        print('  ' + p)
    sys.exit(1)

print(f'all {len(paths)} archives match the live site'
      + (' (titles checked offline)' if OFFLINE else ''))
