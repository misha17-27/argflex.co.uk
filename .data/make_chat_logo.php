<?php
/**
 * The logo at the size the chat panel shows it.
 *
 * partials/chat.php put assets/img/site/logo.png in its header: 1367x370 and
 * 63 KB, fetched eagerly on every page that carries the chat, to be drawn 32
 * pixels wide inside a panel that starts hidden. This writes the same logo
 * 96 pixels wide — three device pixels to each of the 32 it fills — as WebP
 * with its transparency kept, which comes to about two kilobytes.
 *
 * logo.png itself stays where it is: the Organization block names it as the
 * shop's logo and Google wants that one large.
 *
 *   php .data/make_chat_logo.php
 *
 * Run it again whenever logo.png is replaced. .data/check_small_defects.py
 * fails if the small file is missing, or is older than the logo it was made
 * from by content (it compares proportions, not dates).
 */
declare(strict_types=1);

const LOGO_FROM  = 'assets/img/site/logo.png';
const LOGO_TO    = 'assets/img/site/logo-chat.webp';
const LOGO_WIDTH = 96;

$root = dirname(__DIR__);
$src  = imagecreatefrompng($root . '/' . LOGO_FROM);
if (!$src) {
    fwrite(STDERR, 'cannot read ' . LOGO_FROM . "\n");
    exit(1);
}

$fromW = imagesx($src);
$fromH = imagesy($src);
$toW   = LOGO_WIDTH;
$toH   = (int) round($fromH * $toW / $fromW);

$dst = imagecreatetruecolor($toW, $toH);
imagealphablending($dst, false);
imagesavealpha($dst, true);
imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
imagecopyresampled($dst, $src, 0, 0, 0, 0, $toW, $toH, $fromW, $fromH);

if (!imagewebp($dst, $root . '/' . LOGO_TO, 90)) {
    fwrite(STDERR, 'cannot write ' . LOGO_TO . "\n");
    exit(1);
}

clearstatcache();
printf("%s  %dx%d  %d bytes  (from %dx%d, %d bytes)\n", LOGO_TO, $toW, $toH,
       filesize($root . '/' . LOGO_TO), $fromW, $fromH, filesize($root . '/' . LOGO_FROM));
