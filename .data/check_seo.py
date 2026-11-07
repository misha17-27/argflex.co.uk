"""Compare the rebuilt site's metadata against the live site, URL by URL.

TITLES must be identical on any page that earns clicks: they are what those
rankings sit on, so a difference there is a failure.

The exception is RETITLED. On 06.11.2026 the owner lifted the freeze for pages
with no clicks to lose: they sat at position 34-63 under titles such as
"Abrasive Materials for Precision Finishing", on a page that sells sandblast
hose. Those are held to their new title instead. Add a page here only once
Search Console shows it has earned no clicks.

DESCRIPTIONS are deliberately ours, so a difference is expected and counted
rather than failed. A description does not rank a page — it decides whether
the result is clicked — which made it the half that could be rewritten to say
what the hose is for, which standard it meets, the bore range and the price.

What is still checked is that every indexed URL HAS one and that it fits:
a page that quietly ends up with no description is as much a failure as a
wrong title. To see what the live wording was:

    git show <commit>:data/seo.php
"""
import json, os, re, subprocess, html

ROOT  = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
LOCAL = 'http://localhost:8124'
LIVE_DIR = os.path.join(ROOT, '.data', 'live_html')

seo_php = open(os.path.join(ROOT, 'data', 'seo.php'), encoding='utf-8').read()
paths = re.findall(r"^    '(/[^']*)' => \[", seo_php, re.M)

SKIP = {'/cart/', '/checkout/', '/wishlist/', '/compare/', '/my-account/'}

RETITLED = {
    '/product-category/rubber-hoses/': 'Industrial Rubber Hose: Fuel, Oil, Gas, Coolant, Sandblast',
    '/product-category/rubber-hoses/abrasive-materials/': 'Sandblast Hose: Abrasion Resistant Blast Hose by the Metre',
    '/product-category/rubber-hoses/cooling-system/': 'Coolant & Heater Hose SAE J20 R3, +125°C, Cut to Length',
    '/product-category/rubber-hoses/gas/': 'Gas Welding Hose: Oxygen, Acetylene, LPG, Twin Line',
    '/product-category/rubber-hoses/oil-products/': 'Fuel & Oil Resistant Hose: SAE J30 R6, DIN 73379',
    '/product-category/rubber-hoses/water/': 'PVC Garden & Water Hose: HOBBY and NTS Ranges',
    '/product-category/pvcpu-hoses/': 'PVC and PU Hose: Clear Tube, Garden, Air and Ducting',
    '/product-category/pvcpu-hoses/flat-water/': 'PVC Water Hose and Clear PVC Tube',
    '/product-category/pvcpu-hoses/oil-products-pvcpu-hoses/': 'Clear PVC Fuel Tube for Petrol and Diesel, Cut to Length',
    '/product-category/pvcpu-hoses/ventilation/': 'Flexible PVC Ducting Hose: Steel Helix, Flame Resistant',
    '/product-category/hose-couplings/': 'Hose Clamps: ASFA, GBS, MINI, ST ORIGINAL',
    '/product/oxygen-hose-agoma/': 'Oxygen Hose for Gas Welding & Cutting, EN 559 / ISO 3821',
}

def unesc(s):
    return html.unescape(s or '').strip()

def live_meta(path):
    f = os.path.join(LIVE_DIR, (path.strip('/').replace('/', '__') or 'home') + '.html')
    if not os.path.exists(f):
        return None
    src = open(f, encoding='utf-8', errors='replace').read()
    head = src[:src.lower().find('</head>') + 7]
    t = re.search(r'<title[^>]*>(.*?)</title>', head, re.S | re.I)
    d = re.search(r'<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']', head, re.S | re.I)
    return unesc(t.group(1) if t else ''), unesc(d.group(1) if d else '')

def local_meta(path):
    r = subprocess.run(['curl', '-sS', '--max-time', '30', LOCAL + path],
                       capture_output=True, text=True, encoding='utf-8', errors='replace')
    src = r.stdout
    t = re.search(r'<title[^>]*>(.*?)</title>', src, re.S | re.I)
    d = re.search(r'<meta\s+name="description"\s+content="(.*?)">', src, re.S | re.I)
    c = re.search(r'<link\s+rel="canonical"\s+href="(.*?)">', src, re.S | re.I)
    return unesc(t.group(1) if t else ''), unesc(d.group(1) if d else ''), (c.group(1) if c else '')

same_t = same_d = filled_d = retitled_ok = 0
diffs, no_canonical = [], []

# A retitled page dropped from seo.php would otherwise pass without being looked at.
for path in RETITLED:
    if path not in paths:
        diffs.append(f'TITLE {path}\n     in RETITLED but not in data/seo.php')

for path in paths:
    if path in SKIP:
        continue
    lm = live_meta(path)
    # No snapshot is fine for a frozen title, which has nothing to compare with,
    # but a retitled page is compared with RETITLED and needs no snapshot.
    if lm is None and path not in RETITLED:
        continue
    lt, ld = lm or ('', '')
    ot, od, canon = local_meta(path)

    if not canon:
        no_canonical.append(path)

    if path in RETITLED:
        if ot == RETITLED[path]:
            retitled_ok += 1
        else:
            diffs.append(f'TITLE {path}\n     want : {RETITLED[path]}\n     ours : {ot}')
    elif lt and ot == lt:
        same_t += 1
    elif lt:
        diffs.append(f'TITLE {path}\n     live : {lt}\n     ours : {ot}')

    # Ours by design now — but it still has to exist, and it has to fit.
    if not od:
        diffs.append(f'DESC  {path}\n     ours : (none at all)')
    elif len(od) > 175:
        diffs.append(f'DESC  {path} is {len(od)} characters, past the 175 ceiling'
                     f'\n     ours : {od[:110]}')
    elif ld and od == ld:
        same_d += 1          # still word for word the live one
    else:
        filled_d += 1        # rewritten, or written where live had none

checked = len([p for p in paths if p not in SKIP])
print(f'checked            : {checked} urls (basket/account pages excluded by design)')
print(f'title identical    : {same_t}')
print(f'title as retitled  : {retitled_ok}/{len(RETITLED)}')
print(f'description still the live wording: {same_d}')
print(f'description ours, written to sell: {filled_d}')
print(f'canonical present  : {checked - len(no_canonical)}/{checked}')
print(f'differences        : {len(diffs)}')
for d in diffs[:12]:
    print('  !', d)
if no_canonical:
    print('  ! no canonical:', no_canonical[:5])
