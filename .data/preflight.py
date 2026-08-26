"""Run every check in one go, before putting a change live.

    python .data/preflight.py           read-only checks
    python .data/preflight.py --full    also runs the suites that place test
                                        orders and save settings (they clean
                                        up after themselves, so keep this for
                                        a local copy, never a live server)

The local server must be running. The admin walk and the suites also need a
login, which is read from the environment and never stored here:

    ARGFLEX_ADMIN_EMAIL=you@example.com ARGFLEX_ADMIN_PASSWORD=... \\
        python .data/preflight.py --full
"""
import os, sys, glob, shutil, subprocess, urllib.request, urllib.error

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
BASE = os.environ.get('ARGFLEX_BASE', 'http://localhost:8124')
FULL = '--full' in sys.argv
PHP  = os.environ.get('ARGFLEX_PHP') or (
    r'D:\argflex\php\php.exe' if os.path.exists(r'D:\argflex\php\php.exe') else shutil.which('php'))

os.chdir(ROOT)
results = []


def step(name):
    print(f'\n\033[1m{name}\033[0m' if os.name != 'nt' else f'\n{name}')


def done(name, ok, detail=''):
    results.append((name, ok))
    line = f'  {"PASS" if ok else "FAIL"}  {name}{("  — " + detail) if detail else ""}'
    # A failing test's own output is quoted back here, and on Windows the
    # console is cp1252. One pound sign in a failure message used to abort
    # the whole run — losing every check after it, at the moment they were
    # most worth seeing.
    try:
        print(line)
    except UnicodeEncodeError:
        print(line.encode(sys.stdout.encoding or 'ascii', 'replace')
                  .decode(sys.stdout.encoding or 'ascii'))


def run(script, *args):
    r = subprocess.run([sys.executable, script, *args], capture_output=True, text=True,
                       encoding='utf-8', errors='replace')
    return r.returncode, (r.stdout or '') + (r.stderr or '')


# ---------------------------------------------------------------- the server
step('Local server')
try:
    with urllib.request.urlopen(BASE + '/', timeout=15) as r:
        done('site answers', r.status == 200, f'{BASE} -> HTTP {r.status}')
except Exception as e:
    done('site answers', False, f'{BASE} is not responding — start it with start-server.bat')
    print('\nNothing else can run without it.')
    sys.exit(1)

# ------------------------------------------------------------------ the code
step('The code parses')
if not PHP:
    done('php lint', False, 'no php binary found; set ARGFLEX_PHP')
else:
    bad = []
    files = [f for f in glob.glob('**/*.php', recursive=True) if not f.startswith('.git')]
    for f in files:
        r = subprocess.run([PHP, '-l', f], capture_output=True, text=True)
        if 'No syntax errors' not in (r.stdout or ''):
            bad.append(f)
    done('php lint', not bad, f'{len(files)} files' + (', broken: ' + ', '.join(bad) if bad else ''))

step('The stylesheet is current')
# Hash it either side of a rebuild. git status would also fire on a change
# that is merely staged, which is not what this is asking.
import hashlib


def css_hash():
    with open('assets/css/site.css', 'rb') as f:
        return hashlib.md5(f.read()).hexdigest()


was = css_hash()
subprocess.run([sys.executable, '.data/build_css.py'], capture_output=True, text=True)
done('site.css matches its parts', was == css_hash(),
     'a rebuild changed it — commit the new assets/css/site.css' if was != css_hash() else 'nothing to rebuild')

# ---------------------------------------------------------------- the pages
step('Every page')
code, out = run('.data/crawl.py')
tail = [l for l in out.splitlines() if l.startswith(('pages crawled', 'links checked', 'problems'))]
fails = [l for l in out.splitlines() if l.startswith('  !')]
done('crawler', not fails, '; '.join(t.replace('  ', '') for t in tail))
for f in fails[:10]:
    print('        ' + f.strip())

