"""Turn the raw WooCommerce/WP JSON dumps into PHP data files + local images."""
import json, os, re, subprocess, html, sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
D = os.path.join(ROOT, '.data')

products   = json.load(open(os.path.join(D, 'products.json'),   encoding='utf-8'))
categories = json.load(open(os.path.join(D, 'categories.json'), encoding='utf-8'))
posts      = json.load(open(os.path.join(D, 'posts.json'),      encoding='utf-8'))
variations = json.load(open(os.path.join(D, 'variations.json'), encoding='utf-8'))

# ---------------------------------------------------------------- images
def pick_from_srcset(srcset, want=768):
    """Choose the smallest candidate that is still >= want px wide."""
    if not srcset:
        return None
    cands = []
    for part in srcset.split(','):
        part = part.strip()
        m = re.match(r'(\S+)\s+(\d+)w$', part)
        if m:
            cands.append((int(m.group(2)), m.group(1)))
    if not cands:
        return None
    cands.sort()
    for w, u in cands:
        if w >= want:
            return u
    return cands[-1][1]

downloaded = {}
def fetch(url, folder, slug):
    """Download url into assets/img/<folder>/<slug>.<ext>, return the relative path."""
    if not url:
        return None
    key = (folder, slug)
    if key in downloaded:
        return downloaded[key]
    ext = os.path.splitext(url.split('?')[0])[1].lower() or '.jpg'
    if ext not in ('.jpg', '.jpeg', '.png', '.webp', '.gif'):
        ext = '.jpg'
    rel = f'assets/img/{folder}/{slug}{ext}'
    dest = os.path.join(ROOT, rel)
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    if not os.path.exists(dest) or os.path.getsize(dest) == 0:
        r = subprocess.run(['curl', '-sS', '-L', '--max-time', '60', '-o', dest, url],
                           capture_output=True)
        if r.returncode != 0 or not os.path.exists(dest) or os.path.getsize(dest) == 0:
            print('  ! failed', url)
            downloaded[key] = None
            return None
    downloaded[key] = rel
    return rel

# ---------------------------------------------------------------- helpers
MOJIBAKE = re.compile(r'Â[°³²±\xa0]|â€[“”˜™œ\x9d]|Ã[\x80-\xbf]')

def fix_mojibake(s):
    """The live site stores UTF-8 text that was decoded as cp1252 once too
    often ("-40Â°C", "1m â€“ 50m"). Round-trip it back when those markers
    appear; cp1252 first because that is what actually mis-decoded it."""
    if not s or not MOJIBAKE.search(s):
        return s
    for codec in ('cp1252', 'latin-1'):
        try:
            repaired = s.encode(codec).decode('utf-8')
        except (UnicodeEncodeError, UnicodeDecodeError):
            continue
        if not MOJIBAKE.search(repaired):
            return repaired
    return s

def clean_html(s):
    s = fix_mojibake(s)
    if not s:
        return ''
    s = re.sub(r'<!--.*?-->', '', s, flags=re.S)
    s = re.sub(r'\s*(class|id|style|data-[\w-]+)="[^"]*"', '', s)
    s = re.sub(r'<(script|style)[^>]*>.*?</\1>', '', s, flags=re.S | re.I)
    # the page template supplies the h1, so demote any h1 inside imported body copy
    s = re.sub(r'<(/?)h1(\s|>)', r'<\1h2\2', s, flags=re.I)
    # imported tables are wider than a phone; give each one its own scroller
    s = re.sub(r'<table\b', '<div class="table-scroll"><table', s, flags=re.I)
    s = re.sub(r'</table>', '</table></div>', s, flags=re.I)
    s = re.sub(r'\n{3,}', '\n\n', s)
    return s.strip()

def to_text(s, limit=None):
    t = re.sub(r'<[^>]+>', ' ', fix_mojibake(s or ''))
    t = html.unescape(t)
    t = re.sub(r'\s+', ' ', t).strip()
    return t[:limit].rstrip() if limit else t

