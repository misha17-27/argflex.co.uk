# Going live

The site is a folder of PHP files. There is no build step to run on the
server, no database to create and nothing to install — upload it, make three
folders writable, and it runs.

## 1. Before you upload

On your own machine, with the local server running:

```bash
ARGFLEX_ADMIN_EMAIL=you@example.com ARGFLEX_ADMIN_PASSWORD=... python .data/preflight.py --full
```

That parses every PHP file, rebuilds the stylesheet and checks it was already
current, crawls all 90 pages, diffs every title and description against the
live site, walks all 30 admin screens, and runs the behaviour suites for
discount codes, customers and reports. It refuses to say "ready" if anything
fails. Do not upload until it does.

## 2. What to upload

Everything **except** these:

| Leave behind | Why |
|---|---|
| `.data/` | The raw dumps from the old site and the build and test scripts. Not needed to serve a page, and it is a copy of the whole catalogue |
| `.git/` | The history |
| `storage/` | Orders, settings and the admin account. The server makes its own — uploading yours would overwrite live orders with test ones |
| `concepts.html`, `home-v2.html`, `home-v3.html` | The three original homepage designs, kept for reference |
| `start-server.bat`, `router.php` | Only used by PHP's built-in server |
| `README.md`, `DEPLOY.md` | Notes, not code |

Everything else goes: `index.php`, `.htaccess`, `inc/`, `pages/`, `partials/`,
`data/`, `admin/`, `assets/`.

The `.htaccess` files inside `data/`, `inc/`, `pages/`, `partials/`,
`storage/` and `.data/` are what keep those folders private, so upload them
with their folders — some FTP clients hide dotfiles by default.

## 3. On the server

**PHP 8.1 or newer**, with `json` (always there) and `openssl` if you want to
send mail over SMTP. `mbstring` is used where it exists and worked around
where it does not.

Create the writable folders and set permissions:

```bash
mkdir -p storage/orders
chmod 755 data storage storage/orders assets/img assets/img/products assets/img/blog assets/img/site
```

Some shared hosts need `775` or `777` instead — try `755` first, and only
loosen it if the admin says a folder is read-only.

`data/` has to be writable because the admin panel writes the catalogue back
to it. If you would rather keep it read-only, the site still serves every
page; only editing stops working.

## 4. First run

1. Open `https://argflex.co.uk/admin/`. It asks you to create the account,
   which is written to `storage/users.php` — a file the server refuses to
   serve and git ignores. Use a long password; ten characters is the minimum
   and eight wrong attempts lock the address out for fifteen minutes.
2. **Settings → Emails**: fill in the SMTP host, port, username and password
   for a mailbox on your own domain, then press *Save and send a test*. Until
   you do, the site falls back to PHP's `mail()`, which most inboxes treat as
   spam.
3. **Security**: paste the Cloudflare Turnstile site and secret keys. Without
   them the forms still have a honeypot, but Turnstile is what actually stops
   the bots.
4. **Settings → General**: confirm the store address, the currency and
   whether discount codes are accepted.
5. **Settings → Shipping**: confirm the zones and what each charges. The table
   at the bottom shows what four sample orders would be quoted right now.
6. **System status**: every line should be a tick. It checks the PHP version
   and extensions, that the write folders really are writable, that each
   private folder is denied over HTTP, and that mail and anti-spam are set.

## 5. Switching the domain over

The URLs match the WordPress site exactly, so nothing needs redirecting and
the rankings carry over as they are. In order:

1. Take a copy of the WordPress site and its database first. Not because you
   will need it, but because you cannot make one afterwards.
2. Point the document root at this folder.
3. Load `https://argflex.co.uk/` and click through: a category, a product with
   options, add to basket, checkout as far as the summary, the contact form.
4. Check `https://argflex.co.uk/sitemap.xml` renders, then re-submit it in
   Google Search Console. `/sitemap_index.xml` still answers as well, because
   that is the address Yoast registered.
5. Watch Search Console coverage for a week. The addresses have not changed,
   so a drop would mean something is not being served, not that Google has
   re-ranked anything.

`.htaccess` sends `http://` and `www.` permanently to `https://argflex.co.uk`.
If your host has no certificate yet, comment that first pair of rules out
before you upload or every request will loop.

## 6. Afterwards

- Orders arrive in `storage/orders/` as one JSON file each, and appear under
  **Orders**. Back that folder up with whatever backs up the rest of the site.
- **Reports** shows what is selling. **Customers** builds itself from the
  orders as they come in.
- Editing the catalogue writes `data/*.php`. Every write goes to a temporary
  file, is parsed to confirm it still returns an array, and only then renamed
  over the target — a failed save cannot take the site down.
- To pull fresh data from the old WordPress site again, `.data/build_data.py`
  still works as long as it is still running somewhere.

## If something goes wrong

| Symptom | Look at |
|---|---|
| Every page is a 404 except the homepage | `mod_rewrite` is off, or `AllowOverride` is not `All` |
| Admin says a folder is read-only | Permissions in step 3 |
| Nothing sends | **Settings → Emails**, and `storage/mail-errors.log` |
| A page is blank | PHP error log — every write is atomic, so it will not be a half-written data file |
| Prices look wrong | **Settings → General** for the currency, **Tax** for the rate |