step('Search metadata')
code, out = run('.data/check_seo.py')
diff = [l for l in out.splitlines() if l.startswith('differences')]
done('titles and descriptions match the live site',
     bool(diff) and diff[0].strip().endswith('0'), diff[0].strip() if diff else out.strip()[-90:])

step('Delivery')
php = os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe'))
proc = subprocess.run([php, '.data/test_shipping.php'], cwd=ROOT, capture_output=True, text=True)
last = (proc.stdout or '').strip().splitlines()[-1] if (proc.stdout or '').strip() else 'no output'
done('carriage matches the live shop', proc.returncode == 0, last[:90])
for l in (proc.stdout or '').splitlines():
    if 'FAILED' in l:
        print('        ' + l.strip())

step('Taking payment')
proc = subprocess.run([PHP, '.data/test_payments.php'], cwd=ROOT, capture_output=True, text=True)
last = (proc.stdout or '').strip().splitlines()[-1] if (proc.stdout or '').strip() else 'no output'
done('the machinery around a gateway', proc.returncode == 0, last[:90])
for l in (proc.stdout or '').splitlines():
    if 'FAILED' in l:
        print('        ' + l.strip())

step('Attribute archives')
# Only a --full run asks the live site; the quick one checks ours alone.
code, out = run('.data/check_attribute_pages.py', *([] if FULL else ['--offline']))
last = out.strip().splitlines()[-1] if out.strip() else 'no output'
done('the 35 indexed size pages still answer', code == 0, last[:90])
for l in out.splitlines():
    if l.startswith('  ') and ('problem' in l or 'live:' in l or 'ours:' in l):
        print('      ' + l.strip())

step('Accessibility')
code, out = run('.data/check_a11y.py')
summary = [l for l in out.splitlines() if l.strip().endswith('kind(s). Pass --verbose for every one.')]
done('what a machine can check', code == 0,
     summary[0].strip() if summary else 'nothing a machine can see')
for l in out.splitlines():
    stripped = l.strip()
    if stripped and stripped[0].isdigit() and 'in ' not in stripped[:6]:
        print('        ' + stripped)

# ---------------------------------------------------------------- the admin
step('The admin')
if not os.environ.get('ARGFLEX_ADMIN_EMAIL'):
    done('every screen renders', True, 'skipped — no ARGFLEX_ADMIN_EMAIL set')
else:
    code, out = run('.data/check_admin.py')
    line = [l for l in out.splitlines() if l.startswith(('screens walked', 'problems'))]
    done('every screen renders', code == 0, '; '.join(l.replace('  ', '') for l in line))
    for l in out.splitlines():
        if l.startswith('  !'):
            print('        ' + l.strip())

# ------------------------------------------------------------- the behaviour
if FULL:
    for name, script in [('products',       '.data/test_products.py'),
                         ('linked products','.data/test_linked.py'),
                         ('reviews',        '.data/test_reviews.py'),
                         ('orders',         '.data/test_orders.py'),
                         ('accounts',       '.data/test_accounts.py'),
                         ('invoices',       '.data/test_invoices.py'),
                         ('discount codes', '.data/test_coupons.py'),
                         ('customers',      '.data/test_customers.py'),
                         ('reports',        '.data/test_reports.py')]:
        step(f'Behaviour: {name}')
        code, out = run(script)
        last = out.strip().splitlines()[-1] if out.strip() else 'no output'
        done(name, 'all checks passed' in out, last[:100])
        for l in out.splitlines():
            if 'FAILED' in l:
                print('        ' + l.strip())
else:
    step('Behaviour')
    done('coupons, customers and reports', True, 'skipped — pass --full to run them')

# ------------------------------------------------------------------ verdict
failed = [n for n, ok in results if not ok]
print('\n' + '-' * 58)
print(f'{len(results) - len(failed)} of {len(results)} checks passed')
if failed:
    print('failed: ' + ', '.join(failed))
    print('\nDo not deploy until these are clear.')
else:
    print('\nReady to deploy. The checklist is in the README, and /admin/status\n'
          'reports what the server itself is missing once it is up there.')
sys.exit(1 if failed else 0)
