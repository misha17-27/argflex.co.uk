/* ARG FLEX LTD — site behaviour. No dependencies. */
(function () {
  'use strict';

  /* Currency, tax and delivery all come from the admin panel; the defaults
     here only matter if the config block failed to render. */
  var CFG  = window.ARGFLEX || {};
  var CUR  = CFG.currency || { sym: '\u00a3', pos: 'left', dec: 2, dsep: '.', tsep: ',' };
  var TAX  = CFG.tax      || { on: true, rate: 20 };
  var ZONES   = CFG.zones || [];
  var country = CFG.country || 'GB';

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var set = function (s, text) { var el = $(s); if (el) el.textContent = text; };

  var money = function (pence) {
    var parts = (pence / 100).toFixed(CUR.dec).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, CUR.tsep);
    var n = parts.join(CUR.dsep);
    if (CUR.pos === 'right')       return n + CUR.sym;
    if (CUR.pos === 'left_space')  return CUR.sym + '\u00a0' + n;
    if (CUR.pos === 'right_space') return n + '\u00a0' + CUR.sym;
    return CUR.sym + n;
  };

  /* ------------------------------------------------------------ delivery

     Worked out by the server, not here. Carriage on this shop depends on the
     metres in the basket, one basket can split into two consignments charged
     separately, and four rules decide which of eight rates each may use. A
     second copy of that in JavaScript would drift from the one in PHP, and
     the copy the customer sees would be the wrong one. So this asks
     /delivery-quote.php and draws the answer.

     The last answer is kept against a signature of what was asked, so
     repainting after an unrelated change costs nothing. */

  var esc = function (s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  };

  var delivery = { sig: null, data: null, busy: false };

  var shipPicks = function () {
    var out = [];
    $$('[data-ship-pick]').forEach(function (input) {
      if (input.checked) out[+input.dataset.package] = +input.value;
    });
    return out;
  };

  var deliveryAsk = function () {
    return {
      cart: store.read('cart').map(function (i) {
        return { slug: i.slug, option: i.option || '', qty: i.qty };
      }),
      country: country,
      coupon: couponState.data ? couponState.data.code : '',
      ship: shipPicks()
    };
  };

  function refreshDelivery(done) {
    var ask = deliveryAsk();
    var sig = JSON.stringify(ask);

    if (sig === delivery.sig) { done && done(delivery.data); return; }
    if (!ask.cart.length) {
      delivery.sig = sig;
      delivery.data = null;
      done && done(null);
      return;
    }
    if (delivery.busy) return;

    delivery.busy = true;
    fetch('/delivery-quote.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(ask)
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        delivery.sig = sig;
        delivery.data = data;
        delivery.busy = false;
        done && done(data);
      })
      .catch(function () {
        delivery.busy = false;
        // leave the last good answer on screen rather than replacing a real
        // figure with a wrong one
        done && done(delivery.data);
      });
  }

  /* What to put in the delivery row. */
  var shipText = function (data) {
    if (!data) return '—';
    if (!data.deliverable) return 'Unavailable';
    if (data.free) return 'Free';
    return money(data.shipping);
  };

  /* The consignments, and the rates each can be sent by.

     One basket can become two, and each is charged on its own. The headings
     are the shop's own — they say "1-2 days" even where the rates inside
     include the slower option, and they space the bracket differently from
     the rate titles beside them. Both are copied as they are. */
  var drawnChoice = null;

  function renderDeliveryChoice(data) {
    var box = $('[data-ship-choice]');
    if (!box) return;

    if (!data) { box.innerHTML = ''; box.hidden = true; drawnChoice = null; return; }

    var html = '<h2 class="ship-h">Delivery</h2>';

    // Nowhere to send it: say so, rather than showing an empty box.
    if (!data.deliverable) {
      box.hidden = false;
      box.innerHTML = html + '<p class="ship-no">' + esc(data.why) + '</p>';
      drawnChoice = 'undeliverable:' + data.why;
      return;
    }
    if (!data.packages.length) { box.innerHTML = ''; box.hidden = true; drawnChoice = null; return; }

    // Redraw only when the consignments themselves changed. Rebuilding the
    // radios every time one is picked throws away the element the customer
    // just clicked — which loses keyboard focus mid-choice, and made the
    // second consignment silently keep its old rate.
    var shape = JSON.stringify(data.packages.map(function (pkg) {
      return [pkg.name, pkg.rates.map(function (r) { return r.id; })];
    }));
    if (shape === drawnChoice) return;
    drawnChoice = shape;

    box.hidden = false;
    var many = data.packages.length > 1;

    data.packages.forEach(function (pkg, i) {
      html += '<fieldset class="ship-pkg">';
      html += '<legend>' + (many ? esc(pkg.name) : 'How it travels') + '</legend>';
      pkg.rates.forEach(function (rate) {
        var id = 'ship-' + i + '-' + rate.id;
        html += '<label class="ship-opt" for="' + id + '">'
              + '<input type="radio" id="' + id + '" name="ship[' + i + ']" value="' + rate.id + '"'
              + ' data-ship-pick data-package="' + i + '"'
              + (rate.id === pkg.chosen ? ' checked' : '') + '>'
              + '<span class="ship-name">' + esc(rate.title) + '</span>'
              + '<b class="ship-cost">' + money(rate.cost) + '</b>'
              + '</label>';
      });
      html += '</fieldset>';
    });

    box.innerHTML = html;
  }

  /* ---------------------------------------------------------- storage */
  var store = {
    read: function (key) {
      try { return JSON.parse(localStorage.getItem('argflex.' + key) || '[]'); }
      catch (e) { return []; }
    },
    write: function (key, val) {
      try { localStorage.setItem('argflex.' + key, JSON.stringify(val)); } catch (e) {}
      badges();
    }
  };

  function badges() {
    var cart = store.read('cart');
    var count = cart.reduce(function (n, i) { return n + (i.qty || 1); }, 0);
    $$('[data-count="cart"]').forEach(function (b) { b.textContent = count; b.hidden = false; });
    var wish = store.read('wishlist');
    $$('[data-count="wishlist"]').forEach(function (b) { b.textContent = wish.length; b.hidden = wish.length === 0; });
  }

  /* ------------------------------------------------------------ toast */
  var toastEl;
  function toast(message, linkHtml) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      document.body.appendChild(toastEl);
    }
    toastEl.innerHTML =
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M20 6L9 17l-5-5"/></svg>' +
      '<span>' + message + (linkHtml ? ' ' + linkHtml : '') + '</span>';
    toastEl.classList.add('on');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toastEl.classList.remove('on'); }, 3600);
  }

  /* ----------------------------------------------------------- drawer */
  var drawer = $('#dr');
  if (drawer) {
    var burger = $('.burger');
    var open = function (state) {
      drawer.classList.toggle('open', state);
      if (burger) burger.setAttribute('aria-expanded', String(state));
      document.body.style.overflow = state ? 'hidden' : '';
    };
    if (burger) burger.addEventListener('click', function () { open(true); });
    $$('[data-close]', drawer).forEach(function (el) {
      el.addEventListener('click', function () { open(false); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('open')) open(false);
    });
  }

  /* -------------------------------------------------- product filters */
  $$('[data-filter-for]').forEach(function (bar) {
    var grid = $(bar.dataset.filterFor);
    if (!grid) return;
    bar.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn) return;
      $$('button', bar).forEach(function (b) { b.classList.toggle('on', b === btn); });
      var f = btn.dataset.f;
      $$('.card', grid).forEach(function (card) {
        var cats = (card.dataset.cats || '').split(' ');
        card.style.display = (f === 'all' || cats.indexOf(f) !== -1) ? '' : 'none';
      });
    });
  });

  /* --------------------------------------------------------- counters */
  (function () {
    var els = $$('[data-to]');
    if (!els.length || !('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        io.unobserve(entry.target);
        var el = entry.target, to = +el.dataset.to, start = performance.now();
        (function step(now) {
          var p = Math.min((now - start) / 1100, 1);
          el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
          if (p < 1) requestAnimationFrame(step);
        })(start);
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  })();

  /* ---------------------------------------------------- product media */
  $$('.p-thumbs button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var main = $('#p-img');
      if (!main) return;
      main.src = btn.dataset.src;
      $$('.p-thumbs button').forEach(function (b) { b.classList.toggle('on', b === btn); });
    });
  });

  /* ------------------------------------------------------------ tabs */
  var tabsNav = $('.tabs-nav');
  if (tabsNav) {
    tabsNav.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn) return;
      $$('button', tabsNav).forEach(function (b) { b.classList.toggle('on', b === btn); });
      $$('.tab-panel').forEach(function (p) { p.classList.toggle('on', p.dataset.panel === btn.dataset.tab); });
    });
  }

  /* ------------------------------------------------------- quantities */
  $$('.qty').forEach(function (box) {
    var input = $('input', box);
    $$('button', box).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = (parseInt(input.value, 10) || 1) + parseInt(btn.dataset.step, 10);
        input.value = Math.max(1, Math.min(999, next));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  /* ------------------------------------------------- variant + total */
  $$('[data-buy]').forEach(function (form) {
    var qty     = $('input[name=qty]', form);
    var total   = $('.p-total', form);
    var addBtn  = $('[data-add-to-cart]', form);
    var rows    = $$('.sw-row', form);
    var clearBt = $('[data-clear]', form);
    var none    = $('.sw-none', form);
    var priceEl = $('.p-price b');
    var basePrice = priceEl ? priceEl.textContent : '';

    var variants = {};
    try { variants = JSON.parse(form.dataset.variants || '{}'); } catch (e) {}

    /** Values picked so far, one per attribute row, in row order. */
    function picked() {
      return rows.map(function (row) {
        var on = $('.sw.on', row);
        return on ? on.dataset.value : '';
      });
    }

    /** Grey out options that cannot complete a stocked combination. */
    function markAvailability() {
      var current = picked();
      rows.forEach(function (row, i) {
        $$('.sw', row).forEach(function (btn) {
          var trial = current.slice();
          trial[i] = btn.dataset.value;
          var possible = Object.keys(variants).some(function (key) {
            var parts = key.split('|');
            return trial.every(function (v, j) { return !v || v === parts[j]; });
          });
          btn.classList.toggle('out', !possible);
        });
      });
    }

    function update() {
      var values = picked();
      var complete = values.every(Boolean);
      var match = complete ? variants[values.join('|')] : null;

      if (clearBt) clearBt.hidden = !values.some(Boolean) || rows.length === 0;
      if (none) none.hidden = !(complete && !match);

      if (addBtn) {
        if (match) {
          addBtn.dataset.price = match.price;
          addBtn.dataset.option = match.label;
        } else {
          delete addBtn.dataset.price;
          delete addBtn.dataset.option;
        }
      }
      if (priceEl) {
        if (match && match.was) {
          priceEl.innerHTML = '<s>' + money(match.was) + '</s> ' + money(match.price);
        } else {
          priceEl.textContent = match ? money(match.price) : basePrice;
        }
      }
      if (total) {
        var n = parseInt(qty && qty.value, 10) || 1;
        var unit = match ? match.price : (addBtn && !rows.length ? +addBtn.dataset.price || 0 : 0);
        if (!unit) { total.hidden = true; }
        else { total.hidden = false; $('b', total).textContent = money(unit * n); }
      }
      markAvailability();
    }

    rows.forEach(function (row) {
      row.addEventListener('click', function (e) {
        var btn = e.target.closest('.sw');
        if (!btn || btn.classList.contains('out')) return;
        var wasOn = btn.classList.contains('on');
        $$('.sw', row).forEach(function (b) {
          b.classList.remove('on');
          b.setAttribute('aria-pressed', 'false');
        });
        if (!wasOn) { btn.classList.add('on'); btn.setAttribute('aria-pressed', 'true'); }
        update();
      });
    });

    if (clearBt) clearBt.addEventListener('click', function () {
      rows.forEach(function (row) {
        $$('.sw', row).forEach(function (b) {
          b.classList.remove('on');
          b.setAttribute('aria-pressed', 'false');
        });
      });
      update();
    });

    if (qty) qty.addEventListener('change', update);
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    update();
  });

  /* ------------------------------------------------------ add to cart */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-add-to-cart]');
    if (!btn) return;
    e.preventDefault();

    var form  = btn.closest('[data-buy]');
    var rows  = form ? $$('.sw-row', form) : [];
    var qtyEl = form ? $('input[name=qty]', form) : null;

    if (rows.length && !btn.dataset.price) {
      var missing = rows.filter(function (r) { return !$('.sw.on', r); })[0];
      if (missing) {
        missing.scrollIntoView({ block: 'center', behavior: 'smooth' });
        toast('Choose ' + $('.sw-label', missing).textContent.replace(/\s*:\s*$/, '') + ' first');
      } else {
        toast('That combination is not stocked');
      }
      return;
    }

    var option = btn.dataset.option || '';
    var price  = +btn.dataset.price || 0;
    var qty    = qtyEl ? (parseInt(qtyEl.value, 10) || 1) : 1;
    var max    = +btn.dataset.max || 0;      // 0 means no limit
    var key    = btn.dataset.slug + '|' + option;

    var cart = store.read('cart');
    var line = cart.filter(function (i) { return i.key === key; })[0];
    if (max > 0 && (line ? line.qty : 0) + qty > max) {
      qty = max - (line ? line.qty : 0);
      if (qty <= 0) {
        toast(max === 1 ? 'One of these per order' : 'Only ' + max + ' available');
        return;
      }
      toast('Only ' + max + ' available - added what we can');
    }
    if (line) {
      line.qty += qty;
    } else {
      cart.push({
        key: key, slug: btn.dataset.slug, title: btn.dataset.title,
        option: option, price: price, qty: qty,
        image: btn.dataset.image || ''
      });
    }
    store.write('cart', cart);
    renderCart();
    renderCheckout();
    openMini('Added to cart');
  });

  /* -------------------------------------------------------- mini cart */
  var mini = $('#mini');

  function renderMini() {
    if (!mini) return;
    var cart = store.read('cart');
    var rows = $('[data-mini-rows]', mini);
    var none = $('[data-mini-none]', mini);
    var foot = $('.mini-ft', mini);

    none.hidden = cart.length > 0;
    foot.hidden = cart.length === 0;

    rows.innerHTML = cart.map(function (i) {
      return '<li data-key="' + i.key.replace(/"/g, '&quot;') + '">' +
        (i.image ? '<img src="' + i.image + '" alt="">' : '<span class="ph"></span>') +
        '<div><a href="/product/' + i.slug + '/">' + i.title + '</a>' +
          (i.option ? '<span class="opt">' + i.option + '</span>' : '') +
          '<span class="qty-line">' + i.qty + ' × ' + money(i.price) + '</span></div>' +
        '<b>' + money(i.price * i.qty) + '</b>' +
        '<button type="button" class="mini-rm" data-mini-remove aria-label="Remove">&times;</button>' +
      '</li>';
    }).join('');

    var subtotal = cart.reduce(function (n, i) { return n + i.price * i.qty; }, 0);
    $('[data-mini-subtotal]', mini).textContent = money(subtotal);
  }

  function openMini(title) {
    if (!mini) return;
    renderMini();
    if (title) $('[data-mini-title]', mini).textContent = title;
    mini.hidden = false;
    void mini.offsetWidth;          // force a reflow so the transition runs
    mini.classList.add('on');       // synchronous: works in background tabs too
    document.body.style.overflow = 'hidden';
    var close = $('.mini-x', mini);
    if (close) close.focus();
  }

  function closeMini() {
    if (!mini) return;
    mini.classList.remove('on');
    document.body.style.overflow = '';
    setTimeout(function () { if (!mini.classList.contains('on')) mini.hidden = true; }, 240);
  }

  if (mini) {
    $$('[data-mini-close]', mini).forEach(function (el) {
      el.addEventListener('click', closeMini);
    });
    mini.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-mini-remove]');
      if (!rm) return;
      var key = rm.closest('li').dataset.key;
      store.write('cart', store.read('cart').filter(function (i) { return i.key !== key; }));
      renderMini();
      renderCart();
      renderCheckout();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !mini.hidden) closeMini();
    });
    // the cart icon opens the panel; the href stays as the no-JS fallback
    $$('a[href="/cart/"].icon-btn').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        openMini('Your cart');
      });
    });
  }

  /* ------------------------------------------------------- wishlist */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-wishlist]');
    if (!btn) return;
    var slug = btn.dataset.slug;
    var list = store.read('wishlist');
    var idx  = list.indexOf(slug);
    if (idx === -1) { list.push(slug); toast('Saved to your wishlist.', '<a href="/wishlist/">View wishlist</a>'); }
    else { list.splice(idx, 1); toast('Removed from your wishlist.'); }
    store.write('wishlist', list);
    btn.classList.toggle('on', idx === -1);
    var label = $('span', btn);
    if (label) label.textContent = idx === -1 ? 'Saved to wishlist' : 'Add to wishlist';
  });

  function markWishlist() {
    var list = store.read('wishlist');
    $$('[data-wishlist]').forEach(function (btn) {
      var on = list.indexOf(btn.dataset.slug) !== -1;
      btn.classList.toggle('on', on);
      var label = $('span', btn);
      if (label) label.textContent = on ? 'Saved to wishlist' : 'Add to wishlist';
    });
  }

  /* ------------------------------------------------------ cart page */
  /* ------------------------------------------------------------ coupons */

  /* Only the code is kept in the browser. What it is worth is asked of the
     server every time the basket changes, and the checkout checks it again
     before an order is stored, so nothing here can be talked into a bigger
     discount than the goods are worth. */
  var couponState = { code: '', sig: '', data: null, busy: false };

  function storedCode() {
    try { return localStorage.getItem('argflex.coupon') || ''; } catch (e) { return ''; }
  }
  function storeCode(code) {
    try {
      if (code) localStorage.setItem('argflex.coupon', code);
      else localStorage.removeItem('argflex.coupon');
    } catch (e) {}
  }
  function basketSig() {
    return store.read('cart').map(function (i) { return i.key + 'x' + i.qty; }).join('|');
  }
  function discount()   { return couponState.data ? couponState.data.discount : 0; }
  function freeShip()   { return !!(couponState.data && couponState.data.free_shipping); }

  function askServer(code, done) {
    var body = 'code=' + encodeURIComponent(code)
             + '&cart=' + encodeURIComponent(JSON.stringify(store.read('cart').map(function (i) {
                 return { slug: i.slug, option: i.option, qty: i.qty };
               })));
    fetch('/coupon-check', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    })
      .then(function (r) { return r.json(); })
      .then(done)
      .catch(function () { done({ ok: false, error: 'Could not check that code just now.' }); });
  }

  function showCoupon(res) {
    var msg = $('[data-coupon-msg]');
    var on  = $('[data-coupon-on]');
    if (msg) {
      msg.hidden = !res || res.ok || !res.error;
      msg.textContent = res && res.error ? res.error : '';
    }
    if (on) {
      on.hidden = !(res && res.ok);
      if (res && res.ok) {
        set('[data-coupon-code]', res.code);
        set('[data-coupon-title]', res.title);
      }
    }
    var input = $('[data-coupon] input[name=code]');
    if (input && res && res.ok) input.value = '';
    var field = $('[data-coupon-field]');
    if (field) field.value = res && res.ok ? res.code : '';
  }

  /* Re-check the stored code whenever the basket has moved on. */
  function syncCoupon() {
    var code = storedCode();
    if (!code) {
      if (couponState.data) { couponState.data = null; couponState.code = ''; paintTotals(); }
      showCoupon(null);
      return;
    }
    var sig = basketSig();
    if (couponState.busy || (couponState.code === code && couponState.sig === sig)) return;

    couponState.busy = true;
    askServer(code, function (res) {
      couponState.busy = false;
      couponState.code = code;
      couponState.sig  = sig;
      couponState.data = res.ok ? res : null;
      if (!res.ok) storeCode('');
      showCoupon(res);
      paintTotals();
    });
  }

  function paintTotals() { renderCartTotals(); renderCheckoutTotals(); }

  var couponForm = $('[data-coupon]');
  if (couponForm) {
    couponForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = $('input[name=code]', couponForm);
      var code  = (input.value || '').trim();
      if (!code) { showCoupon({ ok: false, error: 'Enter a code.' }); return; }
      askServer(code, function (res) {
        couponState.code = res.ok ? code : '';
        couponState.sig  = basketSig();
        couponState.data = res.ok ? res : null;
        storeCode(res.ok ? res.code : '');
        showCoupon(res);
        paintTotals();
      });
    });
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-coupon-remove]')) return;
    storeCode('');
    couponState = { code: '', sig: '', data: null, busy: false };
    showCoupon(null);
    paintTotals();
  });

  function renderCart() {
    var page = $('[data-cart-page]');
    if (!page) return;

    var cart  = store.read('cart');
    var rows  = $('[data-cart-rows]');
    var table = $('[data-cart-table]');
    var side  = $('[data-cart-side]');
    var empty = $('[data-cart-empty]');

    if (!cart.length) {
      table.hidden = true; side.hidden = true; empty.hidden = false;
      return;
    }
    table.hidden = false; side.hidden = false; empty.hidden = true;

    rows.innerHTML = cart.map(function (i) {
      return '<tr data-key="' + i.key.replace(/"/g, '&quot;') + '">' +
        '<td>' + (i.image ? '<img src="' + i.image + '" alt="">' : '') + '</td>' +
        '<td><a class="ct-name" href="/product/' + i.slug + '/">' + i.title + '</a>' +
          (i.option ? '<span class="ct-opt">' + i.option + '</span>' : '') + '</td>' +
        '<td>' + money(i.price) + '</td>' +
        '<td><div class="qty"><button type="button" data-cart-step="-1">&minus;</button>' +
          '<input type="number" value="' + i.qty + '" min="1" max="999" data-cart-qty aria-label="Quantity">' +
          '<button type="button" data-cart-step="1">+</button></div></td>' +
        '<td><b>' + money(i.price * i.qty) + '</b></td>' +
        '<td><button class="ct-remove" type="button" data-cart-remove aria-label="Remove">&times;</button></td>' +
      '</tr>';
    }).join('');

    renderCartTotals();
    syncCoupon();
    renderCross();
  }

  function renderCartTotals() {
    if (!$('[data-cart-page]')) return;
    var cart     = store.read('cart');
    var subtotal = cart.reduce(function (n, i) { return n + i.price * i.qty; }, 0);
    var disc     = Math.min(discount(), subtotal);

    set('[data-cart-subtotal]', money(subtotal));
    set('[data-cart-discount]', '-' + money(disc));

    // the server has the delivery rules; until it answers, the row waits
    refreshDelivery(function (data) {
      var ship = data && data.deliverable && !freeShip() ? data.shipping : 0;
      var vat  = data ? data.vat : 0;
      set('[data-cart-vat]',   money(vat));
      set('[data-cart-ship]',  freeShip() ? 'Free' : shipText(data));
      set('[data-cart-total]', money(subtotal - disc + ship + vat));
    });

    var row = $('[data-cart-page] [data-discount-row]');
    if (row) row.hidden = disc <= 0;
    if (disc > 0) set('[data-cart-page] [data-discount-label]', 'Discount ' + couponState.data.code);
  }

  document.addEventListener('click', function (e) {
    var row = e.target.closest('tr[data-key]');
    if (!row) return;
    var cart = store.read('cart');
    var line = cart.filter(function (i) { return i.key === row.dataset.key; })[0];
    if (!line) return;

    if (e.target.closest('[data-cart-remove]')) {
      store.write('cart', cart.filter(function (i) { return i.key !== row.dataset.key; }));
      renderCart();
      return;
    }
    var step = e.target.closest('[data-cart-step]');
    if (step) {
      line.qty = Math.max(1, Math.min(999, line.qty + parseInt(step.dataset.cartStep, 10)));
      store.write('cart', cart);
      renderCart();
    }
  });

  document.addEventListener('change', function (e) {
    var input = e.target.closest('[data-cart-qty]');
    if (!input) return;
    var row  = input.closest('tr[data-key]');
    var cart = store.read('cart');
    var line = cart.filter(function (i) { return i.key === row.dataset.key; })[0];
    if (!line) return;
    line.qty = Math.max(1, Math.min(999, parseInt(input.value, 10) || 1));
    store.write('cart', cart);
    renderCart();
  });

  /* -------------------------------------------------- wishlist page */
  function renderWishlist() {
    var grid = $('[data-wishlist-grid]');
    if (!grid) return;
    var slugs = store.read('wishlist');
    var empty = $('[data-wishlist-empty]');
    if (!slugs.length) { grid.hidden = true; empty.hidden = false; return; }

    fetch('/wishlist-items.php?slugs=' + encodeURIComponent(slugs.join(',')))
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (html) {
        if (!html.trim()) { grid.hidden = true; empty.hidden = false; return; }
        grid.innerHTML = html;
        grid.hidden = false;
        empty.hidden = true;
      })
      .catch(function () { grid.hidden = true; empty.hidden = false; });
  }

  /* Cross-sells: what the shop suggests alongside whatever is in the basket.
     The basket only exists in this browser, so the page has to ask. */
  var crossFor = '';
  function renderCross() {
    var box = $('[data-cross]');
    if (!box) return;

    var slugs = store.read('cart').map(function (i) { return i.slug; });
    slugs = slugs.filter(function (s, i) { return slugs.indexOf(s) === i; });
    var key = slugs.join(',');

    if (!key) { box.hidden = true; crossFor = ''; return; }
    if (key === crossFor) return;          // the basket has not changed
    crossFor = key;

    fetch('/cross-sells.php?slugs=' + encodeURIComponent(key))
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (html) {
        if (!html.trim()) { box.hidden = true; return; }
        $('[data-cross-grid]').innerHTML = html;
        box.hidden = false;
      })
      .catch(function () { box.hidden = true; });
  }

  /* ------------------------------------------------------ checkout */
  function renderCheckout() {
    var form = $('[data-checkout]');
    if (!form) return;

    var cart    = store.read('cart');
    var summary = $('[data-co-summary]');
    var empty   = $('[data-co-empty]');
    var field   = $('[data-cart-field]', form);

    field.value = JSON.stringify(cart.map(function (i) {
      return { slug: i.slug, option: i.option, qty: i.qty };
    }));

    if (!cart.length) { summary.hidden = true; empty.hidden = false; return; }
    summary.hidden = false; empty.hidden = true;

    $('[data-co-lines]').innerHTML = cart.map(function (i) {
      return '<li><span class="n">' + i.qty + ' ×</span>' +
        '<span class="t">' + i.title + (i.option ? '<em>' + i.option + '</em>' : '') + '</span>' +
        '<b>' + money(i.price * i.qty) + '</b></li>';
    }).join('');

    renderCheckoutTotals();
    syncCoupon();
  }

  function renderCheckoutTotals() {
    if (!$('[data-checkout]')) return;
    var cart     = store.read('cart');
    var subtotal = cart.reduce(function (n, i) { return n + i.price * i.qty; }, 0);
    var disc     = Math.min(discount(), subtotal);

    set('[data-co-subtotal]', money(subtotal));
    set('[data-co-discount]', '-' + money(disc));

    refreshDelivery(function (data) {
      var ship = data && data.deliverable && !freeShip() ? data.shipping : 0;
      var vat  = data ? data.vat : 0;
      renderDeliveryChoice(data);
      set('[data-co-ship]',  freeShip() ? 'Free' : shipText(data));
      set('[data-co-vat]',   money(vat));
      set('[data-co-total]', money(subtotal - disc + ship + vat));

      // a basket we cannot send must not look ready to order
      var place = $('[data-checkout] button[type=submit]');
      if (place) place.disabled = !!data && !data.deliverable;
    });

    var row = $('[data-checkout] [data-discount-row]');
    if (row) row.hidden = disc <= 0;
    if (disc > 0) set('[data-checkout] [data-discount-label]', 'Discount ' + couponState.data.code);

    var field = $('[data-coupon-field]');
    if (field) field.value = disc > 0 ? couponState.data.code : '';
  }

  /* Picking a different rate re-prices the order. The radios are drawn by
     renderDeliveryChoice, so the listener has to be on the document. */
  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-ship-pick]')) renderCheckoutTotals();
  });

  /* Changing the delivery country re-quotes the order there and then. */
  var countrySelect = $('[data-co-country]');
  if (countrySelect) {
    country = countrySelect.value || country;
    countrySelect.addEventListener('change', function () {
      country = this.value;
      set('[data-co-zone]', country === 'GB' ? 'UK' : 'No delivery');
      renderCheckout();
    });
  }

  // the order is placed, so the basket and the code it used are finished with
  if ($('[data-order-done]')) { store.write('cart', []); storeCode(''); }

  badges();
  markWishlist();
  renderCart();
  renderWishlist();
  renderCheckout();
  renderMini();
})();
