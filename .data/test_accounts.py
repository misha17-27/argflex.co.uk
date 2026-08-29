"""Customer accounts: registering, signing in, orders, details, reset."""
import re, os, json, sys, urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = 'http://localhost:8124'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
ORD  = os.path.join(ROOT, 'storage/orders')
ACC  = os.path.join(ROOT, 'storage/customers.php')

jar = http.cookiejar.CookieJar()
op  = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
# a second browser, to prove one person's session cannot see another's
jar2 = http.cookiejar.CookieJar()
op2  = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar2))

EMAIL = 'rita@example.com'
PW    = 'a-long-enough-password'


# Every public form now carries a token signed for its own action, so this
# suite has to carry one too — see .data/formtoken.py. Scraped from the real
# page, never computed here: a test that signed its own tokens would keep
# passing if the server stopped checking them. One per browser, because the
# token is bound to that browser's own cookie.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from formtoken import Tokens
TOK  = Tokens(op, BASE)
TOK2 = Tokens(op2, BASE)

# The suites hammer the same forms from one address, so they trip the counters
# the shop uses against brute force and spam. Clearing them at the start keeps
# a run from failing on its own previous run — never do this on a live server,
# which is why every one of these files says local-only at the top.
try:
    os.remove(os.path.join(ROOT, 'storage', 'rate-limits.json'))
except OSError:
    pass


def call(opener, url, fields=None):
    if fields is not None and '_form' not in fields and not url.startswith('/admin'):
        fields = {**fields, '_form': (TOK if opener is op else TOK2).sign(url, fields)}
    try:
        if fields is None:
            with opener.open(BASE + url, timeout=40) as r:
                return r.status, r.read().decode('utf-8', 'replace'), r.geturl()
        data = urllib.parse.urlencode(fields, doseq=True, encoding='utf-8').encode()
        with opener.open(urllib.request.Request(BASE + url, data=data, method='POST'), timeout=60) as r:
            return r.status, r.read().decode('utf-8', 'replace'), r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), ''


def get(u):        return call(op, u)
def post(u, f):    return call(op, u, f)


FAILS = []
def check(label, ok, extra=''):
    if not ok: FAILS.append(label)
    print(f'  {label:54} {"OK" if ok else "FAILED"}{("  " + str(extra)) if extra else ""}')


for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))
if os.path.exists(ACC): os.remove(ACC)

print('AN ANONYMOUS VISITOR')
code, page, _ = get('/my-account/')
check('the page offers signing in and registering', code == 200
      and 'value="login"' in page and 'value="register"' in page)
check('and is kept out of the index', 'noindex' in page)
# A session cookie is still not handed out for browsing — that is the point of
# the check. The form cookie IS, and has to be: it is the visitor's half of the
# token every form carries, it holds nothing about them, and a form cannot set
# it itself because by then the headers have gone.
ALLOWED = {'argflex_form'}
check('no session cookie is handed out just for looking',
      not any(c.name == 'argflex_customer' for c in jar), str([c.name for c in jar]))
check('and nothing beyond the form token',
      not ({c.name for c in jar} - ALLOWED), str([c.name for c in jar]))
check('other pages hand out none either',
      (get('/shop/'), not any(c.name == 'argflex_customer' for c in jar))[1])

print('\nREGISTERING')
_, page, url = post('/my-account/', {'act': 'register', 'name': 'Rita Cheng',
                                     'email': EMAIL, 'password': 'short'})
check('a short password is refused', 'at least ten characters' in page)
check('nothing was written', not os.path.exists(ACC))

_, page, url = post('/my-account/', {'act': 'register', 'name': 'Rita Cheng',
                                     'email': 'not-an-email', 'password': PW})
check('a bad address is refused', 'does not look right' in page)

_, page, url = post('/my-account/', {'act': 'register', 'name': 'Rita Cheng',
                                     'email': EMAIL, 'password': PW})
