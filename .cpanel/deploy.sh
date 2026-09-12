#!/bin/sh
#
# Deploy this repository into a document root. Called by .cpanel.yml, which
# sets DEPLOYPATH first; run it by hand with the same variable to test.
#
#   DEPLOYPATH=$HOME/argflex.co.uk sh .cpanel/deploy.sh
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
    echo "Point DEPLOYPATH at this build's document root in .cpanel.yml and try again."
    exit 1
fi

mkdir -p "$DEPLOYPATH"

# ----------------------------------------------------------------- code
# Staged, then swapped in by renaming. This used to delete the four folders
# and copy them back, and for as long as that copy took there was no inc/ on
# the server: every request in the window hit a missing require and answered
# 500, while the deploy log went on to say Done. A copy of a few hundred files
# over a shared disk is seconds, not milliseconds, and a customer mid-checkout
# has no idea the shop is being updated.
#
# A rename inside one filesystem is as close to instant as this gets, so the
# gap shrinks from the length of a copy to the length of two renames. The
# staging folder is under DEPLOYPATH so it IS the same filesystem — staging in
# /tmp would make each swap a copy again — and it is dot-prefixed and removed
# either way; .htaccess denies dotfiles, so it is never served even mid-deploy.
echo "  code"
STAGE="$DEPLOYPATH/.deploy-stage"
rm -rf "$STAGE"
mkdir -p "$STAGE"
cp -a inc pages partials admin "$STAGE/"

for dir in inc pages partials admin; do
    [ -d "$DEPLOYPATH/$dir" ] && mv "$DEPLOYPATH/$dir" "$STAGE/.going-$dir"
    mv "$STAGE/$dir" "$DEPLOYPATH/$dir"
done
rm -rf "$STAGE"

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
mkdir -p "$DEPLOYPATH/assets/img"

# Swapped in the same way, and for the same reason: a page served while css/
# was missing came back unstyled, which is the kind of thing a customer
# screenshots.
STAGE="$DEPLOYPATH/assets/.deploy-stage"
rm -rf "$STAGE"
mkdir -p "$STAGE"
cp -a assets/css assets/js "$STAGE/"

for dir in css js; do
    [ -d "$DEPLOYPATH/assets/$dir" ] && mv "$DEPLOYPATH/assets/$dir" "$STAGE/.going-$dir"
    mv "$STAGE/$dir" "$DEPLOYPATH/assets/$dir"
done
rm -rf "$STAGE"

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
