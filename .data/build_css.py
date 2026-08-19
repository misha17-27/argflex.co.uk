"""Build assets/css/site.css — the single stylesheet every page loads.

Order matters: v1.css sets the variables and the shell, pages.css builds on
them, checkout.css last because it overrides a few form rules. Keeping the
list here is the point — site.css was hand-concatenated once and quietly
lost checkout.css, so the checkout ran unstyled until it was spotted.
"""
import os

PARTS = ['v1.css', 'pages.css', 'checkout.css']

os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'assets', 'css'))

out = []
for name in PARTS:
    with open(name, encoding='utf-8') as f:
        body = f.read().strip()
    out.append(f'/* ---------- {name} ---------- */\n{body}\n')
    print(f'  {name}: {len(body):>7,} bytes')

with open('site.css', 'w', encoding='utf-8', newline='\n') as f:
    f.write('\n'.join(out))

print(f'site.css: {os.path.getsize("site.css"):,} bytes from {len(PARTS)} files')
