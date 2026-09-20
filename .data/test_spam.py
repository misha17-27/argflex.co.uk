"""Drive the real contact form: the casino message and a real enquiry.

Posts through the running site the way a browser does — real form token, real
handler — then reads the stored file back to see where each one landed. The
submissions file is snapshotted and restored, so a shop's own enquiries are
never disturbed.
"""
import os, re, sys, json, shutil, urllib.request, urllib.parse, urllib.error, http.cookiejar

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

BASE  = 'http://localhost:8124'
ROOT  = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
STORE = os.path.join(ROOT, 'storage', 'submissions.json')
UA    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126 Safari/537.36'

op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
op.addheaders = [('User-Agent', UA)]

# Two posts from one address in a second is exactly what the enquiry rate
# limiter exists to stop, and a second run would trip on the first one's
# counter. Local-only, as every file in here is.
try:
    os.remove(os.path.join(ROOT, 'storage', 'rate-limits.json'))
except OSError:
    pass

ok = fail = 0


def check(label, cond, note=''):
    global ok, fail
    if cond:
        ok += 1
        print('  %-52s OK  %s' % (label, note))
    else:
        fail += 1
        print('  %-52s FAIL %s' % (label, note))


def get(path):
    with op.open(BASE + path, timeout=40) as r:
        return r.read().decode('utf-8', 'replace')


def post(path, fields):
    data = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(BASE + path, data=data)
    try:
        with op.open(req, timeout=40) as r:
            return r.status, r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, ''


def token():
    html = get('/contacts/')
    m = re.search(r'name="_form" value="([a-f0-9]{64})"', html)
    return m.group(1) if m else ''


def rows():
    if not os.path.isfile(STORE):
        return []
    return json.load(open(STORE, encoding='utf-8'))


SPAM = """Selecting a safe British online casino platform means far more than checking the new-player promotion.
Prior to opening an account, verify the licensed operator. The brand [url=https://example.invalid]https://example.invalid[/url] does not always equal its licensed company.
BetMGM Casino is linked to LeoVegas Gaming PLC. MrQ is listed under Tek Fox Ltd. bet365 Casino is associated with Hillside (UK Gaming) ENC.
Read the bonus requirement, included titles, deadline rules, maximum bet caps and withdrawal limits.
Since 19 January 2026, UK Gambling Commission standards limit incentive wagering requirements at 10x the bonus value.
British casino brands cannot process credit-card payments. Use strictly funds you can risk losing. Treat real-money gaming as leisure, not a financial plan.
More at https://example.invalid/slots — poker, betting and jackpot offers every day of the week for every kind of player who enjoys a wager."""

REAL = 'Do you have 25 m of 16 mm fuel hose in stock, and what would delivery to Romford cost? Thanks.'

backup = STORE + '.bak'
had = os.path.isfile(STORE)
if had:
    shutil.copy2(STORE, backup)
before = len(rows())

try:
    print('\nTHE CASINO MESSAGE')
    t = token()
    check('the form gives out a token', len(t) == 64)
    status, url = post('/contact-send/', {
        'name': 'Ukazjep', 'email': 'zaplon88@absroma.it', 'phone': '+447700900152',
        'message': SPAM, 'website': '', '_form': t})
    check('the sender is told it was sent', 'sent=1' in url, url.replace(BASE, ''))

    got = rows()
    check('it was stored, not dropped', len(got) == before + 1, '%d rows' % len(got))
    junk = [r for r in got if r.get('name') == 'Ukazjep']
    check('and filed as spam', bool(junk) and junk[0].get('spam') is True)
    check('with the reason recorded', bool(junk) and junk[0].get('spam_why'),
          junk[0].get('spam_why', '') if junk else '')
    check('and read, so it is not in the badge', bool(junk) and junk[0].get('is_read') is True)

    print('\nA REAL ENQUIRY THROUGH THE SAME FORM')
    t = token()
    status, url = post('/contact-send/', {
        'name': 'John Smith', 'email': 'j.smith@example.com', 'phone': '07812 345678',
        'message': REAL, 'website': '', '_form': t})
    check('the customer is told it was sent', 'sent=1' in url, url.replace(BASE, ''))
    got = rows()
    mine = [r for r in got if r.get('name') == 'John Smith']
    check('stored', bool(mine))
    check('NOT marked as spam', bool(mine) and not mine[0].get('spam'))
    check('and left unread for someone to answer', bool(mine) and not mine[0].get('is_read'))

    print('\nWHAT THE BADGE COUNTS')
    unread = len([r for r in got if not r.get('is_read') and not r.get('spam')])
    check('one thing to answer, not two', unread == 1, '%d unread' % unread)

finally:
    if had:
        shutil.move(backup, STORE)
    elif os.path.isfile(STORE):
        os.remove(STORE)
    print('\n  %-52s OK  %d rows' % ('the enquiry file is back as it was', len(rows())))

print('\n%s' % ('all checks passed' if not fail else '%d FAILED' % fail))
sys.exit(1 if fail else 0)