check('a good one works', 'done=registered' in url, url[-24:])
stored = open(ACC, encoding='utf-8').read()
check('the account is stored', "'email' => 'rita@example.com'" in stored)
check('the password is hashed, not kept', PW not in stored and "'password' => '$2y$" in stored)
check('and they are signed straight in', 'Sign out' in get('/my-account/')[1])

# Signed out first: registering is only offered to somebody who is not
# already signed in, so the form - and its token - is only on that page.
post('/my-account/', {'act': 'logout'})
_, page, _ = post('/my-account/', {'act': 'register', 'name': 'Someone else',
                                   'email': EMAIL.upper(), 'password': PW})
check('the same address twice is refused', 'already an account' in page)
post('/my-account/', {'act': 'login', 'email': EMAIL, 'password': PW})

print('\nSIGNING IN AND OUT')
# Signing out posts a token now rather than being a GET link - a link that
# signs you out is something any other page can put in an <img>.
check('signing out is not a bare link', 'my-account/?logout=1' not in get('/my-account/')[1])
post('/my-account/', {'act': 'logout'})
check('signing out works', 'value="login"' in get('/my-account/')[1])
_, page, _ = post('/my-account/', {'act': 'login', 'email': EMAIL, 'password': 'wrong-password'})
check('a wrong password is refused', 'not recognised' in page)
_, page, url = post('/my-account/', {'act': 'login', 'email': EMAIL, 'password': PW})
check('the right one is accepted', 'done=welcome' in url, url[-22:])
_, page, _ = get('/my-account/')
check('their name is shown', 'Rita Cheng' in page)

print('\nTHEIR ORDERS, AND ONLY THEIRS')
def place(opener, email, name, qty):
    body = call(opener, '/checkout/', {'cart': json.dumps([{"slug": "submersible-fuel-hose-sae-j30-r10",
                                                            "option": "", "qty": qty}]),
                                       'name': name, 'company': '', 'email': email,
                                       'phone': '07000 000111', 'address': '1 Test Road',
                                       'city': 'London', 'postcode': 'E18 1AN', 'country': 'GB',
                                       'notes': '', 'payment': 'proforma', 'website': ''})[1]
    m = re.search(r'([0-9]{6}-[0-9A-F]{6})', body)
    return m.group(1) if m else None

mine  = place(op,  EMAIL, 'Rita Cheng', 3)
other = place(op2, 'omar@example.com', 'Omar Silva', 2)
check('both orders placed', mine and other, f'{mine}, {other}')
_, page, _ = get('/my-account/orders/')
check('my order is listed', mine in page)
check('somebody else\'s is not', other not in page)
check('with its status', 'acc-status' in page)
_, one, _ = get('/my-account/orders/%s/' % mine)
check('and opens on its own page', 'Delivered to' in one and mine in one)
_, theirs, _ = get('/my-account/orders/%s/' % other)
check('another customer\'s reference opens nothing', 'Delivered to' not in theirs)
_, dash, _ = get('/my-account/')
check('the dashboard offers the sections', 'acc-tile' in dash and '/my-account/addresses/' in dash)

print('\nTHE CHECKOUT FILLS ITSELF IN')
post('/my-account/', {'act': 'details', 'name': 'Rita Cheng',
                      'company': 'Cheng Plant Hire', 'phone': '0113 496 0011'})
post('/my-account/', {'act': 'address', 'address': '12 Bridge Works', 'city': 'Leeds',
                      'postcode': 'LS1 4AB', 'country': 'GB'})
stored = open(ACC, encoding='utf-8').read()
check('the details save', "'Cheng Plant Hire'" in stored and "'LS1 4AB'" in stored)
_, co, _ = get('/checkout/')
check('and the checkout is prefilled', 'value="Cheng Plant Hire"' in co
      and 'value="12 Bridge Works"' in co and 'value="LS1 4AB"' in co)
_, co2, _ = call(op2, '/checkout/')
check('but not for anybody else', 'Cheng Plant Hire' not in co2)

