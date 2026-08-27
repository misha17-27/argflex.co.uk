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

# A style aimed at a heading level the templates no longer emit.
#
# The footer's headings were h4, then h3, then h2, for the sake of the
# heading order. `.ftr h4` was never moved with them, so it matched nothing:
# the column headings rendered at 24px with no space beneath, and every
# check passed because none of them looks at whether a rule applies to
# anything.
import glob

root = os.path.join('..', '..')
markup = ''
for pattern in ('pages/*.php', 'inc/*.php', 'partials/*.php', 'admin/views/*.php'):
    for path in glob.glob(os.path.join(root, pattern)):
        with open(path, encoding='utf-8', errors='replace') as f:
            markup += f.read()

emitted = set(re.findall(r'<(h[1-6])\b', markup))
orphans = {}
for selector in re.findall(r'([^{}]+)\{', css):
    for m in re.finditer(r'\.([a-z0-9-]+)\s+(h[1-6])\b', selector):
        klass, tag = m.group(1), m.group(2)
        if tag in emitted:
            continue
        # a rule listing several levels is fine as long as one of them exists
        levels = set(re.findall(r'\.' + re.escape(klass) + r'\s+(h[1-6])\b', selector))
        if levels & emitted:
            continue
        orphans.setdefault(tag, set()).add(klass)

if orphans:
    lines = [f'  {tag} under .{", .".join(sorted(names))}' for tag, names in sorted(orphans.items())]
    raise SystemExit('site.css styles heading levels the templates never emit:\n'
                     + '\n'.join(lines)
                     + '\nThe markup moved and the rule did not, so it matches nothing.')

print(f'site.css: {os.path.getsize("site.css"):,} bytes from {len(PARTS)} files')
print('  the focus ring outranks every rule that suppresses one')
print('  every styled heading level is one the templates actually emit')
