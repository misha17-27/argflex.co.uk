"""Draw the three new article images for argflex.co.uk.

    python .data/make_blog_images.py

HOUSE STYLE. The twenty older article images are the shop's own product
photographs on white, laid out side by side with a caption under each. These
match that: the photographs are the ones already in assets/img/products, so
every pixel is the shop's own — no stock library, nothing from a competitor's
catalogue. The drawing on top is only what a photograph cannot say: which hose
goes inside the tank, what a metre scale looks like, where a bore is measured.

820x461 is the size pages/post.php declares, and the article image is also the
og:image, so it is what gets shown when somebody shares the page.
"""
import math
import os
from PIL import Image, ImageDraw, ImageFont

W, H = 820, 461
ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
SRC  = os.path.join(ROOT, 'assets', 'img', 'products')
OUT  = os.path.join(ROOT, 'assets', 'img', 'blog')

INK, STEEL, LINE = (11, 18, 32), (91, 104, 128), (227, 231, 238)
SOFT, BG, BRAND = (244, 246, 250), (255, 255, 255), (195, 69, 21)

F, FB = 'C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/segoeuib.ttf'
def font(s, bold=False):
    return ImageFont.truetype(FB if bold else F, s)
H2, BODY, SMALL, TINY = font(21, True), font(17), font(15), font(13, True)


def canvas():
    im = Image.new('RGB', (W, H), BG)
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, W, 64], fill=SOFT)
    d.line([0, 64, W, 64], fill=LINE, width=1)
    d.rectangle([0, H - 5, W, H], fill=BRAND)
    return im, d


def title(d, text):
    d.text((34, 18), text, font=H2, fill=INK)
    d.text((W - 34, 24), 'ARG FLEX LTD', font=TINY, fill=STEEL, anchor='ra')


def photo(name, box_w, box_h):
    """A product photograph, trimmed of its margin and fitted to a box.

    The threshold is deliberately generous: these were not all shot on the
    same white. The submersible hose sits on a pale blue-grey that reads as a
    panel stuck on the page when it is kept, so anything near-white goes.
    """
    im = Image.open(os.path.join(SRC, name + '.webp')).convert('RGB')
    mask = im.convert('L').point(lambda v: 255 if v < 232 else 0)
    bbox = mask.getbbox()
    if bbox:
        pad = 6
        im = im.crop((max(0, bbox[0] - pad), max(0, bbox[1] - pad),
                      min(im.width, bbox[2] + pad), min(im.height, bbox[3] + pad)))

    # The remaining background is levelled to white so photographs taken on
    # different days sit on one page without seams.
    im.thumbnail((box_w, box_h), Image.LANCZOS)
    return whiten(im)


def whiten(im, cut=228, tint=200, bias=12):
    """Lift a near-white photographic background to true white.

    Two different things are removed. The first is anything already near
    white, which varies a shade between photographs. The second is the pale
    blue cast the submersible hose was shot against — measured at about
    (218, 241, 249), light but distinctly blue, so brightness alone leaves it
    as a rectangle stuck to the page. A pixel that is bright AND noticeably
    bluer than it is red is that cast; the hose itself is far too dark to
    qualify, and a neutral grey has no bias to trip on.
    """
    px = im.load()
    for y in range(im.height):
        for x in range(im.width):
            r, g, b = px[x, y]
            if (r >= cut and g >= cut and b >= cut) \
               or (min(r, g, b) >= tint and b - r >= bias):
                px[x, y] = (255, 255, 255)
    return im


def arrow(d, x1, y1, x2, y2, colour=BRAND, width=3, head=11):
    d.line([x1, y1, x2, y2], fill=colour, width=width)
    a = math.atan2(y2 - y1, x2 - x1)
    for s in (0.42, -0.42):
        d.line([x2, y2, x2 - head * math.cos(a + s), y2 - head * math.sin(a + s)],
               fill=colour, width=width)


def dim(d, x1, x2, y, label):
    d.line([x1, y, x2, y], fill=BRAND, width=2)
    for x in (x1, x2):
        d.line([x, y - 7, x, y + 7], fill=BRAND, width=2)
    tw = d.textlength(label, font=TINY)
    d.rectangle([(x1 + x2) / 2 - tw / 2 - 7, y - 23, (x1 + x2) / 2 + tw / 2 + 7, y - 5], fill=BG)
    d.text(((x1 + x2) / 2, y - 14), label, font=TINY, fill=BRAND, anchor='mm')


