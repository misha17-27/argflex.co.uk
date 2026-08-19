"""Collect the attributes the products already use into a global list."""
import pathlib, os, re

os.chdir(os.path.join(os.path.dirname(__file__), '..'))

prods = pathlib.Path('data/products.php').read_text(encoding='utf-8')

attrs = {}
pattern = re.compile(
    r"'name' => '([^']+)',\s*\n\s*'variation' => (?:true|false),\s*\n\s*'terms' => \[(.*?)\n            \],",
    re.S)
for m in pattern.finditer(prods):
    name, terms_src = m.group(1), m.group(2)
    entry = attrs.setdefault(name, {})
    for tname, tslug in re.findall(r"'name' => '([^']*)',\s*\n\s*'slug' => '([^']*)'", terms_src):
        entry[tslug] = tname

def slugify(value):
    value = value.lower().replace('&', 'and')
    return re.sub(r'[^a-z0-9]+', '-', value).strip('-')

def numeric_first(pair):
    """8mm before 10mm before 'other' — read the leading number when there is one."""
    m = re.match(r'([\d.]+)', pair[1])
    return (0, float(m.group(1))) if m else (1, pair[1].lower())

out = []
for i, (name, terms) in enumerate(sorted(attrs.items())):
    ordered = sorted(terms.items(), key=numeric_first)
    out.append({
        'slug': slugify(name),
        'name': name,
        'order_by': 'custom',
        'sort': i,
        'terms': [{'name': tname, 'slug': tslug} for tslug, tname in ordered],
    })

def php(value, indent=0):
    pad = '    ' * indent
    if isinstance(value, bool):
        return 'true' if value else 'false'
    if isinstance(value, int):
        return str(value)
    if isinstance(value, list):
        if not value:
            return '[]'
        inner = ',\n'.join(pad + '    ' + php(x, indent + 1) for x in value)
        return '[\n' + inner + ',\n' + pad + ']'
    if isinstance(value, dict):
        if not value:
            return '[]'
        inner = ',\n'.join(f"{pad}    '{k}' => {php(v, indent + 1)}" for k, v in value.items())
        return '[\n' + inner + ',\n' + pad + ']'
    text = str(value).replace('\\', '\\\\').replace("'", "\\'")
    return "'" + text + "'"

pathlib.Path('data/attributes.php').write_text(
    "<?php\n/**\n * Global product attributes and their terms.\n"
    " * Products reference these by name; the terms give every variant its key.\n */\n"
    "if (!defined('ROOT_DIR')) { http_response_code(404); exit; }\n\nreturn "
    + php(out) + ";\n",
    encoding='utf-8', newline='\n')

print(f'data/attributes.php: {len(out)} attributes')
for a in out:
    shown = ', '.join(t['name'] for t in a['terms'][:9])
    print(f"  {a['name']:16} {len(a['terms']):2} terms — {shown}")