def php_val(v, indent=0):
    pad = '    ' * indent
    if v is None:
        return 'null'
    if isinstance(v, bool):
        return 'true' if v else 'false'
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, list):
        if not v:
            return '[]'
        inner = ',\n'.join(pad + '    ' + php_val(x, indent + 1) for x in v)
        return '[\n' + inner + ',\n' + pad + ']'
    if isinstance(v, dict):
        if not v:
            return '[]'
        inner = ',\n'.join(f"{pad}    '{k}' => {php_val(x, indent + 1)}" for k, x in v.items())
        return '[\n' + inner + ',\n' + pad + ']'
    s = str(v).replace('\\', '\\\\').replace("'", "\\'")
    return "'" + s + "'"

def write_php(name, header, data):
    path = os.path.join(ROOT, 'data', name)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8', newline='\n') as f:
        f.write("<?php\n/**\n * " + header + "\n * Generated from the live argflex.co.uk data - do not edit by hand.\n */\n"
                "if (!defined('ROOT_DIR')) { http_response_code(404); exit; }\n\nreturn ")
        f.write(php_val(data))
        f.write(";\n")
    print(f'  data/{name}: {os.path.getsize(path)} bytes')

# ---------------------------------------------------------------- categories
print('categories...')
by_id = {c['id']: c for c in categories}
cat_out = []
for i, c in enumerate(sorted(categories, key=lambda x: (x['parent'] != 0, x['name']))):
    parent = by_id.get(c['parent'])
    path = f"{parent['slug']}/{c['slug']}" if parent else c['slug']
    cat_out.append({
        'id': c['id'],
        'slug': c['slug'],
        'name': fix_mojibake(html.unescape(c['name'])),
        'parent': parent['slug'] if parent else '',
        'path': path,
        'count': c['count'],
        'description': to_text(c.get('description') or ''),
        'image': '',
        'sort': i,
    })

# the twelve the homepage used to hard-code; now a flag on the product itself
FEATURED = {
    'oil-resistant-hose-sae-j30-r6-7mm', 'submersible-fuel-hose-sae-j30-r10-0-5m-50m',
    'sandblast-hose-56-mm%c2%b3', 'car-heater-hose-125c-sae-j20-r3',
    'oxygen-hose-agoma', 'twin-line-welding-hose-for-oxygen-and-acetylene',
    'pvc-ventilation-hose-termoresist', 'pu-hose-for-pneumatic-tools-notas-pu',
    'pvc-garden-hose-hobby', 'fuel-hose-din-73379-b', 'asfa-clamps', 'gbs-clamps',
}