print('\nCHANGING THE PASSWORD')
_, page, _ = post('/my-account/', {'act': 'password', 'current': 'wrong', 'password': 'another-long-one'})
check('the current password is required', 'not your current password' in page)
_, page, _ = post('/my-account/', {'act': 'password', 'current': PW, 'password': 'short'})
check('a short new one is refused', 'at least ten characters' in page)
_, page, url = post('/my-account/', {'act': 'password', 'current': PW,
                                     'password': 'a-brand-new-password',
                                     'confirm': 'a-brand-new-password'})
check('a good one is accepted', 'done=password' in url, url[-22:])
post('/my-account/', {'act': 'logout'})
_, _, url = post('/my-account/', {'act': 'login', 'email': EMAIL, 'password': 'a-brand-new-password'})
check('the new password signs them in', 'done=welcome' in url)
post('/my-account/', {'act': 'logout'})

print('\nFORGOTTEN PASSWORD')
_, page, _ = get('/my-account/?do=forgot')
check('there is a way to ask', 'value="forgot"' in page)
_, _, url = post('/my-account/', {'act': 'forgot', 'email': 'nobody@example.com'})
check('an unknown address gets the same answer', 'done=reset-sent' in url, url[-24:])
_, _, url = post('/my-account/', {'act': 'forgot', 'email': EMAIL})
check('so does a real one', 'done=reset-sent' in url)
stored = open(ACC, encoding='utf-8').read()
check('a token was stored, hashed', "'reset' => '$2y$" in stored)

_, page, _ = post('/my-account/', {'act': 'reset', 'email': EMAIL,
                                   'token': 'a' * 40, 'password': 'yet-another-password'})
check('a made-up token is refused', 'expired' in page)

# read the real token the way the email would carry it
import subprocess
token = subprocess.run([os.environ.get('ARGFLEX_PHP', os.path.join('D:', os.sep, 'argflex', 'php', 'php.exe')),
                        '-r', 'require "inc/config.php"; require "inc/store.php"; '
                              'echo start_password_reset("' + EMAIL + '");'],
                       cwd=ROOT, capture_output=True, text=True).stdout.strip()
check('a fresh token can be issued', len(token) == 40, str(len(token)))
_, page, url = post('/my-account/', {'act': 'reset', 'email': EMAIL, 'token': token,
                                     'password': 'the-final-password'})
check('the real token works', 'done=password' in url, url[-22:])
check('and signs them in', 'Sign out' in get('/my-account/')[1])
stored = open(ACC, encoding='utf-8').read()
check('the token is spent', "'reset' => ''" in stored)
# Signed out first: a successful reset signs you in, and the reset form is
# only shown to somebody who is not.
post('/my-account/', {'act': 'logout'})
_, page, _ = post('/my-account/', {'act': 'reset', 'email': EMAIL, 'token': token,
                                   'password': 'trying-again-now'})
check('and cannot be used twice', 'expired' in page)

print('\nWHAT A CUSTOMER CANNOT DO')
_, page, _ = post('/my-account/', {'act': 'details', 'name': 'Rita', 'company': '', 'phone': '',
                                   'address': '', 'city': '', 'postcode': '', 'country': 'GB',
                                   'password': 'injected', 'email': 'someone@else.com'})
stored = open(ACC, encoding='utf-8').read()
check('they cannot set a password through the details form', 'injected' not in stored)
check('nor invent another account', "'someone@else.com'" not in stored)
code, _, _ = call(op, '/admin/')
check('a customer session is not an admin one', code in (200, 302)
      and 'admin/logout' not in call(op, '/admin/')[1])

print('\nTIDY UP')
for f in os.listdir(ORD):
    if f.endswith('.json'): os.remove(os.path.join(ORD, f))
if os.path.exists(ACC): os.remove(ACC)
check('accounts and orders removed', not os.path.exists(ACC))
check('the page still works with none', get('/my-account/')[0] == 200)

print()
print(f'{len(FAILS)} FAILED: ' + ', '.join(FAILS) if FAILS else 'all checks passed')
sys.exit(1 if FAILS else 0)
