/**
 * Responsive sweep. Paste into the browser console on http://localhost:8124/
 * then read window.__rc when it finishes.
 *
 * Loads every page in a fixed-width iframe and reports any that push the
 * document wider than the viewport, naming the offending elements.
 *
 * Every template is checked at seven widths; every remaining URL is checked
 * at 320px, the width that breaks first.
 */
(async () => {
  const WIDTHS = [320, 360, 390, 768, 900, 1024, 1280];

  const TEMPLATES = [
    '/', '/shop/', '/shop/?q=oxygen', '/shop/?q=zzzz', '/shop/?cat=rubber-hoses',
    '/product-category/rubber-hoses/', '/product-category/rubber-hoses/water/',
    '/product-category/pvcpu-hoses/ventilation/',
    '/product/acetylene-hose/', '/product/nts-garden-hose/',
    '/product/propane-butane-lpg-hose/', '/product/submersible-fuel-hose-sae-j30-r10-0-5m-50m/',
    '/blog/', '/industrial-hose-types-and-applications/',
    '/asfa-clamps-high-performance-hose-clamps-for-demanding-applications/',
    '/about-us/', '/contacts/', '/cart/', '/checkout/', '/wishlist/', '/compare/',
    '/compare/?a=oxygen-hose-agoma&b=acetylene-hose', '/my-account/',
    '/refund_returns/', '/definitely-not-real/',
  ];

  // deliberately clipped: marquee, off-canvas panels, carousels, scrollers
  const ALLOWED = ['.tick .row', '.tick i', '.drawer', '.mini', '.rail',
                   '.nav .wrap', '.bar .wrap', '.table-scroll'];

  const xml  = await (await fetch('/sitemap.xml')).text();
  const urls = [...xml.matchAll(/<loc>https:\/\/argflex\.co\.uk([^<]*)<\/loc>/g)].map(m => m[1]);
  const rest = urls.concat(['/cart/', '/checkout/', '/wishlist/', '/compare/', '/my-account/'])
                   .filter(u => !TEMPLATES.includes(u));

  const plan = [];
  WIDTHS.forEach(w => TEMPLATES.forEach(u => plan.push([w, u])));
  rest.forEach(u => plan.push([320, u]));

  window.__rc = { running: true, done: 0, total: plan.length, bad: [] };

  const frame = document.createElement('iframe');
  frame.style.cssText = 'position:fixed;left:-9999px;top:0;border:0;height:820px';
  document.body.appendChild(frame);

  let lastWidth = null;
  for (const [width, url] of plan) {
    if (width !== lastWidth) { frame.style.width = width + 'px'; lastWidth = width; }
    await new Promise(done => { frame.onload = () => setTimeout(done, 20); frame.src = url; });
    try {
      const doc = frame.contentDocument;
      const vw  = doc.documentElement.clientWidth;
      const overflow = doc.documentElement.scrollWidth - vw;
      if (overflow > 1) {
        const offenders = [...doc.querySelectorAll('body *')].filter(el => {
          const r = el.getBoundingClientRect();
          if (r.width < 1 || r.right <= vw + 1) return false;
          return !ALLOWED.some(sel => el.matches(sel) || el.closest(sel));
        }).slice(0, 3).map(el => el.tagName + '.' + (el.className || '').toString().split(/\s+/)[0]);
        window.__rc.bad.push({ width, url, overflow, offenders });
      }
    } catch (e) {
      window.__rc.bad.push({ width, url, error: String(e) });
    }
    window.__rc.done++;
  }

  frame.remove();
  window.__rc.running = false;
  console.log(`responsive: ${window.__rc.total} checks, ${window.__rc.bad.length} problems`, window.__rc.bad);
})();
