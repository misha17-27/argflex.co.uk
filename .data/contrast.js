/* Colour contrast, measured in a real browser.
 *
 * Open the site, open the console, paste the whole file, press enter. It
 * loads every page listed below into a hidden frame and reports any text
 * that falls short of WCAG AA — 4.5:1 for body text, 3:1 for large.
 *
 * This is not in check_a11y.py because contrast cannot be read off the
 * HTML: the colour that matters is the one the cascade finally computes,
 * and the ground behind it may be three translucent layers and a gradient
 * deep. Both of those are handled here, and both mattered — the banner on
 * the front page was white text on an orange gradient at 2.34:1, and the
 * hero chips looked like failures until the 8%-white layer above the dark
 * section was composited properly.
 *
 * A photographic background returns null and is skipped. No machine can
 * judge text over a photograph, and pretending otherwise fills the report
 * with noise.
 */
(async () => {
  const PAGES = ['/', '/shop/', '/product/acetylene-hose/', '/product-category/rubber-hoses/',
                 '/cart/', '/checkout/', '/blog/', '/contacts/', '/my-account/', '/compare/',
                 '/wishlist/', '/about-us/', '/definitely-not-a-real-page/'];

  const lum = ([r, g, b]) => {
    const f = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4) };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const nums  = c => { const m = String(c).match(/[\d.]+/g); return m ? m.map(Number) : null };
  const over  = (fg, bg) => { const a = fg[3] === undefined ? 1 : fg[3];
                              return [0, 1, 2].map(i => fg[i] * a + bg[i] * (1 - a)) };
  const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05) };

  const audit = win => {
    const gcs = el => win.getComputedStyle(el);

    /* Every colour the text could be sitting on. Translucent layers are
       composited downwards; a gradient contributes each of its stops,
       because the worst stop is the one that decides. */
    const backdrops = el => {
      const layers = [];
      for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
        const s = gcs(n);
        if (s.backgroundImage !== 'none') {
          const stops = s.backgroundImage.match(/rgba?\([^)]+\)/g);
          if (stops) { layers.push(stops.map(nums)); continue }
          return null;                                   // a photograph
        }
        const c = nums(s.backgroundColor);
        if (c && (c[3] === undefined || c[3] > 0)) layers.push([c]);
        if (c && (c[3] === undefined || c[3] >= 1)) break;
      }
      layers.push([[255, 255, 255]]);
      let out = layers.pop().map(c => c.slice(0, 3));
      while (layers.length) {
        const top = layers.pop(), next = [];
        for (const base of out) for (const c of top) next.push(over(c, base));
        out = next.slice(0, 8);
      }
      return out;
    };

    const seen = new Map();
    win.document.querySelectorAll('body *').forEach(el => {
      const s = gcs(el);
      if (s.visibility === 'hidden' || s.opacity === '0' || s.display === 'none') return;
      if (!el.offsetParent && s.position !== 'fixed') return;
      if (el.closest('.sr, .skip, [hidden]')) return;
      if (![...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) return;

      const fg = nums(s.color); if (!fg) return;
      const px = parseFloat(s.fontSize), bold = parseInt(s.fontWeight) >= 700;
      const need = (px >= 24 || (bold && px >= 18.66)) ? 3 : 4.5;

      const grounds = backdrops(el); if (!grounds) return;
      let worst = Infinity, on = null;
      for (const bg of grounds) {
        const r = ratio(over(fg, bg), bg);
        if (r < worst) { worst = r; on = bg }
      }
      if (worst >= need) return;

      const key = s.color + '|' + el.tagName + '|' + (typeof el.className === 'string' ? el.className : '');
      if (!seen.has(key)) seen.set(key, {
        sel: el.tagName.toLowerCase() + (typeof el.className === 'string' && el.className
             ? '.' + el.className.trim().split(/\s+/).join('.') : ''),
        text: el.textContent.trim().slice(0, 30),
        colour: s.color, on: 'rgb(' + on.map(Math.round).join(',') + ')',
        size: px + (bold ? ' bold' : ''), ratio: +worst.toFixed(2), needs: need });
    });
    return [...seen.values()].sort((a, b) => a.ratio - b.ratio);
  };

  const failures = {};
  for (const page of PAGES) {
    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;left:-9999px;width:1280px;height:2400px;border:0';
    frame.src = page;
    document.body.appendChild(frame);
    await new Promise(done => { frame.onload = done; setTimeout(done, 6000) });
    try { const hits = audit(frame.contentWindow); if (hits.length) failures[page] = hits }
    catch (e) { failures[page] = 'could not be read: ' + e.message }
    frame.remove();
  }

  if (!Object.keys(failures).length) {
    console.log(`All ${PAGES.length} pages pass AA for contrast.`);
    return 'pass';
  }
  for (const [page, hits] of Object.entries(failures)) { console.group(page); console.table(hits); console.groupEnd() }
  return failures;
})();
