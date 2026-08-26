/* ARG FLEX LTD — paying by card or PayPal.
 *
 * Loaded only on the checkout, and only when a gateway has its keys. Two
 * gateways, two shapes:
 *
 *   the card   our own Place order button stays. Stripe's fields are shown
 *              as soon as the card is chosen, using the deferred flow, so
 *              the amount can follow the basket without an intent being
 *              created for every keystroke.
 *
 *   PayPal     PayPal's own button replaces ours, because their flow starts
 *              from it. That is how the live shop behaves too: choosing
 *              PayPal swaps the button for one reading "Pay with PayPal".
 *
 * Neither path sends an amount. The server prices the basket, tells the
 * gateway, and afterwards asks the gateway what was actually paid. All this
 * file does is carry tokens between the two.
 */
(function () {
  'use strict';

  var CFG = window.ARGFLEX_PAY || {};
  var form = document.querySelector('[data-checkout]');
  if (!form || (!CFG.stripe && !CFG.paypal)) return;

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var placeBtn = form.querySelector('button[type=submit]');
  var cardBox  = $('[data-card-fields]');
  var payBox   = $('[data-paypal-button]');
  var noteBox  = $('[data-pay-error]');

  var say = function (message) {
    if (!noteBox) return;
    noteBox.textContent = message || '';
    noteBox.hidden = !message;
    if (message) noteBox.scrollIntoView({ block: 'center', behavior: 'smooth' });
  };

  var chosen = function () {
    var on = form.querySelector('input[name=payment]:checked')
          || form.querySelector('input[name=payment][type=hidden]');
    return on ? on.value : '';
  };

  /* What the server needs to price and check the order: never an amount. */
  function orderPayload(extra) {
    var data = { cart: [] };
    try { data.cart = JSON.parse(localStorage.getItem('argflex.cart') || '[]')
            .map(function (i) { return { slug: i.slug, option: i.option || '', qty: i.qty }; }); }
    catch (e) {}

    new FormData(form).forEach(function (value, key) {
      if (key === 'cart') return;
      if (key.slice(-2) === '[]') return;
      var m = key.match(/^ship\[(\d+)\]$/);
      if (m) { (data.ship = data.ship || [])[+m[1]] = +value; return; }
      data[key] = value;
    });

    return Object.assign(data, extra || {});
  }

  function post(body) {
    return fetch('/payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json().catch(function () { return { ok: false,
             error: 'The payment service did not answer properly. Nothing has been charged.' }; }); });
  }

  /** Field-level errors from the server, shown where the customer typed. */
  function showFieldErrors(errors) {
    var first = null;
    Object.keys(errors || {}).forEach(function (field) {
      var input = form.querySelector('[name="' + field + '"]');
      if (input && !first) first = input;
    });
    if (first) first.focus();
    say(Object.values(errors || {})[0] || 'Please check the form.');
  }

  var loading = {};
  function script(src) {
    if (loading[src]) return loading[src];
    return loading[src] = new Promise(function (resolve, reject) {
      var el = document.createElement('script');
      el.src = src;
      el.onload = resolve;
      el.onerror = function () { reject(new Error('could not load ' + src)); };
      document.head.appendChild(el);
    });
  }

  /* ------------------------------------------------------------ the card */

  var stripe = null, elements = null, mounted = false;

  /** The figure the summary is showing, in pence. */
  function currentTotal() {
    var el = $('[data-co-total]');
    if (!el) return 0;
    var digits = el.textContent.replace(/[^0-9.]/g, '');
    return Math.round(parseFloat(digits || '0') * 100);
  }

  function mountCard() {
    if (!CFG.stripe || mounted || !cardBox) return;
    var total = currentTotal();
    if (total <= 0) return;                 // nothing to pay for yet

    script('https://js.stripe.com/v3/').then(function () {
      if (mounted) return;
      mounted = true;
      stripe = window.Stripe(CFG.stripe);
      // Deferred: the fields appear now, the charge is created at confirm
      // time, so the amount can follow the basket without opening an intent
      // for every change.
      elements = stripe.elements({ mode: 'payment', currency: CFG.currency, amount: total,
                                   appearance: { theme: 'flat' } });
      elements.create('payment', { layout: 'tab' }).mount(cardBox);
      cardBox.hidden = false;
    }).catch(function () {
      say('The card form could not be loaded. Please choose another way to pay.');
    });
  }

  function payByCard() {
    say('');
    placeBtn.disabled = true;

    elements.submit().then(function (result) {
      if (result.error) throw new Error(result.error.message);
      return post(orderPayload({ action: 'start', payment: 'stripe' }));
    }).then(function (started) {
      if (!started.ok) {
        if (started.errors) { showFieldErrors(started.errors); throw new Error(''); }
        throw new Error(started.error);
      }
      return stripe.confirmPayment({
        elements: elements,
        clientSecret: started.client_secret,
        confirmParams: { return_url: location.origin + '/checkout/?ok=' + encodeURIComponent(started.reference) },
        redirect: 'if_required'
      }).then(function (result) {
        if (result.error) throw new Error(result.error.message);
        return post({ action: 'finish', reference: started.reference,
                      intent: result.paymentIntent.id });
      });
    }).then(function (done) {
      if (!done.ok) throw new Error(done.error);
      localStorage.removeItem('argflex.cart');
      location.href = '/checkout/?ok=' + encodeURIComponent(done.reference);
    }).catch(function (e) {
      placeBtn.disabled = false;
      if (e && e.message) say(e.message);
    });
  }

  /* ---------------------------------------------------------- paypal */

  var paypalDrawn = false;

  function drawPayPal() {
    if (!CFG.paypal || paypalDrawn || !payBox) return;
    paypalDrawn = true;

    script('https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(CFG.paypal)
           + '&currency=' + encodeURIComponent((CFG.currency || 'gbp').toUpperCase())
           + '&intent=capture&disable-funding=card,credit,paylater')
      .then(function () {
        window.paypal.Buttons({
          style: { layout: 'horizontal', label: 'pay', height: 48, tagline: false },

          createOrder: function () {
            say('');
            return post(orderPayload({ action: 'start', payment: 'ppcp' }))
              .then(function (started) {
                if (!started.ok) {
                  if (started.errors) showFieldErrors(started.errors);
                  else say(started.error);
                  throw new Error(started.error || 'form');
                }
                payBox.dataset.reference = started.reference;
                return started.paypal_order;
              });
          },

          onApprove: function (data) {
            return post({ action: 'finish', reference: payBox.dataset.reference,
                          paypal_order: data.orderID })
              .then(function (done) {
                if (!done.ok) { say(done.error); return; }
                localStorage.removeItem('argflex.cart');
                location.href = '/checkout/?ok=' + encodeURIComponent(done.reference);
              });
          },

          onError: function () {
            say('PayPal could not complete that. Nothing has been charged.');
          }
        }).render(payBox);
        payBox.hidden = false;
      })
      .catch(function () {
        say('PayPal could not be loaded. Please choose another way to pay.');
      });
  }

  /* ---------------------------------------------------------- wiring */

  function reflectChoice() {
    var id = chosen();
    if (cardBox) cardBox.hidden = id !== 'stripe' || !mounted;
    if (payBox)  payBox.hidden  = id !== 'ppcp'  || !paypalDrawn;

    // PayPal's own button is the one that starts their flow, so ours steps
    // aside — which is what the live shop does as well.
    if (placeBtn) placeBtn.hidden = id === 'ppcp' && !!CFG.paypal;

    if (id === 'stripe') mountCard();
    if (id === 'ppcp')   drawPayPal();
    say('');
  }

  form.addEventListener('change', function (e) {
    if (e.target.name === 'payment') reflectChoice();
    // the basket or the delivery choice moved, so the card's amount has too
    if (e.target.matches('[data-ship-pick]') && elements) {
      var total = currentTotal();
      if (total > 0) elements.update({ amount: total });
    }
  });

  form.addEventListener('submit', function (e) {
    if (chosen() !== 'stripe' || !CFG.stripe) return;   // the invoice routes post normally
    e.preventDefault();
    payByCard();
  });

  // The summary is filled in by a fetch, so the amount is not there yet.
  var settle = setInterval(function () {
    if (currentTotal() > 0) { clearInterval(settle); reflectChoice(); }
  }, 400);
  setTimeout(function () { clearInterval(settle); }, 15000);
})();
