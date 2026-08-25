#!/bin/sh
#
# Deploy this repository into a document root. Called by .cpanel.yml, which
# sets DEPLOYPATH first; run it by hand with the same variable to test.
#
#   DEPLOYPATH=$HOME/new.argflex.co.uk sh .cpanel/deploy.sh
#
# Three rules shape everything below:
#
#   Code is replaced.   inc/ pages/ partials/ admin/ and the root PHP files
#                       are whatever the repository says.
#
#   Data is seeded.     data/ is copied on the first deploy and never again.
#                       After that the admin panel owns it, and overwriting it
#                       would throw away every product edit made on the server.
#
#   Storage is sacred.  storage/ holds the orders, the settings and the admin
#                       account. It is created if missing and otherwise left
#                       completely alone.
#
set -e

if [ -z "$DEPLOYPATH" ]; then
    echo "DEPLOYPATH is not set — nothing to deploy into."
    exit 1
fi

echo "Deploying to $DEPLOYPATH"

# --------------------------------------------------------------- safety
# The live WordPress shop is on the same hosting account and is still taking
# orders. Copying this over it would take it down, so refuse outright.
if [ -e "$DEPLOYPATH/wp-config.php" ] || [ -d "$DEPLOYPATH/wp-content" ]; then
    echo ""
    echo "REFUSING: $DEPLOYPATH is the live WordPress site."
    echo "Point DEPLOYPATH at the subdomain folder in .cpanel.yml and try again."
    exit 1
fi

mkdir -p "$DEPLOYPATH"

# ----------------------------------------------------------------- code
echo "  code"
rm -rf "$DEPLOYPATH/inc" "$DEPLOYPATH/pages" "$DEPLOYPATH/partials" "$DEPLOYPATH/admin"
cp -a inc pages partials admin "$DEPLOYPATH/"

# every PHP file in the root except the local-only router
for f in *.php; do
    [ "$f" = "router.php" ] && continue
    cp -a "$f" "$DEPLOYPATH/"
done
cp -a .htaccess robots.txt "$DEPLOYPATH/"

# --------------------------------------------------------------- assets
# The stylesheet and script are replaced. Images are added and updated but
# never deleted, because the admin panel uploads into the same folders.
echo "  assets"
rm -rf "$DEPLOYPATH/assets/css" "$DEPLOYPATH/assets/js"
mkdir -p "$DEPLOYPATH/assets/img"
cp -a assets/css assets/js "$DEPLOYPATH/assets/"
cp -a assets/img/. "$DEPLOYPATH/assets/img/"

# -------------------------------------------------------------- storage
mkdir -p "$DEPLOYPATH/storage/orders"
[ -f "$DEPLOYPATH/storage/.htaccess" ] || cp -a storage/.htaccess "$DEPLOYPATH/storage/.htaccess"
echo "  storage — ready, contents untouched"

# ----------------------------------------------------------------- data
# The admin panel drops a marker the first time it writes to data/. Until
# that exists, nobody has edited the catalogue on this server and the
# repository is the better copy, so changes come through. After it exists the
# server owns the catalogue and a deploy must not touch it.
if [ -f "$DEPLOYPATH/storage/.catalogue-edited" ]; then
    echo "  data — left alone, it has been edited in the admin here"
else
    echo "  data — refreshed from the repository, not yet edited here"
    rm -rf "$DEPLOYPATH/data"
    cp -a data "$DEPLOYPATH/"
fi

# ---------------------------------------------------------- permissions
chmod 755 "$DEPLOYPATH/data" "$DEPLOYPATH/storage" "$DEPLOYPATH/storage/orders" 2>/dev/null || true
chmod -R 755 "$DEPLOYPATH/assets/img" 2>/dev/null || true

# a placeholder from the hosting panel would be served instead of the shop
rm -f "$DEPLOYPATH/index.html" "$DEPLOYPATH/default.html"

echo ""
echo "Done. $(find "$DEPLOYPATH" -type f | wc -l) files in place."
echo "Open /admin/ to create the account if this was the first deploy."
