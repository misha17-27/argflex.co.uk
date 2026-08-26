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
    """Every archive URL the catalogue implies."""
    code = ('require "inc/config.php"; '
            'foreach (all_attributes() as $a) foreach ($a["terms"] as $t) '
            'echo attribute_term_url($a["slug"], $t["slug"]), "\\n";')
    out = subprocess.run([PHP, '-r', code], cwd=ROOT, capture_output=True, text=True)
    return [u.strip() for u in out.stdout.splitlines() if u.strip()]


def fetch(base, path):
    req = urllib.request.Request(base + path, headers={'User-Agent': 'argflex-parity-check'})
    try:
        with urllib.request.urlopen(req, timeout=40) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
    except Exception as e:
        return 0, str(e)


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


paths = urls()
print(f'{len(paths)} archive URL(s) from the catalogue\n')

problems = []
for path in paths:
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
