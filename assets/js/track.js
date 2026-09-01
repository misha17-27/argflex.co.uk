/* ARG FLEX LTD — Google and Meta, loaded only once allowed.
 *
 * Nothing here runs unless the shop has entered an id under Settings ->
 * Tracking, and nothing is fetched from Google or Meta until the visitor has
 * agreed. Before that the banner is the only thing on the page from this file.
 *
 * See inc/tracking.php for why it is strict rather than Consent Mode.
 */
(function () {
  'use strict';

  var CFG = (window.ARGFLEX && window.ARGFLEX.track) || {};
  if (!CFG.needed) return;

  var KEY = 'argflex.consent';          // 'yes' | 'no'
  var queue = (window.ARGFLEX && window.ARGFLEX.trackEvents) || [];
  var loaded = false;

  function stored() {
    try { return localStorage.getItem(KEY) || ''; } catch (e) { return ''; }
  }
  function remember(value) {
    try { localStorage.setItem(KEY, value); } catch (e) {}
  }

  /* ---------------------------------------------------------- the tags */

  function script(src) {
    var s = document.createElement('script');
    s.async = true;
    s.src = src;
    document.head.appendChild(s);
    return s;
  }

  function loadGoogle() {
    if (!CFG.ga4 && !CFG.ads && !CFG.gtm) return;

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };

    if (CFG.gtm) {
      // A container manages its own tags; the shop's ids are for it to use.
      script('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(CFG.gtm));
      return;
    }

    var first = CFG.ga4 || CFG.ads;
    script('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(first));
    window.gtag('js', new Date());
    if (CFG.ga4) window.gtag('config', CFG.ga4);
    if (CFG.ads) window.gtag('config', CFG.ads);
  }

  function loadMeta() {
    if (!CFG.pixel) return;

    /* Meta's own snippet, written out rather than pasted: the pasted version
       is minified to one line and nobody can tell what it does. */
    var f = window;
    if (f.fbq) return;
    var n = f.fbq = function () {
      n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
    };
    if (!f._fbq) f._fbq = n;
    n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
    script('https://connect.facebook.net/en_US/fbevents.js');

    f.fbq('init', CFG.pixel);
    f.fbq('track', 'PageView');
  }

  /* ------------------------------------------------------- the events */

  /* One vocabulary in the templates — Google's — translated here, so no page
     has to know that Meta calls a basket an AddToCart and a checkout an
     InitiateCheckout. */
  var META_NAME = {
    view_item:      'ViewContent',
    add_to_cart:    'AddToCart',
    begin_checkout: 'InitiateCheckout',
    purchase:       'Purchase',
    search:         'Search'
  };

  function toMeta(name, p) {
    var out = {};
    if (p.value !== undefined)    out.value = p.value;
    if (p.currency)               out.currency = p.currency;
    if (p.transaction_id)         out.order_id = p.transaction_id;
    if (p.search_term)            out.search_string = p.search_term;
    if (p.items && p.items.length) {
      out.content_type = 'product';
      out.contents = p.items.map(function (i) {
        return { id: i.item_id, quantity: i.quantity, item_price: i.price };
      });
      out.content_ids = p.items.map(function (i) { return i.item_id; });
      out.content_name = p.items[0].item_name;
    }
    return out;
  }

  function send(name, params) {
    if (!loaded) return;                 // nothing before consent, and nothing replayed
    params = params || {};
    if (!params.currency && CFG.currency) params.currency = CFG.currency;

    if (window.gtag && (CFG.ga4 || CFG.gtm)) window.gtag('event', name, params);

    // A purchase is also the Ads conversion, when one is configured.
    if (name === 'purchase' && window.gtag && CFG.ads && CFG.adsLabel) {
      window.gtag('event', 'conversion', {
        send_to: CFG.ads + '/' + CFG.adsLabel,
        value: params.value,
        currency: params.currency,
        transaction_id: params.transaction_id
      });
    }

    if (window.fbq && META_NAME[name]) window.fbq('track', META_NAME[name], toMeta(name, params));
  }

  // The rest of the site reports what happened; it does not know who is listening.
  window.argflexTrack = function (name, params) { send(name, params); };

  function start() {
    if (loaded) return;
    loaded = true;
    loadGoogle();
    loadMeta();
    queue.forEach(function (e) { send(e.name, e.params); });
    queue.length = 0;
  }

  /* ------------------------------------------------------- the banner */

  function banner() {
    var box = document.createElement('div');
    box.className = 'consent';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Cookies');
    box.innerHTML =
      '<p>We would like to use Google Analytics and the Meta pixel to see which '
      + 'pages and products people actually use. They set cookies, so only if you '
      + 'say yes. Saying no changes nothing about the shop.</p>'
      + '<div class="consent-acts">'
      + '<button type="button" data-consent="yes" class="btn btn-primary">Allow</button>'
      + '<button type="button" data-consent="no" class="btn btn-out">No thanks</button>'
      + '</div>';
    document.body.appendChild(box);

    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-consent]');
      if (!b) return;
      remember(b.dataset.consent);
      box.remove();
      if (b.dataset.consent === 'yes') start();
    });
  }

  /* Re-openable, because a decision you cannot revisit is not a choice.
     Any link to #cookies asks again. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href="#cookies"], [data-consent-reopen]');
    if (!a) return;
    e.preventDefault();
    remember('');
    if (!document.querySelector('.consent')) banner();
  });

  var said = stored();
  if (!CFG.ask || said === 'yes') start();
  else if (said !== 'no') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', banner);
    } else {
      banner();
    }
  }
})();
