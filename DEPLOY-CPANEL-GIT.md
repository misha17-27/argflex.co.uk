# Deploying through cPanel Git Version Control

cPanel clones the repository onto the server and copies it into place when you
press a button. No FTP, no password to type anywhere, and every deploy is a
named commit you can point at afterwards.

Two files in the repository drive it:

| File | What it does |
|---|---|
| `.cpanel.yml` | Names the folder to deploy into. **This is the one line you edit.** |
| `.cpanel/deploy.sh` | Does the copying, and refuses to run if that folder turns out to be the live WordPress site |

## 1. Make the subdomain

cPanel → **Domains** → *Create A Domain* → `new.argflex.co.uk`.

Untick *Share document root*. cPanel offers a folder — usually
`/home/<account>/new.argflex.co.uk`. **Write the exact path down.**

Wait for the certificate to appear under *SSL/TLS Status*. `.htaccess` sends
`http://` to `https://`, so until there is one every request loops. If your
host does not issue one, comment out these three lines in `.htaccess` first:

```apache
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !=https
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

## 2. Point the deploy at that folder

Open `.cpanel.yml` and set the path to what cPanel gave you:

```yaml
    - export DEPLOYPATH=$HOME/new.argflex.co.uk
```

`$HOME` is your account's home directory, so this is right for the usual
layout. If cPanel put the subdomain under `public_html`, write
`$HOME/public_html/new` instead.

Commit and push:

```bash
cd "D:\argflex\18.08.26-Yeni sayt"
git add .cpanel.yml && git commit -m "Deploy to new.argflex.co.uk" && git push
```

## 3. Let cPanel see the repository

The repository is at `https://github.com/misha17-27/argflex.co.uk.git`.

**If it is public**, nothing to do — cPanel can clone it over HTTPS.

**If it is private**, cPanel needs a key GitHub will accept:

1. cPanel → **SSH Access** → *Manage SSH Keys* → *Generate a New Key* (leave
   the passphrase empty, or cPanel cannot use it unattended)
2. *View/Download* the **public** key, copy it
3. GitHub → the repository → Settings → **Deploy keys** → *Add deploy key* →
   paste it, read-only is enough
4. Back in cPanel, *Manage* → **Authorize** the key
5. Clone with the SSH address: `git@github.com:misha17-27/argflex.co.uk.git`

## 4. Clone it

cPanel → **Git™ Version Control** → *Create*:

| | |
|---|---|
| Clone a Repository | on |
| Clone URL | the HTTPS or SSH address above |
| Repository Path | `/home/<account>/repositories/argflex` |
| Repository Name | `argflex` |

This clones the code into that path. It is **not** the website folder — the
website folder is what `.cpanel.yml` names, and cPanel copies into it in the
next step.

## 5. Deploy

In *Git Version Control* → **Manage** → the **Pull or Deploy** tab →
**Deploy HEAD Commit**.

The log should read:

```
Deploying to /home/<account>/new.argflex.co.uk
  code
  assets
  data — first deploy, seeding from the repository
  storage — ready, contents untouched
Done. 184 files in place.
```

If instead it says `REFUSING: ... is the live WordPress site`, then
`DEPLOYPATH` is pointing at the shop. Fix the path in `.cpanel.yml`, push, and
deploy again. Nothing was copied.

## 6. Check it from the outside

```bash
cd "D:\argflex\18.08.26-Yeni sayt" && python .data/check_deploy.py https://new.argflex.co.uk
```

Eleven pages, the 404 page, `data/` `inc/` `storage/` `.data/` all refused,
images and stylesheet with their cache headers, the admin noindex, and the copy
kept out of the search index.

Then open `https://new.argflex.co.uk/admin/`, create the account, and look at
**System status** — every line should be a tick.

## What a second deploy does, and does not do

This matters once the shop is being used, because the admin panel writes to the
server and the repository knows nothing about it.

| | On every deploy |
|---|---|
| `inc/ pages/ partials/ admin/`, the root PHP, `.htaccess`, `robots.txt` | **Replaced** — the repository wins |
| `assets/css`, `assets/js` | **Replaced** |
| `assets/img` | Added to and updated, **never deleted** — images uploaded in the admin survive |
| `data/` | **Left alone** after the first deploy. The admin panel owns the catalogue; overwriting it would throw away every product edit made on the server |
| `storage/` | **Never touched.** Orders, settings and the admin account live here |

So: push code freely. If you need to push a *catalogue* change from your
machine instead, delete `data/` on the server first and deploy — but only when
you are sure nothing has been edited in the admin since.

Verified by deploying twice into a scratch folder with an edited product, a
stored order, an admin account, saved settings and an uploaded image in place:
all five survived, the code refreshed, and pointing the path at a folder
containing `wp-config.php` refused without copying anything.

## Afterwards

The FTP password you shared is still valid — change it in cPanel → *FTP
Accounts*. With git deploy you will not need it again.
