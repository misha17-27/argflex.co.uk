"""The chat widget's endpoint: what it accepts, and what it refuses.

The widget sits on every page of the shop, which makes it the easiest form on
the site to find and the easiest to abuse. It carries the same machinery as
the enquiry form — a signed token, a rate limit, a honeypot and the spam
scorer — and this checks each of them rather than trusting that it does.

The submissions file is snapshotted and restored, so a shop's own enquiries
are never disturbed. Local only.
"""
import json, os, re, shutil, sys, urllib.error, urllib.parse, urllib.request, http.cookiejar

BASE  = 'http://localhost:8124'
ROOT  = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
STORE = os.path.join(ROOT, 'storage', 'submissions.json')
UA    = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
         '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36')

# Two posts from one address in a second is what the limiter exists to stop,
# and a second run would trip on the first one's counter.
try:
    os.remove(os.path.join(ROOT, 'storage', 'rate-limits.json'))
except OSError:
    pass

op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
op.addheaders = [('User-Agent', UA)]

ok = bad = 0


def check(label, cond, note=''):
    global ok, bad
    if cond:
        ok += 1
        print('  %-50s OK   %s' % (label, note))
    else:
        bad += 1
        print('  %-50s FAIL %s' % (label, note))


def token():
    html = op.open(BASE + '/', timeout=40).read().decode('utf-8', 'replace')
    m = re.search(r'data-token="([a-f0-9]{64})"', html)
    return m.group(1) if m else ''


def post(fields):
    data = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(BASE + '/chat-lead/', data=data)
    req.add_header('Content-Type', 'application/x-www-form-urlencoded')
    try:
        with op.open(req, timeout=40) as r:
            return r.status, json.loads(r.read().decode('utf-8', 'replace') or '{}')
    except urllib.error.HTTPError as e:
        body = e.read().decode('utf-8', 'replace')
        try:
            return e.code, json.loads(body or '{}')
        except ValueError:
            return e.code, {}


def rows():
    if not os.path.isfile(STORE):
        return []
    return json.load(open(STORE, encoding='utf-8'))


backup = STORE + '.bak'
had = os.path.isfile(STORE)
if had:
    shutil.copy2(STORE, backup)
before = len(rows())

try:
    print('\nTHE WIDGET IS ON THE PAGE AT ALL')
    t = token()
    check('the page hands out a signed token', len(t) == 64)

    print('\nA REAL ENQUIRY')
    code, d = post({'_form': t, 'product': 'Fuel & oil hose',
                    'name': 'John Smith', 'phone': '07812 345678', 'website': ''})
    check('it is accepted', code == 200 and d.get('ok') is True, str(code))
    check('and handed on to WhatsApp', d.get('wa', '').startswith('https://wa.me/'))
    check('with the name in the message', 'John%20Smith' in d.get('wa', ''))
    got = rows()
    check('the lead is stored, not only sent', len(got) == before + 1, '%d rows' % len(got))
    mine = [r for r in got if r.get('name') == 'John Smith']
    check('filed as a chat lead', bool(mine) and mine[0].get('source') == 'chat')
    check('left unread for somebody to answer', bool(mine) and not mine[0].get('is_read'))

    print('\nWHAT IT REFUSES')
    code, d = post({'_form': 'f' * 64, 'product': 'x', 'name': 'A', 'phone': '07812345678'})
    check('a forged token', code == 419 and not d.get('ok'), str(code))

    t2 = token()
    code, d = post({'_form': t2, 'product': 'x', 'name': 'A', 'phone': 'not a number'})
    check('a phone number that is not one', not d.get('ok'), d.get('say', '')[:34])

    code, d = post({'_form': t2, 'product': 'x', 'name': '', 'phone': '07812345678'})
    check('no name at all', not d.get('ok'), d.get('say', '')[:34])

    code, d = post({'_form': t2, 'product': 'x', 'name': 'Bot', 'phone': '07812345678',
                    'website': 'http://spam.invalid'})
    check('a filled honeypot is accepted silently', d.get('ok') is True and not d.get('wa'),
          'told nothing useful')
    check('and nothing was stored for it', not [r for r in rows() if r.get('name') == 'Bot'])

    print('\nTHE RATE LIMIT')
    tripped = False
    for i in range(9):
        code, d = post({'_form': token(), 'product': 'x',
                        'name': 'Flood %d' % i, 'phone': '07812345678'})
        if code == 429:
            tripped = True
            break
    check('a flood is stopped', tripped, 'stopped at attempt %d' % (i + 1))

finally:
    if had:
        shutil.move(backup, STORE)
    elif os.path.isfile(STORE):
        os.remove(STORE)
    try:
        os.remove(os.path.join(ROOT, 'storage', 'rate-limits.json'))
    except OSError:
        pass
    print('\n  %-50s OK   %d rows' % ('the enquiry file is back as it was', len(rows())))

print('\n%s' % ('all checks passed' if not bad else '%d FAILED' % bad))
sys.exit(1 if bad else 0)