# ---------------------------------------------------------------- image one
def r6_vs_r10():
    im, d = canvas()
    title(d, 'SAE J30 R6 and R10 \u2014 where each one belongs')

    mid = W // 2
    d.line([mid, 96, mid, 372], fill=LINE, width=1)

    for x0, name, head, lines in (
        (34, 'oil-resistant-hose-sae-j30-r6-10mm', 'SAE J30 R6',
         ['Tank to engine, oil and return lines', 'NOT to be submerged in fuel',
          '\u221240 \u00b0C to +100 \u00b0C  \u00b7  13 bores in stock']),
        (mid + 34, 'submersible-fuel-hose-sae-j30-r10-0-5m-50m', 'SAE J30 R10',
         ['Inside the tank, around the pump', 'FKM tube, rated for immersion',
          '\u221240 \u00b0C to +125 \u00b0C']),
    ):
        p = photo(name, mid - 92, 170)
        im.paste(p, (x0 + (mid - 68 - p.width) // 2, 96 + (174 - p.height) // 2))
        d.text((x0, 286), head, font=H2, fill=BRAND)
        for i, s in enumerate(lines):
            d.text((x0, 318 + i * 22), '\u2022  ' + s, font=SMALL, fill=INK)

    d.text((W // 2, H - 32), 'One standard, two jobs \u2014 the wrong one in a tank swells, softens and leaks',
           font=SMALL, fill=STEEL, anchor='mm')
    return im


# ---------------------------------------------------------------- image two
def cut_to_length():
    im, d = canvas()
    title(d, 'Hose cut to length, priced by the metre')

    coil = photo('fuel-hose-din-73379-b', 226, 170)
    im.paste(coil, (36 + (226 - coil.width) // 2, 96 + (170 - coil.height) // 2))
    d.text((149, 276), 'the coil we hold', font=SMALL, fill=STEEL, anchor='mm')

    arrow(d, 276, 174, 326, 174)
    d.text((301, 150), 'cut', font=TINY, fill=BRAND, anchor='mm')

    # Kept above the arithmetic: the scale used to run underneath it and the
    # box covered every number on it.
    run = photo('oil-resistant-hose-sae-j30-r6-10mm', 420, 116)
    rx, ry = 352, 100
    im.paste(run, (rx, ry))
    x0, x1, y = rx + 6, rx + run.width - 6, ry + run.height + 14
    d.line([x0, y, x1, y], fill=STEEL, width=2)
    step = (x1 - x0) / 20.0
    for i in range(21):
        x = x0 + i * step
        tall = (i % 5 == 0)
        d.line([x, y, x, y + (12 if tall else 7)], fill=STEEL, width=2 if tall else 1)
        if tall:
            d.text((x, y + 24), str(i), font=TINY, fill=STEEL, anchor='mm')
    d.text((x1, y + 44), 'metres', font=TINY, fill=STEEL, anchor='ra')

    box = [340, 300, 784, 378]
    d.rounded_rectangle(box, radius=10, fill=SOFT, outline=LINE, width=2)
    base = 360
    for x, cap, val, colour in ((362, 'price per metre', '\u00a31.50', INK),
                                (514, 'metres', '20', INK),
                                (636, 'your order', '\u00a330.00', BRAND)):
        d.text((x, 314), cap, font=TINY, fill=STEEL)
        d.text((x, base), val, font=H2, fill=colour, anchor='ls')
    for x, sign in ((484, '\u00d7'), (600, '=')):
        d.text((x, base), sign, font=H2, fill=STEEL, anchor='ms')

    d.text((W // 2, H - 32), 'From 1 m to 50 m \u2014 no cutting charge, and carriage is priced on the metres',
           font=SMALL, fill=STEEL, anchor='mm')
    return im


# -------------------------------------------------------------- image three
def measure_bore():
    im, d = canvas()
    title(d, 'Bore is the hole, not the hose')

    p = photo('oil-resistant-hose-sae-j30-r6-10mm', 392, 236)
    px, py = 34, 102
    im.paste(p, (px, py))

    # The cut ends sit in the upper right of this photograph; the callout
    # points at them rather than trying to measure them, because the exact
    # pixels move the moment the photograph is replaced.
    ex, ey = px + int(p.width * 0.70), py + int(p.height * 0.30)
    d.ellipse([ex - 34, ey - 30, ex + 34, ey + 30], outline=BRAND, width=3)
    # Stops short of the text column; it used to run into the heading.
    arrow(d, ex + 40, ey + 2, 432, ey + 2)
    # Above the ring, on its own white ground: below it the label landed on
    # the hose and dark red on black cannot be read.
    label, ly = 'measure across here', ey - 52
    tw = d.textlength(label, font=TINY)
    d.rectangle([ex - tw / 2 - 8, ly - 11, ex + tw / 2 + 8, ly + 11], fill=BG)
    d.text((ex, ly), label, font=TINY, fill=BRAND, anchor='mm')
    d.line([ex, ly + 12, ex, ey - 31], fill=BRAND, width=2)

    d.text((466, 128), 'The bore sizes the hose', font=H2, fill=BRAND)
    for i, s in enumerate(['Measure across the opening, through',
                           'the centre \u2014 ringed on the photograph.',
                           'It must match the fitting it slides onto.']):
        d.text((466, 164 + i * 24), s, font=BODY, fill=INK)

    d.text((466, 252), 'The outside sizes the clamp', font=H2, fill=BRAND)
    for i, s in enumerate(['Measure where the clamp will sit, with',
                           'the hose on the fitting. Pick a range',
                           'that brackets it rather than ends on it.']):
        d.text((466, 288 + i * 24), s, font=BODY, fill=INK)

    d.rounded_rectangle([466, 368, 786, 414], radius=9, fill=SOFT, outline=LINE, width=2)
    d.text((482, 379), 'Between two sizes? Take the smaller one \u2014', font=SMALL, fill=INK)
    d.text((482, 396), 'hose stretches onto a fitting, never shrinks.', font=SMALL, fill=INK)
    return im


JOBS = [
    ('sae-j30-r6-or-r10-which-fuel-hose-goes-in-the-tank', r6_vs_r10),
    ('buying-hose-cut-to-length-by-the-metre', cut_to_length),
    ('how-to-measure-hose-bore-inner-diameter', measure_bore),
]

if __name__ == '__main__':
    for slug, fn in JOBS:
        img = fn()
        path = os.path.join(OUT, slug + '.webp')
        img.save(path, 'WEBP', quality=88, method=6)
        print('  %-54s %6d bytes  %dx%d' % (slug + '.webp', os.path.getsize(path), *img.size))
