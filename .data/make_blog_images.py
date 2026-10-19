"""Draw the three article images for argflex.co.uk.

Diagrams, not photographs. For "how to measure a bore" a cross-section with the
two diameters marked is worth more than any photograph of a hose, and drawing
them keeps every pixel the shop's own — no stock library, nothing lifted from
a competitor.

Palette and proportions come from assets/css/v1.css so they sit beside the
site rather than on top of it. 820x461 is what pages/post.php declares.
"""
import math
import os
from PIL import Image, ImageDraw, ImageFont

W, H = 820, 461
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'assets', 'img', 'blog')
os.makedirs(OUT, exist_ok=True)

INK   = (11, 18, 32)
STEEL = (91, 104, 128)
LINE  = (227, 231, 238)
SOFT  = (244, 246, 250)
BG    = (255, 255, 255)
BRAND = (195, 69, 21)
BRIGHT = (255, 90, 31)
RUBBER = (38, 44, 56)
RUBBER_L = (64, 72, 88)

F = 'C:/Windows/Fonts/segoeui.ttf'
FB = 'C:/Windows/Fonts/segoeuib.ttf'
def font(size, bold=False):
    return ImageFont.truetype(FB if bold else F, size)

H1, H2, BODY, SMALL, TINY = font(30, True), font(21, True), font(17), font(15), font(13, True)


def canvas():
    im = Image.new('RGB', (W, H), BG)
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, W, 64], fill=SOFT)
    d.line([0, 64, W, 64], fill=LINE, width=1)
    d.rectangle([0, H - 5, W, H], fill=BRAND)
    return im, d


def title(d, text, sub=None):
    d.text((34, 18), text, font=H2, fill=INK)
    if sub:
        d.text((W - 34, 24), sub, font=TINY, fill=STEEL, anchor='ra')


def arrow(d, x1, y1, x2, y2, colour=BRAND, width=3, head=11):
    d.line([x1, y1, x2, y2], fill=colour, width=width)
    a = math.atan2(y2 - y1, x2 - x1)
    for s in (0.42, -0.42):
        d.line([x2, y2, x2 - head * math.cos(a + s), y2 - head * math.sin(a + s)],
               fill=colour, width=width)


def dim(d, x1, x2, y, label, colour=BRAND):
    """A dimension line with ticks at both ends and a label above it."""
    d.line([x1, y, x2, y], fill=colour, width=2)
    for x in (x1, x2):
        d.line([x, y - 7, x, y + 7], fill=colour, width=2)
    for s in (1, -1):
        d.line([x1 if s > 0 else x2, y, (x1 + 9 * s) if s > 0 else (x2 - 9), y - 5], fill=colour, width=2)
    tw = d.textlength(label, font=TINY)
    d.rectangle([(x1 + x2) / 2 - tw / 2 - 7, y - 24, (x1 + x2) / 2 + tw / 2 + 7, y - 6], fill=BG)
    d.text(((x1 + x2) / 2, y - 15), label, font=TINY, fill=colour, anchor='mm')


def hose_run(d, pts, outer=17, inner=8, colour=RUBBER):
    """A length of hose drawn as a thick dark line with a lighter core."""
    d.line(pts, fill=colour, width=outer, joint='curve')
    d.line(pts, fill=RUBBER_L, width=inner, joint='curve')


