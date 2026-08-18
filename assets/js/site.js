/* ARG FLEX LTD — site behaviour. No dependencies. */
(function () {
  'use strict';

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var money = function (pence) { return '£' + (pence / 100).toFixed(2); };

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
        priceEl.textContent = match ? money(match.price) : basePrice;
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
    var key    = btn.dataset.slug + '|' + option;

    var cart = store.read('cart');
    var line = cart.filter(function (i) { return i.key === key; })[0];
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
    toast('Added to cart.', '<a href="/cart/">View cart</a>');
    renderCart();
  });

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

    var subtotal = cart.reduce(function (n, i) { return n + i.price * i.qty; }, 0);
    var vat      = Math.round(subtotal * 0.2);
    var ship     = subtotal >= 25000 ? 0 : 1200;
    $('[data-cart-subtotal]').textContent = money(subtotal);
    $('[data-cart-vat]').textContent      = money(vat);
    $('[data-cart-ship]').textContent     = ship === 0 ? 'Free' : money(ship);
    $('[data-cart-total]').textContent    = money(subtotal + vat + ship);
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

  badges();
  markWishlist();
  renderCart();
  renderWishlist();
})();
