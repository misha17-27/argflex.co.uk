"""Assemble exactly the files that belong on a server, and zip them.

    python .data/build_release.py [target-folder]

Everything the site needs to serve a page goes in. The raw API dumps, the
build and test scripts, the git history, the local server helpers and the
notes stay behind — see DEPLOY.md. storage/ is created empty apart from the
file that denies it over HTTP: uploading a local storage/ would overwrite
live orders and the live admin account with test ones.
"""
import os, sys, shutil, zipfile, datetime

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
OUT  = os.path.abspath(sys.argv[1]) if len(sys.argv) > 1 \
    else os.path.join(os.path.dirname(ROOT), 'deploy')

FOLDERS = ['inc', 'pages', 'partials', 'data', 'admin', 'assets']

# Every PHP file in the root ships, minus the ones that are only for running
# the site locally. Listing them by hand once left wishlist-items.php behind,
# which the wishlist page fetches — so the rule is "all of them except these".
ROOT_SKIP = {'router.php'}
FILES = ['.htaccess', 'robots.txt']

# never shipped: local-only helpers, notes, the design concepts, the toolchain
SKIP_NAMES = {'.DS_Store', 'Thumbs.db', 'desktop.ini'}
SKIP_EXT   = {'.snapshot', '.tmp', '.log'}


def copy_tree(src, dst):
    kept = 0
    for base, dirs, files in os.walk(src):
        dirs[:] = [d for d in dirs if not d.startswith('.') or d == '.well-known']
        rel = os.path.relpath(base, src)
        out = dst if rel == '.' else os.path.join(dst, rel)
        os.makedirs(out, exist_ok=True)
        for name in files:
            if name in SKIP_NAMES or os.path.splitext(name)[1] in SKIP_EXT:
                continue
            shutil.copy2(os.path.join(base, name), os.path.join(out, name))
            kept += 1
    return kept


if os.path.isdir(OUT):
    shutil.rmtree(OUT)
os.makedirs(OUT)

print(f'building into {OUT}')
total = 0
for folder in FOLDERS:
    n = copy_tree(os.path.join(ROOT, folder), os.path.join(OUT, folder))
    total += n
    print(f'  {folder + "/":<12} {n:>4} files')

root_php = sorted(n for n in os.listdir(ROOT)
                  if n.endswith('.php') and n not in ROOT_SKIP
                  and os.path.isfile(os.path.join(ROOT, n)))
for name in root_php + FILES:
    src = os.path.join(ROOT, name)
    if os.path.isfile(src):
        shutil.copy2(src, os.path.join(OUT, name))
        total += 1
        print(f'  {name:<20}  1 file')

# storage is created, not copied: it must start empty on the server
os.makedirs(os.path.join(OUT, 'storage', 'orders'), exist_ok=True)
shutil.copy2(os.path.join(ROOT, 'storage', '.htaccess'),
             os.path.join(OUT, 'storage', '.htaccess'))
open(os.path.join(OUT, 'storage', 'orders', '.gitkeep'), 'w').close()
total += 2
print('  storage/         2 files  (empty — the server writes its own)')

# a short note beside the files, so whoever uploads them knows the rules
with open(os.path.join(OUT, 'UPLOAD-NOTES.txt'), 'w', encoding='utf-8', newline='\n') as f:
    f.write(
        'Arg Flex — what is in this folder\n'
        '=================================\n\n'
        'Upload everything here into the document root of the site or subdomain.\n'
        'Dotfiles matter: .htaccess in the root and inside data/, inc/, pages/,\n'
        'partials/ and storage/ is what keeps those folders private. Many FTP\n'
        'clients hide them by default — turn hidden files on before you drag.\n\n'
        'Then make these writable by the web server (755 first, 775 if the admin\n'
        'reports a folder as read-only):\n'
        '    data/  storage/  storage/orders/  assets/img/\n\n'
        'Open /admin/ and create the account. It lands in storage/users.php,\n'
        'which the server refuses to serve.\n\n'
        'On any host that is not argflex.co.uk the site sends noindex on every\n'
        'page and serves robots.txt as Disallow: / by itself, so a staging copy\n'
        'cannot take traffic from the live site. That switches off on its own\n'
        'once the real domain points here.\n\n'
        'The full checklist is DEPLOY.md in the project.\n'
    )
total += 1

size = sum(os.path.getsize(os.path.join(b, n))
           for b, _, fs in os.walk(OUT) for n in fs)
print(f'\n{total} files, {size / (1 << 20):.1f} MB')

stamp = datetime.date.today().isoformat()
zip_path = os.path.join(os.path.dirname(OUT), f'argflex-site-{stamp}.zip')
with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for base, _, files in os.walk(OUT):
        for name in files:
            full = os.path.join(base, name)
            z.write(full, os.path.relpath(full, OUT))
print(f'zipped to {zip_path}  ({os.path.getsize(zip_path) / (1 << 20):.1f} MB)')