# ---------------------------------------------------------------- products
print('products...')
cat_slug_by_id = {c['id']: c['slug'] for c in categories}
prod_out = []
for p in sorted(products, key=lambda x: x['name']):
    slug = p['slug']
    imgs = []
    for i, im in enumerate(p.get('images') or []):
        url = pick_from_srcset(im.get('srcset'), 768) or im.get('src')
        rel = fetch(url, 'products', slug if i == 0 else f'{slug}-{i+1}')
        if rel:
            imgs.append(rel)

    # Terms keep both the display name ("12.5mm") and the slug ("12-5mm"),
    # because variations reference the slug while the picker shows the name.
    attrs = []
    for a in p.get('attributes') or []:
        attrs.append({
            'name': fix_mojibake(a['name']),
            'variation': bool(a.get('has_variations')),
            'terms': [{'name': fix_mojibake(t['name']), 'slug': t['slug']}
                      for t in (a.get('terms') or [])],
        })

    name_by_slug = {}
    for a in attrs:
        for t in a['terms']:
            name_by_slug[(a['name'], t['slug'])] = t['name']

    variants = []
    for v in p.get('variations') or []:
        vd = variations.get(str(v['id']))
        if not vd:
            continue
        chosen = {fix_mojibake(a['name']): a['value'] for a in (v.get('attributes') or [])}
        label = ', '.join(
            f"{k}: {name_by_slug.get((k, val), val)}" for k, val in chosen.items()
        ) or fix_mojibake(vd.get('label', ''))
        variants.append({
            'key': '|'.join(chosen.get(a['name'], '') for a in attrs if a['variation']),
            'attrs': chosen,
            'label': label,
            'price': int(vd['price']) if vd.get('price') else 0,
            'sale': 0,
        })
    variants.sort(key=lambda x: x['price'])

    pr = p.get('prices') or {}
    rng = pr.get('price_range') or {}
    pmin = int(rng.get('min_amount') or pr.get('price') or 0)
    pmax = int(rng.get('max_amount') or pr.get('price') or 0)

    prod_out.append({
        'id': p['id'],
        'slug': slug,
        'name': fix_mojibake(html.unescape(p['name'])),
        'type': p['type'],
        'sku': p.get('sku') or '',
        'cats': [cat_slug_by_id[c['id']] for c in (p.get('categories') or []) if c['id'] in cat_slug_by_id],
        'images': imgs,
        'short': clean_html(p.get('short_description')),
        'desc': clean_html(p.get('description')),
        'price_min': pmin,
        'price_max': pmax,
        'purchasable': bool(p.get('is_purchasable')) and pmin > 0,
        'primary_cat': '',
        'tags': [t['name'] for t in (p.get('tags') or [])],
        # the fields the admin panel owns; the importer only seeds them
        'sale_min': 0, 'sale_max': 0, 'sale_from': '', 'sale_to': '',
        'manage_stock': False, 'stock_qty': 0, 'backorders': 'no',
        'low_stock': 0, 'sold_individually': False,
        'weight': '', 'length': '', 'width': '', 'height': '', 'shipping_class': '',
        'upsells': [], 'crosssells': [], 'purchase_note': '', 'menu_order': 0,
        'virtual': False,
        'status': 'published',
        'featured': slug in FEATURED,
        'stock': 'instock' if p.get('is_in_stock', True) else 'outofstock',
        'created': (p.get('date_created') or '')[:10] or '2024-01-01',
        'attrs': attrs,
        'variants': variants,
    })

# ---------------------------------------------------------------- posts
print('posts...')
post_out = []
for p in sorted(posts, key=lambda x: x['date'], reverse=True):
    slug = p['slug']
    fm = (p.get('_embedded') or {}).get('wp:featuredmedia') or []
    img = None
    if fm and isinstance(fm[0], dict) and fm[0].get('source_url'):
        sizes = ((fm[0].get('media_details') or {}).get('sizes') or {})
        best = sizes.get('medium_large') or sizes.get('large') or sizes.get('full')
        img = fetch((best or {}).get('source_url') or fm[0]['source_url'], 'blog', slug)
    post_out.append({
        'slug': slug,
        'title': fix_mojibake(html.unescape(re.sub(r'<[^>]+>', '', p['title']['rendered']))),
        'date': p['date'][:10],
        'excerpt': to_text(p['excerpt']['rendered'], 190),
        'content': clean_html(p['content']['rendered']),
        'image': img,
    })

# ---------------------------------------------------------------- write
print('writing php...')
write_php('categories.php', 'Product categories', cat_out)
write_php('products.php',   'Products with variants and prices (pence, excl. VAT)', prod_out)
write_php('posts.php',      'Blog posts', post_out)

print(f'\nproducts: {len(prod_out)}  categories: {len(cat_out)}  posts: {len(post_out)}')
print(f'product images: {sum(len(p["images"]) for p in prod_out)}  post images: {sum(1 for p in post_out if p["image"])}')
missing = [p['slug'] for p in prod_out if not p['images']]
if missing:
    print('products without an image:', missing)
