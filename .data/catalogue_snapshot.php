<?php
/**
 * Save or restore data/products.php exactly.
 *
 * A test that drives the product form has to put the catalogue back byte for
 * byte afterwards. Reposting a form does not do that: values come back
 * HTML-escaped and go in escaped again, so a description gains a layer of
 * &amp; every single time. This snapshots the file instead.
 *
 *   php .data/catalogue_snapshot.php save     -> writes .data/products.snapshot
 *   php .data/catalogue_snapshot.php restore  -> puts it back and reports
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$live = $root . '/data/products.php';
$snap = $root . '/.data/products.snapshot';
$what = $argv[1] ?? '';

if ($what === 'save') {
    copy($live, $snap);
    echo "saved " . filesize($snap) . " bytes\n";
    exit(0);
}

if ($what === 'restore') {
    if (!is_file($snap)) { fwrite(STDERR, "no snapshot to restore\n"); exit(1); }
    $changed = md5_file($live) !== md5_file($snap);
    copy($snap, $live);
    unlink($snap);
    echo $changed ? "catalogue restored — the run had changed it\n" : "catalogue untouched\n";
    exit(0);
}

fwrite(STDERR, "usage: catalogue_snapshot.php save|restore\n");
exit(2);