# ---------------------------------------------------------------- image one
def r6_vs_r10():
    im, d = canvas()
    title(d, 'SAE J30 R6 and R10 — where each one belongs', 'ARG FLEX LTD')

    mid = W // 2
    d.line([mid, 92, mid, H - 70], fill=LINE, width=2)

    # ---- left: the tank, with R10 inside it
    d.text((34, 86), 'INSIDE THE TANK', font=TINY, fill=STEEL)
    tank = [44, 128, mid - 46, 300]
    d.rounded_rectangle(tank, radius=18, fill=SOFT, outline=STEEL, width=2)
    d.rectangle([tank[0] + 10, 206, tank[2] - 10, tank[3] - 12], fill=(206, 221, 238))
    d.text((tank[0] + 18, 212), 'fuel', font=SMALL, fill=(70, 96, 128))
    # pump body and the submersible hose looping in the fuel
    d.rounded_rectangle([mid - 150, 150, mid - 116, 214], radius=6, fill=STEEL)
    hose_run(d, [(mid - 133, 214), (mid - 133, 254), (118, 254), (118, 224)])
    d.ellipse([110, 214, 126, 230], outline=RUBBER, width=4)
    d.text((44, 312), 'SAE J30 R10', font=H2, fill=BRAND)
    for i, s in enumerate(['FKM tube, thermoplastic lining',
                           'rated for constant immersion',
                           '\u221240 \u00b0C to +125 \u00b0C']):
        d.text((44, 344 + i * 23), '\u2022  ' + s, font=SMALL, fill=INK)

    # ---- right: the engine run, with R6
    d.text((mid + 34, 86), 'TANK TO ENGINE', font=TINY, fill=STEEL)
    d.rounded_rectangle([W - 190, 150, W - 62, 258], radius=10, fill=SOFT, outline=STEEL, width=2)
    for i in range(4):
        d.rounded_rectangle([W - 176 + i * 29, 132, W - 158 + i * 29, 152], radius=4, fill=STEEL)
    d.text((W - 180, 196), 'engine', font=SMALL, fill=STEEL)
    hose_run(d, [(mid + 44, 300), (mid + 44, 222), (W - 190, 222)])
    d.text((mid + 34, 312), 'SAE J30 R6', font=H2, fill=BRAND)
    for i, s in enumerate(['synthetic rubber, oil resistant',
                           'NOT to be submerged in fuel',
                           '\u221240 \u00b0C to +100 \u00b0C']):
        d.text((mid + 34, 344 + i * 23), '\u2022  ' + s, font=SMALL, fill=INK)

    d.text((W // 2, H - 34), 'One standard, two jobs — the wrong one in a tank swells, softens and leaks',
           font=SMALL, fill=STEEL, anchor='mm')
    return im


# ---------------------------------------------------------------- image two
def cut_to_length():
    im, d = canvas()
    title(d, 'Hose cut to length, priced by the metre', 'ARG FLEX LTD')

    # A coil of hose: even turns, plus a short tail running off it so the
    # shape reads as hose rather than as a target.
    cx, cy = 150, 206
    for r in (82, 62, 42, 22):
        d.ellipse([cx - r, cy - r * 0.80, cx + r, cy + r * 0.80], outline=RUBBER, width=14)
        d.arc([cx - r, cy - r * 0.80, cx + r, cy + r * 0.80],
              start=185, end=355, fill=RUBBER_L, width=5)
    hose_run(d, [(cx + 78, cy + 22), (cx + 104, cy + 52), (cx + 96, cy + 86)], outer=14, inner=6)
    d.text((cx, 318), 'the coil we hold', font=SMALL, fill=STEEL, anchor='mm')

    arrow(d, 252, 206, 318, 206)
    d.text((285, 182), 'cut', font=TINY, fill=BRAND, anchor='mm')

    # the measured run
    x0, x1, y = 340, 772, 206
    hose_run(d, [(x0, y), (x1, y)], outer=19, inner=9)
    step = (x1 - x0) / 20.0
    for i in range(21):
        x = x0 + i * step
        tall = (i % 5 == 0)
        d.line([x, y + 16, x, y + (30 if tall else 23)], fill=STEEL, width=2 if tall else 1)
        if tall:
            d.text((x, y + 40), str(i), font=TINY, fill=STEEL, anchor='mm')
    d.text((x1, y + 60), 'metres', font=TINY, fill=STEEL, anchor='ra')
    dim(d, x0, x1, y - 40, 'the run you asked for')

    # the arithmetic
    box = [340, 296, 772, 376]
    d.rounded_rectangle(box, radius=10, fill=SOFT, outline=LINE, width=2)
    # One baseline for the figures and the operators between them, so the sum
    # reads as a line of arithmetic rather than three stacked labels.
    base = 358
    for x, cap, val, colour in ((360, 'price per metre', '\u00a31.50', INK),
                                (508, 'metres', '20', INK),
                                (628, 'your order', '\u00a330.00', BRAND)):
        d.text((x, 312), cap, font=TINY, fill=STEEL)
        d.text((x, base), val, font=H2, fill=colour, anchor='ls')
    for x, sign in ((478, '\u00d7'), (592, '=')):
        d.text((x, base), sign, font=H2, fill=STEEL, anchor='ms')

    d.text((W // 2, H - 34), 'From 1 m to 50 m \u2014 no cutting charge, and carriage is priced on the metres',
           font=SMALL, fill=STEEL, anchor='mm')
    return im


# -------------------------------------------------------------- image three
def measure_bore():
    im, d = canvas()
    title(d, 'Bore is the hole, not the hose', 'ARG FLEX LTD')

    cx, cy = 252, 252
    ro, ri = 118, 70
    d.ellipse([cx - ro, cy - ro, cx + ro, cy + ro], fill=RUBBER)
    d.ellipse([cx - ro + 13, cy - ro + 13, cx + ro - 13, cy + ro - 13], fill=RUBBER_L)
    for a in range(0, 360, 12):                      # the cord reinforcement
        r1, r2 = ri + 16, ro - 16
        d.line([cx + r1 * math.cos(math.radians(a)), cy + r1 * math.sin(math.radians(a)),
                cx + r2 * math.cos(math.radians(a + 7)), cy + r2 * math.sin(math.radians(a + 7))],
               fill=(104, 114, 132), width=2)
    d.ellipse([cx - ri, cy - ri, cx + ri, cy + ri], fill=BG, outline=(150, 158, 172), width=2)

    dim(d, cx - ri, cx + ri, cy + 2, 'BORE')
    dim(d, cx - ro, cx + ro, cy + ro + 48, 'OUTER DIAMETER')

    # what each number is for
    d.text((466, 128), 'The bore sizes the hose', font=H2, fill=BRAND)
    for i, s in enumerate(['Measure across the opening, through',
                           'the centre. It has to match the outside',
                           'of the fitting it slides onto.']):
        d.text((466, 164 + i * 24), s, font=BODY, fill=INK)

    d.text((466, 256), 'The outside sizes the clamp', font=H2, fill=BRAND)
    for i, s in enumerate(['Measure where the clamp will sit, with',
                           'the hose on the fitting. Pick a range that',
                           'brackets it rather than ends on it.']):
        d.text((466, 292 + i * 24), s, font=BODY, fill=INK)

    d.rounded_rectangle([466, 372, 786, 416], radius=9, fill=SOFT, outline=LINE, width=2)
    d.text((482, 384), 'Between two sizes? Take the smaller one \u2014',
           font=SMALL, fill=INK)
    d.text((482, 400), 'hose stretches onto a fitting, never shrinks.',
           font=SMALL, fill=INK)
    return im


JOBS = [
    ('sae-j30-r6-or-r10-which-fuel-hose-goes-in-the-tank', r6_vs_r10),
    ('buying-hose-cut-to-length-by-the-metre', cut_to_length),
    ('how-to-measure-hose-bore-inner-diameter', measure_bore),
]

for slug, fn in JOBS:
    im = fn()
    path = os.path.join(OUT, slug + '.webp')
    im.save(path, 'WEBP', quality=88, method=6)
    print('  %-54s %6d bytes  %dx%d' % (slug + '.webp', os.path.getsize(path), *im.size))
