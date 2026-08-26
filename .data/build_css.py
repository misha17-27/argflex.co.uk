"""Build assets/css/site.css — the single stylesheet every page loads.

Order matters: v1.css sets the variables and the shell, pages.css builds on
them, checkout.css after them because it overrides a few form rules, and
a11y.css last of all because its focus rules have to outrank every
`outline:0` in the files before it.

Keeping the list here is the point — site.css was hand-concatenated once
and quietly lost checkout.css, so the checkout ran unstyled until it was
spotted. The ordering check at the end is the same idea: `.qty input` and
`input:focus-visible` score the same, so the focus ring survives only while
it is written last, and nothing about that is visible from either file.
"""
import os, re

PARTS = ['v1.css', 'pages.css', 'checkout.css', 'a11y.css']

os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'assets', 'css'))

out = []
for name in PARTS:
    with open(name, encoding='utf-8') as f:
        body = f.read().strip()
    out.append(f'/* ---------- {name} ---------- */\n{body}\n')
    print(f'  {name}: {len(body):>7,} bytes')

with open('site.css', 'w', encoding='utf-8', newline='\n') as f:
    f.write('\n'.join(out))

css = open('site.css', encoding='utf-8').read()

# The ring has to come after everything that turns a ring off.
suppressed = [m.start() for m in re.finditer(r'outline:\s*(0|none)\b', css)]
ring = re.search(r'\ba:focus-visible[^{]*\{[^}]*outline:\s*\d+px solid', css)
if not ring:
    raise SystemExit('site.css has no element-qualified focus ring — a keyboard '
                     'user would have nothing to follow.')
if suppressed and max(suppressed) > ring.start():
    raise SystemExit('an `outline:0` is written after the focus ring, which cancels '
                     'it wherever the two selectors tie. Move it, or move the ring.')

print(f'site.css: {os.path.getsize("site.css"):,} bytes from {len(PARTS)} files')
print('  the focus ring outranks every rule that suppresses one')
