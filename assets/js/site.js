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

    // What was picked on the product page, if anything.
    var remembered = 0, applied = false;
    try { remembered = +localStorage.getItem('argflex.ship') || 0; } catch (e) {}

    data.packages.forEach(function (pkg, i) {
      html += '<fieldset class="ship-pkg">';
      html += '<legend>' + (many ? esc(pkg.name) : 'How it travels') + '</legend>';
      pkg.rates.forEach(function (rate) {
        var id = 'ship-' + i + '-' + rate.id;
        /* A speed chosen on the product page opens selected here, when this
           consignment can actually be sent that way. Otherwise the server's
           own choice stands — carrying a rate into a consignment that cannot
           use it would silently reprice the order. */
        var wanted = pkg.chosen;
        if (remembered && pkg.rates.some(function (r) { return r.id === remembered; })) {
          wanted = remembered;
          if (wanted !== pkg.chosen) applied = true;
        }
        html += '<label class="ship-opt" for="' + id + '">'
              + '<input type="radio" id="' + id + '" name="ship[' + i + ']" value="' + rate.id + '"'
              + ' data-ship-pick data-package="' + i + '"'
              + (rate.id === wanted ? ' checked' : '') + '>'
              + '<span class="ship-name">' + esc(rate.title) + '</span>'
              + '<b class="ship-cost">' + money(rate.cost) + '</b>'
              + '</label>';
      });
      html += '</fieldset>';
    });

    box.innerHTML = html;

    /* A rate carried in from the product page is not the one the server
       quoted, so the figures beside it are a consignment behind until they
       are asked for again. Without this the checkout showed the fast price
       with the slow speed selected. */
    if (applied) {
      var again = $('[data-ship-pick]:checked');
      if (again) again.dispatchEvent(new Event('change', { bubbles: true }));
    }
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
    // A row marked data-family-only lists the other listings of this model.
    // It is navigation, not an axis this product varies on, so it takes no
    // part in matching a variation.
    var rows    = $$('.sw-row', form).filter(function (r) {
      return !r.hasAttribute('data-family-only');
    });
    var clearBt = $('[data-clear]', form);
    var none    = $('.sw-none', form);
    var priceEl = $('.p-price b');
    var basePrice = priceEl ? priceEl.textContent : '';
    var waBtn   = $('[data-wa]', form);
    var shipBox = $('[data-ship-estimate]', form);
    var shipOut = $('[data-ship-lines]', form);
    var buySlug = addBtn ? (addBtn.dataset.slug || '') : '';
    var shipAsk = '';           // what the last request was for
    var shipRates = [], shipGoods = 0;
    /* The rate chosen here is remembered so the checkout opens on it. Kept in
       the browser beside the basket rather than put in the URL: it is a
       preference, not a page. */
    var shipPicked = (function () {
      try { return +localStorage.getItem('argflex.ship') || 0; } catch (e) { return 0; }
    })();

    var variants = {};
    try { variants = JSON.parse(form.dataset.variants || '{}'); } catch (e) {}

    /** Values picked so far, one per attribute row, in row order. */
    function picked() {
      return rows.map(function (row) {
        // Past six options a row is a <select>, not a block of chips.
        var sel = $('[data-attr-select]', row);
        if (sel) return sel.value.indexOf('go:') === 0 ? '' : sel.value;
        var on = $('.sw.on', row);
        return on ? on.dataset.value : '';
      });
    }

    /** Grey out options that cannot complete a stocked combination. */
    function markAvailability() {
      var current = picked();

      rows.forEach(function (row, i) {
        var sel = $('[data-attr-select]', row);
        if (!sel) return;
        $$('option', sel).forEach(function (o) {
          if (o.value.indexOf('go:') === 0) return;      // another listing
          var trial = current.slice();
          trial[i] = o.value;
          var possible = Object.keys(variants).some(function (key) {
            var parts = key.split('|');
            return trial.every(function (v, j) { return !v || v === parts[j]; });
          });
          o.disabled = !possible;
          o.textContent = o.textContent.replace(/ — not stocked$/, '')
                        + (possible ? '' : ' — not stocked');
        });
      });

      rows.forEach(function (row, i) {
        $$('.sw', row).forEach(function (btn) {
          var trial = current.slice();
          trial[i] = btn.dataset.value;
          var possible = Object.keys(variants).some(function (key) {
            var parts = key.split('|');
            return trial.every(function (v, j) { return !v || v === parts[j]; });
          });
          btn.classList.toggle('out', !possible);
          // Struck through and greyed is only half of it. Without this a
          // screen reader announces an ordinary button, the person presses
          // it, and nothing happens with nothing said. It stays reachable
          // on purpose — knowing the size exists but is not made in this
          // diameter is worth hearing.
          btn.setAttribute('aria-disabled', possible ? 'false' : 'true');
        });
      });
    }

    function update() {
      var values = picked();
      var complete = values.every(Boolean);
      var match = complete ? variants[values.join('|')] : null;

      if (clearBt) clearBt.hidden = !values.some(Boolean) || rows.length === 0;
      if (none) none.hidden = !(complete && !match);

      // A variation can carry its own photo — a 50 m coil does not look like
      // a 1 m offcut. Falling back to the product's own means a half-chosen
      // combination never leaves the gallery blank.
      var mainImg = $('#p-img');
      if (mainImg) {
        if (!mainImg.dataset.base) mainImg.dataset.base = mainImg.getAttribute('src') || '';
        var want = (match && match.image) ? match.image : mainImg.dataset.base;
        if (want && mainImg.getAttribute('src') !== want) mainImg.setAttribute('src', want);
      }

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
      var n = parseInt(qty && qty.value, 10) || 1;
      if (total) {
        var unit = match ? match.price : (addBtn && !rows.length ? +addBtn.dataset.price || 0 : 0);
        if (!unit) { total.hidden = true; }
        else { total.hidden = false; $('b', total).textContent = money(unit * n); }
      }
      markAvailability();
      extras(match, complete ? values.join('|') : '', n);
      carryLength();
    }

    /* Carry the chosen length to the other bores of this model — 50 m of the
       10 mm should become 50 m of the 16 mm, not the 16 mm's own default.

       Held on the element and appended only when the link is FOLLOWED. Writing
       it into every href instead would put a hundred and fifty parameterised
       copies of pages that already exist in front of a crawler, on a site whose
       whole constraint is search. Without JavaScript the link still works; only
       the convenience is lost. */
    function carryLength() {
      var jumps = $$('[data-family-jump]', form);
      if (!jumps.length) return;

      var lengthRow = rows.filter(function (r) {
        return (r.dataset.attr || '').toLowerCase().indexOf('length') !== -1;
      })[0];
      var on = lengthRow && $('.sw.on', lengthRow);

      jumps.forEach(function (a) {
        if (on) a.dataset.carry = on.dataset.value;
        else delete a.dataset.carry;
      });
    }

    /* Carriage for what is chosen right now, and a WhatsApp message that
       says the same thing. Both only make sense once a combination is
       complete, because on this shop the length IS the delivery price. */
    function extras(match, optKey, n) {
      if (waBtn) {
        var msg = waBtn.dataset.base
                + (match ? '\nOption: ' + match.label : '')
                + '\nQuantity: ' + n;
        waBtn.setAttribute('href', 'https://wa.me/' + waBtn.dataset.number
                                 + '?text=' + encodeURIComponent(msg));
      }

      if (!shipBox || !buySlug) return;
      if (rows.length && !optKey) { shipBox.hidden = true; shipAsk = ''; return; }

      var ask = buySlug + '|' + optKey + '|' + n;
      if (ask === shipAsk) return;
      shipAsk = ask;

      // One implementation of the delivery rules, and it is on the server.
      fetch('/delivery-quote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart: [{ slug: buySlug, option: optKey, qty: n }] })
      })
        .then(function (r) { return r.json(); })
        .then(function (q) {
          if (shipAsk !== ask) return;         // a later answer already won
          var pkg = (q.packages || [])[0];
          if (!q.deliverable || !pkg || !pkg.rates.length) { shipBox.hidden = true; return; }

          shipRates = pkg.rates;
          shipGoods = (q.subtotal || 0);

          /* Pickable, not just quoted. Two speeds at two prices is a decision,
             and making it here — where the length that drives the price is
             also being chosen — is better than making it three pages later
             with the figure out of sight. */
          shipOut.innerHTML = pkg.rates.map(function (rate) {
            var id = 'pship-' + rate.id;
            return '<label class="p-ship-opt" for="' + id + '">'
                 + '<input type="radio" id="' + id + '" name="p-ship" value="' + rate.id + '"'
                 + (rate.id === shipPicked ? ' checked' : '') + ' data-p-ship>'
                 + '<i>' + esc(rate.title) + '</i>'
                 + '<b>' + money(rate.cost) + '</b></label>';
          }).join('')
          + (q.packages.length > 1
              ? '<em class="p-ship-note">Sent as ' + q.packages.length
                + ' parcels, each charged on its own — the checkout shows the breakdown.</em>'
              : '');

          shipBox.hidden = false;
          shipTotal();
        })
        .catch(function () { shipBox.hidden = true; });
    }

    /* What it comes to with the carriage that is selected, and a way straight
       to the checkout once it is. Goods only until then: a total that quietly
       included a delivery nobody picked would be the shop choosing for them. */
    function shipTotal() {
      var wrap = $('[data-ship-sum]', form);
      if (!wrap) return;

      var rate = shipRates.filter(function (r) { return r.id === shipPicked; })[0];
      if (!rate) { wrap.hidden = true; return; }

      var n    = parseInt(qty && qty.value, 10) || 1;
      var add  = $('[data-add-to-cart]', form);
      var unit = add ? +add.dataset.price || 0 : 0;
      if (!unit) { wrap.hidden = true; return; }

      wrap.hidden = false;
      $('[data-ship-sum-figure]', wrap).innerHTML =
        money(unit * n) + ' + ' + money(rate.cost) + ' delivery = <b>'
        + money(unit * n + rate.cost) + '</b>';
    }

    rows.forEach(function (row) {
      row.addEventListener('click', function (e) {
        var btn = e.target.closest('.sw');
        // A bore that belongs to a sibling listing is a link, not a swatch:
        // let it navigate rather than toggling something on a page we are
        // about to leave.
        if (!btn || btn.classList.contains('out') || btn.tagName === 'A') return;
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

    form.addEventListener('change', function (e) {
      var sel = e.target.closest('[data-attr-select]');
      if (sel) {
        if (sel.value.indexOf('go:') === 0) {
          // Another listing of the same model. Take the length along.
          var lengthRow = rows.filter(function (r) {
            return (r.dataset.attr || '').toLowerCase().indexOf('length') !== -1;
          })[0];
          var on   = lengthRow && $('.sw.on', lengthRow);
          var lsel = lengthRow && $('[data-attr-select]', lengthRow);
          var keep = on ? on.dataset.value : (lsel ? lsel.value : '');
          location.href = sel.value.slice(3) + (keep ? '?length=' + encodeURIComponent(keep) : '');
          return;
        }
        update();
        return;
      }

      if (!e.target.closest('[data-p-ship]')) return;
      shipPicked = +e.target.value;
      try { localStorage.setItem('argflex.ship', String(shipPicked)); } catch (err) {}
      shipTotal();
    });

    form.addEventListener('click', function (e) {
      if (!e.target.closest('[data-ship-go]')) return;
      e.preventDefault();
      var add = $('[data-add-to-cart]', form);
      if (!add) return;

      var count = function () {
        return store.read('cart').reduce(function (n, i) { return n + i.qty; }, 0);
      };
      var before = count();
      add.click();
      setTimeout(function () {
        if (count() > before) location.href = '/checkout/';
      }, 0);
    });

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

  /* Following a link to another bore of the same model takes the chosen
     length with it. The parameter is added here rather than in the markup —
     see carryLength() — so what a crawler sees is the plain, canonical URL. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-family-jump]');
    if (!a || !a.dataset.carry) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;   // opening in a tab
    e.preventDefault();
    location.href = a.getAttribute('href').split('?')[0]
                  + '?length=' + encodeURIComponent(a.dataset.carry);
  });

  /* ------------------------------------------------------ quick view */
  /* These hoses look alike in a photograph and are told apart by a standard,
     a bore range and a temperature — so comparing them means reading, and
     opening each one to read six lines is the slow way round. The popup shows
     enough to decide; buying a variable one still happens on its page, which
     is where the delivery estimate and the reviews are. */
  (function () {
    var box = null, panel = null, opener = null, live = null;

    function build() {
      if (box) return;
      box = document.createElement('dialog');
      box.className = 'qv';
      // A native dialog brings Escape, the backdrop and a focus trap with it.
      box.innerHTML =
        '<button class="qv-x" type="button" aria-label="Close">' +
        '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">' +
        '<path d="M6 6l12 12M18 6L6 18"/></svg></button><div class="qv-body"></div>';
      document.body.appendChild(box);
      panel = $('.qv-body', box);

      $('.qv-x', box).addEventListener('click', function () { box.close(); });
      // Clicking the backdrop closes it; clicking the card inside must not.
      box.addEventListener('click', function (e) { if (e.target === box) box.close(); });
      box.addEventListener('close', function () { if (opener) opener.focus(); });
    }

    /* What is chosen right now, keyed by axis name. The panel is redrawn from
       this rather than read back out of the DOM, so the price, the picture and
       the button can never disagree with the swatches. */
    var picked = {}, shown = null;

    function variantKey() {
      if (!shown || !shown.axes.length) return '';
      var parts = shown.axes.map(function (a) { return picked[a.name] || ''; });
      return parts.every(Boolean) ? parts.join('|') : '';
    }

    function currentVariant() {
      var key = variantKey();
      return key && shown.variants[key] ? shown.variants[key] : null;
    }

    /** Could this value still complete a combination the shop actually makes? */
    function reachable(axisName, value) {
      if (!shown || !shown.axes.length) return true;
      var trial = shown.axes.map(function (a) {
        return a.name === axisName ? value : (picked[a.name] || '');
      });
      return Object.keys(shown.variants).some(function (key) {
        var got = key.split('|');
        return trial.every(function (v, i) { return !v || v === got[i]; });
      });
    }

    function qvRow(label, inner) {
      return '<div class="qv-row"><span>' + esc(label) + '</span><div>' + inner + '</div></div>';
    }

    function swatch(axisName, value, label) {
      var on  = picked[axisName] === value;
      var out = !reachable(axisName, value);
      return '<button class="qv-sw' + (on ? ' on' : '') + (out ? ' out' : '') + '" type="button"'
           + ' data-qv-pick="' + esc(axisName) + '" data-qv-value="' + esc(value) + '"'
           + ' aria-pressed="' + (on ? 'true' : 'false') + '"'
           + ' aria-disabled="' + (out ? 'true' : 'false') + '">' + esc(label) + '</button>';
    }

    /* Past this many, chips stop helping. Ten bores wrap to three ragged
       lines and push the button out of the window, and picking one out of a
       block that size is slower than reading a list. Under it — two speeds,
       three lengths — chips are still quicker, because every choice is
       visible at once. */
    var CHIPS_MAX = 6;

    /* Options for a <select>. `go:` in front of a value means it belongs to
       another listing of the same model and the popup reopens on it. */
    function qvSelect(axisName, entries) {
      return '<select class="qv-sel" data-qv-select="' + esc(axisName) + '"'
           + ' aria-label="' + esc(axisName) + '">'
           + entries.map(function (o) {
               return '<option value="' + esc(o.value) + '"'
                    + (o.on ? ' selected' : '')
                    + (o.out ? ' disabled' : '') + '>'
                    + esc(o.label) + (o.out ? ' — not stocked' : '') + '</option>';
             }).join('')
           + '</select>';
    }

    /* The bores of the whole family. A sibling's reopens the popup on that
       listing rather than closing it — the customer is comparing, and being
       thrown back to the grid to click again is what this popup exists to
       avoid. */
    function familyRow(d, axisVaries) {
      var entries = d.family.map(function (b) {
        return {
          value: b.mine ? b.slug : 'go:' + b.to,
          label: b.name,
          on:    b.mine && (!axisVaries || picked[d.axisName] === b.slug),
          out:   b.mine && axisVaries && !reachable(d.axisName, b.slug)
        };
      });

      // A family with one bore of its own and no siblings is not a choice.
      if (entries.length > CHIPS_MAX) return qvSelect(d.axisName, entries);

      return d.family.map(function (b) {
        if (b.mine) {
          if (axisVaries) return swatch(d.axisName, b.slug, b.name);
          // This listing IS that bore. A control that cannot be pressed would
          // read as broken, so it is stated rather than offered.
          return '<span class="qv-sw on" aria-current="true">' + esc(b.name) + '</span>';
        }
        return '<button class="qv-sw away" type="button" data-qv-go="' + esc(b.to) + '">'
             + esc(b.name) + '</button>';
      }).join('');
    }

    /* Only the parts that change, so choosing a length does not rebuild the
       picture and restart its fade. */
    function refresh() {
      var d = shown;
      if (!d) return;

      var v     = currentVariant();
      var qtyEl = $('.qv-qty input', panel);
      var qty   = Math.max(1, parseInt(qtyEl && qtyEl.value, 10) || 1);
      var unit  = v ? v.price : (d.simple ? +d.unit || 0 : 0);
      var ready = d.simple || !!v;

      var priceEl = $('.qv-price b', panel);
      if (priceEl) {
        priceEl.innerHTML = v && v.was
          ? '<s>' + money(v.was) + '</s> ' + money(v.price)
          : (v ? money(v.price) : esc(d.price));
      }

      var totalEl = $('.qv-total', panel);
      if (totalEl) {
        totalEl.hidden = !unit;
        if (unit) totalEl.innerHTML = 'Total: <b>' + money(unit * qty) + '</b>';
      }

      // A variation can carry its own photograph: a 50 m coil is not a 1 m offcut.
      var img = $('.qv-pic img', panel);
      if (img) {
        var want = (v && v.image) ? v.image : d.image;
        if (want && img.getAttribute('src') !== want) img.setAttribute('src', want);
      }

      $$('[data-qv-select]', panel).forEach(function (sel) {
        var axis = sel.dataset.qvSelect;
        $$('option', sel).forEach(function (o) {
          if (o.value.indexOf('go:') === 0) return;         // another listing
          var out = !reachable(axis, o.value);
          o.disabled = out;
          o.textContent = o.textContent.replace(/ — not stocked$/, '') + (out ? ' — not stocked' : '');
        });
        if (picked[axis] && sel.value !== picked[axis]) sel.value = picked[axis];
      });

      $$('[data-qv-pick]', panel).forEach(function (b) {
        var axis = b.dataset.qvPick, value = b.dataset.qvValue;
        var on = picked[axis] === value, out = !reachable(axis, value);
        b.classList.toggle('on', on);
        b.classList.toggle('out', out);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.setAttribute('aria-disabled', out ? 'true' : 'false');
      });

      var add = $('[data-add-to-cart]', panel);
      if (add) {
        add.disabled = !ready || !d.inStock;
        if (v) {
          add.dataset.price  = v.price;
          add.dataset.option = v.label;
        } else if (!d.simple) {
          delete add.dataset.price;
          delete add.dataset.option;
        }
      }

      var none = $('.qv-none', panel);
      if (none) {
        var allChosen = d.axes.length > 0 && d.axes.every(function (a) { return picked[a.name]; });
        none.hidden = !(allChosen && !v);
      }
    }

    function draw(d) {
      shown  = d;
      picked = {};

      // Open on the combination the product itself opens on, as its page does.
      d.axes.forEach(function (a) {
        var wanted = d.opensOn[a.name] || '';
        if (a.terms.some(function (t) { return t.slug === wanted; })) picked[a.name] = wanted;
        else if (a.terms.length === 1) picked[a.name] = a.terms[0].slug;
      });

      var spec = (d.specs || []).map(function (s) {
        return '<p><b>' + esc(s.label) + ':</b> ' + esc(s.value) + '</p>';
      }).join('');

      var rows = '', drawn = {};
      if (d.family.length) {
        var varies = d.axes.some(function (a) { return a.name === d.axisName; });
        rows += qvRow(d.axisName, familyRow(d, varies));
        drawn[d.axisName] = true;
      }
      d.axes.forEach(function (a) {
        if (drawn[a.name]) return;
        rows += qvRow(a.name, a.terms.length > CHIPS_MAX
          ? qvSelect(a.name, a.terms.map(function (t) {
              return { value: t.slug, label: t.name,
                       on: picked[a.name] === t.slug,
                       out: !reachable(a.name, t.slug) };
            }))
          : a.terms.map(function (t) { return swatch(a.name, t.slug, t.name); }).join(''));
      });

      var canBuy = d.inStock && (d.simple || d.axes.length > 0);

      panel.innerHTML =
        '<div class="qv-pic">' +
          (d.image ? '<img src="' + esc(d.image) + '" alt="' + esc(d.name) + '" width="420" height="320">' : '') +
        '</div>' +
        '<div class="qv-info">' +
          '<span class="cat-l">' + esc(d.cat) + '</span>' +
          '<h2 id="qv-title">' + esc(d.name) + '</h2>' +
          '<div class="qv-price"><b>' + esc(d.price) + '</b>' +
            (d.suffix ? '<small>' + esc(d.suffix) + '</small>' : '') +
            '<span class="p-stock ' + esc(d.stock.state) + '">' + esc(d.stock.label) + '</span></div>' +

          /* Shut to begin with. Four lines of prose in a popup whose job is a
             glance pushed the sizes and the button below the fold on a laptop.
             <details> brings the keyboard and screen-reader behaviour with it. */
          (spec
            ? '<details class="qv-spec"><summary>Specification</summary>' +
              '<div class="p-facts">' + spec + '</div></details>'
            : '') +

          rows +
          '<p class="qv-none" hidden>That combination is not stocked &mdash; ' +
            '<a href="/contacts/?product=' + esc(d.slug) + '">ask us about it</a>.</p>' +

          (canBuy
            ? '<div class="qv-acts">' +
                '<div class="qty qv-qty">' +
                  '<button type="button" data-qv-step="-1" aria-label="Decrease quantity">&minus;</button>' +
                  '<input type="number" value="1" min="1" max="999" aria-label="Quantity">' +
                  '<button type="button" data-qv-step="1" aria-label="Increase quantity">+</button>' +
                '</div>' +
                '<button class="btn btn-primary" type="button" data-add-to-cart' +
                ' data-slug="' + esc(d.slug) + '" data-title="' + esc(d.name) + '"' +
                (d.simple ? ' data-price="' + (+d.unit || 0) + '"' : '') +
                ' data-max="' + (+d.max || 0) + '"' +
                ' data-image="' + esc(d.image) + '">Add to cart</button>' +
              '</div>' +
              /* One button per way the shop can ACTUALLY take money today.
                 With no gateway configured this is empty and only the link
                 below shows — a button promising a payment the shop cannot
                 take is a lie the customer finds out at the end. */
              (d.pay || []).map(function (m) {
                return '<button class="btn btn-pay" type="button" data-qv-pay="' + esc(m.id) + '">'
                     + 'Buy now with ' + esc(m.title) + '</button>';
              }).join('') +
              '<p class="qv-total" hidden></p>' +
              '<a class="qv-more" href="/checkout/">More payment options &rarr;</a>'
            : '<p class="qv-total" hidden></p>') +
          '<a class="qv-more" href="' + esc(d.url) + '">See the full details &rarr;</a>' +
        '</div>';

      box.setAttribute('aria-labelledby', 'qv-title');
      box.removeAttribute('aria-label');
      refresh();
    }

    /* Open the popup on one product. Called both by the eye on a card and by a
       bore that belongs to another listing of the same model — which is why it
       is a function rather than living inside the card's click handler. */
    function load(slug, from) {
      if (!slug) return;
      build();
      if (from) opener = from;

      // Named before it opens, not after the fetch: a dialog announced as
      // "dialog" for the whole of the load is a dialog nobody can place.
      box.setAttribute('aria-label', 'Product details');
      box.removeAttribute('aria-labelledby');

      panel.innerHTML = '<p class="qv-wait">Loading&hellip;</p>';
      if (!box.open) {
        if (typeof box.showModal === 'function') box.showModal();
        else box.setAttribute('open', '');
      }

      if (live && live.abort) live.abort();
      var ctl = (typeof AbortController === 'function') ? new AbortController() : null;
      live = ctl;

      fetch('/quick-view.php?slug=' + encodeURIComponent(slug),
            ctl ? { signal: ctl.signal } : undefined)
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) throw new Error('no');
          draw(d);
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          // Never a dead end: the product page always works.
          panel.innerHTML = '<p class="qv-wait">Could not load that just now. '
            + '<a href="/product/' + esc(slug) + '/">Open the product page</a>.</p>';
        });
    }

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-quick-view]');
      if (!btn) return;
      e.preventDefault();
      load(btn.dataset.slug, btn);
    });

    /* ---- picking a size, stepping the quantity, moving to a sibling ---- */

    document.addEventListener('click', function (e) {
      if (!box || !box.open) return;

      var go = e.target.closest('[data-qv-go]');
      if (go) { load(go.dataset.qvGo); return; }

      var sw = e.target.closest('[data-qv-pick]');
      if (sw) {
        if (sw.classList.contains('out')) return;
        var axis = sw.dataset.qvPick;
        picked[axis] = picked[axis] === sw.dataset.qvValue ? '' : sw.dataset.qvValue;
        refresh();
        return;
      }

      var step = e.target.closest('[data-qv-step]');
      if (step) {
        var input = $('.qv-qty input', panel);
        input.value = Math.max(1, (parseInt(input.value, 10) || 1) + (+step.dataset.qvStep));
        refresh();
      }
    });

    document.addEventListener('change', function (e) {
      var sel = e.target.closest('[data-qv-select]');
      if (!sel || !box || !box.open) return;

      if (sel.value.indexOf('go:') === 0) { load(sel.value.slice(3)); return; }
      picked[sel.dataset.qvSelect] = sel.value;
      refresh();
    });

    document.addEventListener('input', function (e) {
      if (box && box.open && e.target.closest('.qv-qty input')) refresh();
    });

    /* Buying by a named gateway is an add followed by the same jump, with
       the method already chosen at the other end. The click is forwarded to
       the ordinary Add to cart so there is one path that puts things in a
       basket, not two. */
    document.addEventListener('click', function (e) {
      var pay = e.target.closest('[data-qv-pay]');
      if (!pay || !box || !box.open) return;
      payWith = pay.dataset.qvPay;
      var add = $('[data-add-to-cart]', panel);
      if (add && !add.disabled) add.click();
    });

    /* Adding from here goes straight to the checkout, which is what a popup
       that can price a combination is for. Only when something was actually
       added: the shared handler refuses an incomplete choice or a stock
       ceiling with a toast, and jumping to the checkout after one of those
       would be a lie about what just happened.

       CAPTURE phase. The shared add-to-cart handler is registered earlier in
       this file, so a bubble listener here runs AFTER it — and read a basket
       that had already grown, so the count never changed and the jump never
       happened. */
    var payWith = '';
    document.addEventListener('click', function (e) {
      if (!box || !box.open) return;
      if (!e.target.closest('.qv [data-add-to-cart]')) return;

      var count = function () {
        return store.read('cart').reduce(function (n, i) { return n + i.qty; }, 0);
      };
      var before = count();
      var method = payWith;
      payWith = '';

      setTimeout(function () {
        if (count() <= before) return;          // refused: a toast said why
        box.close();
        location.href = '/checkout/' + (method ? '?pay=' + encodeURIComponent(method) : '');
      }, 0);
    }, true);
  })();

  /* ---------------------------------------------------------- share */
  /* The phone's own share sheet where there is one — that is where WhatsApp,
     Messages and email already are, so offering our own list of four would
     be a worse version of it. Everywhere else the link goes to the clipboard,
     which is what a person on a desktop was going to do by hand anyway. */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-share]');
    if (!btn) return;

    var url   = btn.dataset.url || location.href;
    var share = { title: btn.dataset.title || document.title, url: url };

    if (navigator.share) {
      navigator.share(share).catch(function () {});   // dismissed is not an error
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url)
        .then(function () { toast('Link copied.'); })
        .catch(function () { window.prompt('Copy this link:', url); });
      return;
    }
    window.prompt('Copy this link:', url);
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
    // The endpoint answers "is that a real code?", so it asks for the page's
    // token like every other form does.
    var body = 'code=' + encodeURIComponent(code)
             + '&_form=' + encodeURIComponent(document.body.dataset.couponToken || '')
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

    // The picture is already on the cart line — the mini cart has shown it
    // all along. A summary of what you are about to pay for is the last place
    // to make somebody read their order back as a list of names.
    $('[data-co-lines]').innerHTML = cart.map(function (i) {
      return '<li>' +
        (i.image ? '<img class="co-thumb" src="' + i.image + '" alt="" loading="lazy">'
                 : '<span class="co-thumb ph"></span>') +
        '<span class="n">' + i.qty + ' ×</span>' +
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

  /* ------------------------------------------------- search suggestions */
  /* People arrive at this shop knowing a bore size or a standard rather than
     a product name, so answering while they type is worth a round trip. The
     form still submits to /shop/?q= — this only ever gets in the way of the
     journey it shortens, never of the one that already worked. */
  (function () {
    var form = $('[data-search]');
    if (!form) return;

    var box  = $('input[name=q]', form);
    var drop = $('.search-drop', form);
    if (!box || !drop) return;

    var timer = null, live = null, at = -1, shown = [], lastQ = '';

    function close() {
      drop.hidden = true;
      drop.innerHTML = '';
      box.setAttribute('aria-expanded', 'false');
      box.removeAttribute('aria-activedescendant');
      at = -1; shown = [];
    }

    function highlight(i) {
      var rows = $$('.sd-item', drop);
      if (!rows.length) return;
      at = (i + rows.length) % rows.length;
      rows.forEach(function (row, n) {
        var on = n === at;
        row.classList.toggle('on', on);
        row.setAttribute('aria-selected', on ? 'true' : 'false');
        if (on) {
          box.setAttribute('aria-activedescendant', row.id);
          if (row.scrollIntoView) row.scrollIntoView({ block: 'nearest' });
        }
      });
    }

    function draw(res) {
      if (!res.items.length) {
        drop.innerHTML = '<p class="sd-none">Nothing matched <b>' + esc(res.q) + '</b>. '
                       + 'Try a bore size such as <b>16mm</b>, or a standard such as '
                       + '<b>SAE J30</b> — or <a href="/contacts/">ask us</a>.</p>';
        drop.hidden = false;
        box.setAttribute('aria-expanded', 'true');
        shown = [];
        return;
      }

      shown = res.items;
      drop.innerHTML = res.items.map(function (it, i) {
        return '<a class="sd-item" id="sd-' + i + '" role="option" aria-selected="false" href="'
             + esc(it.url) + '">'
             // Not lazy: at most six thumbnails, inserted only when the list
             // opens, and a lazy image in a box that just appeared shows as a
             // grey square for exactly as long as anybody is looking at it.
             + (it.image ? '<img src="' + esc(it.image) + '" alt="" width="46" height="46" decoding="async">'
                         : '<span class="sd-noimg" aria-hidden="true"></span>')
             + '<span class="sd-txt"><b>' + esc(it.name) + '</b>'
             + '<em>' + esc(it.cat) + '</em></span>'
             + '<span class="sd-price">' + esc(it.price)
             + (it.stock ? '' : '<i>Out of stock</i>') + '</span>'
             + '</a>';
      }).join('')
        + '<a class="sd-all" href="' + esc(res.url) + '">See all '
        + res.total + ' result' + (res.total === 1 ? '' : 's') + '</a>';

      drop.hidden = false;
      box.setAttribute('aria-expanded', 'true');
      at = -1;
    }

    function ask() {
      var q = box.value.trim();
      if (q.length < 2) { lastQ = ''; close(); return; }
      if (q === lastQ) { if (shown.length || drop.innerHTML) drop.hidden = false; return; }
      lastQ = q;

      // An answer to a query the person has already typed past is noise.
      if (live) live.abort && live.abort();
      var ctl = (typeof AbortController === 'function') ? new AbortController() : null;
      live = ctl;

      fetch('/search.php?q=' + encodeURIComponent(q), ctl ? { signal: ctl.signal } : undefined)
        .then(function (r) { return r.json(); })
        .then(function (res) { if (box.value.trim() === res.q) draw(res); })
        .catch(function () {});          // aborted, or offline: the form still works
    }

    box.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(ask, 180);
    });
    box.addEventListener('focus', function () { if (box.value.trim().length >= 2) ask(); });

    box.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); return; }
      if (drop.hidden) return;

      if (e.key === 'ArrowDown')    { e.preventDefault(); highlight(at + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(at - 1); }
      else if (e.key === 'Enter' && at > -1) {
        // Only when a row is picked; otherwise Enter submits, as it always did.
        var row = $$('.sd-item', drop)[at];
        if (row) { e.preventDefault(); location.href = row.getAttribute('href'); }
      }
    });

    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) close();
    });
    form.addEventListener('submit', close);
  })();

  // the order is placed, so the basket and the code it used are finished with
  if ($('[data-order-done]')) { store.write('cart', []); storeCode(''); }

  badges();
  markWishlist();
  renderCart();
  renderWishlist();
  renderCheckout();
  renderMini();
})();
