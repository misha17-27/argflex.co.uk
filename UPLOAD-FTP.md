# Putting the site on new.argflex.co.uk over FTP

Everything you need is already built. This is the whole job.

## Before you start

The folder to upload is **`D:\argflex\deploy`** — 185 files, about 6 MB. There
is a zip of the same thing at `D:\argflex\argflex-site-2026-08-25.zip` if your
host lets you upload one file and extract it, which is much faster than FTP.

Rebuild either at any time:

```bash
cd "D:\argflex\18.08.26-Yeni sayt" && python .data/build_release.py
```

## 1. Create the subdomain

In your hosting panel, add the subdomain **`new.argflex.co.uk`**. The panel
will offer a folder for it — something like `/public_html/new` or
`/home/<account>/new.argflex.co.uk`. Write that path down; it is where the
files go.

Then wait for the certificate. Most hosts issue one automatically within a few
minutes. **This matters**: `.htaccess` redirects `http://` to `https://`, so
until the subdomain has a certificate every request will fail. If your host
does not issue one, open `.htaccess` and put a `#` in front of these three
lines before uploading:

```apache
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !=https
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

## 2. Connect

In FileZilla, File → **Site Manager** → New site:

| | |
|---|---|
| Protocol | FTP |
| Host | `66.29.132.31` |
| Encryption | *Use explicit FTP over TLS if available* |
| Logon Type | Ask for password |
| User | `mmm@argflex.co.uk` |

Leave the password blank in the Site Manager and let it ask each time — that
way it is not sitting in a config file on the machine.

### Before you drag anything

**The live shop is on this same account and must not be touched.** Once you
are connected, look at the path box on the right-hand side and confirm it ends
in the subdomain folder — something like `/new.argflex.co.uk` or
`/public_html/new`.

If you can see `wp-config.php`, `wp-content` or `wp-admin` in the remote
listing, **you are in the live WordPress site — go up and into the subdomain
folder before uploading anything.** Nothing in this package should ever land
beside those files.

The remote folder you upload into should be empty apart from whatever the
panel put there when it made the subdomain (often a placeholder `index.html`
and a `cgi-bin`). Delete the placeholder `index.html` — otherwise the server
will show it instead of the shop.

## 3. Turn on hidden files in FileZilla

**Do this first, or the upload will silently break the site.** Eight
`.htaccess` files carry the routing and keep `data/`, `inc/`, `pages/`,
`partials/` and `storage/` private. FileZilla hides them by default and will
skip them without a word.

> Server → **Force showing hidden files**

You should then see `.htaccess` in the file list on the left when you open
`D:\argflex\deploy`.

## 4. Upload

With the subdomain folder open on the right, open `D:\argflex\deploy` on the
left, select everything including `.htaccess`, and drag it across. Six
megabytes over FTP takes a few minutes.

When it finishes, the remote folder should contain:

```
.htaccess   index.php   robots.txt   UPLOAD-NOTES.txt
admin/  assets/  data/  inc/  pages/  partials/  storage/
```

## 5. Permissions

Right-click each of these on the server → **File permissions** → `755`, and
tick *Recurse into subdirectories* for the first three:

```
data/            755
storage/         755
assets/img/      755
```

If the admin later says a folder is read-only, set that one to `775`.

## 6. Check it

From here, one command:

```bash
cd "D:\argflex\18.08.26-Yeni sayt" && python .data/check_deploy.py https://new.argflex.co.uk
```

It fetches the real address and reports what a browser and a search engine
actually get: eleven pages, the 404 page, that `data/`, `inc/`, `storage/` and
`.data/` are all refused, that the images and stylesheet are served with their
cache headers, that the admin is noindex — and that the copy itself is kept out
of the index.

## 7. First run

Open `https://new.argflex.co.uk/admin/`. It asks you to create the account;
it is written to `storage/users.php`, which the server refuses to serve.

Then **System status** in the admin — every line should be a tick. It checks
the PHP version and extensions, the writable folders, the private folders, and
what is still unset.

## About the search engines

You do not have to remember anything. On any host that is not
`argflex.co.uk` the site sends `X-Robots-Tag: noindex, nofollow, noarchive` on
every page and serves `robots.txt` as `Disallow: /` by itself. The canonicals
still point at the live domain. So this copy cannot be indexed and cannot take
traffic from the shop that is currently earning money.

The moment you point the real domain at the same folder, all of that switches
off on its own — there is no flag to set and nothing to undo.

## If something is wrong

| What you see | What it usually is |
|---|---|
| Only the homepage works, everything else 404s | `mod_rewrite` off, or `AllowOverride` is not `All` — ask the host |
| Every page 500s | `.htaccess` did not upload, or uploaded as a folder |
| No styling at all | `assets/` did not upload, or its permissions are wrong |
| Redirect loop | No certificate yet — comment out the three HTTPS lines in step 1 |
| Admin says a folder is read-only | Step 5, use `775` |
| You end up looking at the old WordPress site | You uploaded into the wrong folder — see step 2 |

## Afterwards

Change the FTP password in the panel. It has been shared, so treat it as
spent.

`DEPLOY.md` has the full version, including moving the real domain over
afterwards.
