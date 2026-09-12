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

  /* The checkout's own token, for the requests that do NOT go through
     orderPayload().

     payment.php checks it on BOTH steps — start and finish — and only start
     was sending it, because only start is built from the form. So every card
     and every PayPal payment took the money and was then refused at the door
     with "the checkout had gone stale": the order was never written by the
     browser at all, and the only reason any of them exist is the gateway's
     webhook arriving seconds later to finish the job. */
  var formToken = function () {
    var el = form.querySelector('input[name=_form]');
    return el ? el.value : '';
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

  /* payment.php answers in JSON on every path it knows about, including its
     own fatals — see the handlers at the top of it. So a reply that will not
     parse is something neither side planned: the host's error page, a proxy in
     the way, a limit hit before PHP ran. Say the status and put the first of
     the body in the console, because "did not answer properly" on its own sent
     somebody looking through Stripe for a fault that was never there. */
  function post(body) {
    var code = 0;
    return fetch('/payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) {
      code = r.status;
      return r.text();
    }).then(function (text) {
      try {
        return JSON.parse(text);
      } catch (e) {
        if (window.console) console.error('payment.php answered ' + code
          + ' with something that is not JSON:', text.slice(0, 800));
        return { ok: false, error: 'The payment service answered ' + code
          + ' and we could not read it. Nothing has been charged — please try '
          + 'again, or get in touch and quote that number.' };
      }
    });
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
      stripe = window.Stripe(CFG.stripe);
      // Deferred: the fields appear now, the charge is created at confirm
      // time, so the amount can follow the basket without opening an intent
      // for every change.
      elements = stripe.elements({ mode: 'payment', currency: CFG.currency, amount: total,
                                   appearance: { theme: 'flat' } });
      /* "tabs", not "tab". Stripe accepts accordion, tabs or auto and throws
         on anything else — and the throw lands in the catch below, which says
         the form could not be LOADED. So the script had loaded, the key was
         right, Elements was fine, and the checkout told every customer to pay
         another way. One letter, and the only way to see it was to open the
         checkout in a browser with live keys behind it. */
      elements.create('payment', { layout: 'tabs' }).mount(cardBox);
      cardBox.hidden = false;
      // only once it is actually up, so a failure can be tried again rather
      // than latching the checkout into "no cards" for the rest of the visit
      mounted = true;
    }).catch(function (e) {
      /* Say what went wrong, not what we assume went wrong. This message read
         "could not be loaded" for every failure, including the one where the
         script had loaded perfectly and Stripe was refusing an argument we
         had spelled wrong — so the checkout described a network problem that
         did not exist, and the real sentence, which named the mistake exactly,
         was thrown away. */
      var why = e && e.message ? e.message : '';
      say('The card form could not be shown' + (why ? ' — ' + why : '')
        + '. Please choose another way to pay, or tell us and we will send an invoice.');
      if (window.console && console.error) console.error('card form:', e);
    });
  }

  /* Set the moment a gateway says it has the money, and never unset.
     Everything after that point used to re-enable the button on any failure —
     the order failing to save, the connection dropping on the way back — and
     the customer, reading an error, would press it again and be charged a
     second time for the same basket. An error after the money has moved is
     not something to retry; it is something to tell somebody about. */
  var charged = false;

  function payByCard() {
    say('');
    placeBtn.disabled = true;

    // Kept outside the chain: the catch needs it to know which order was paid
    // for when the step after the payment is the one that failed.
    var reference = '';

    elements.submit().then(function (result) {
      if (result.error) throw new Error(result.error.message);
      return post(orderPayload({ action: 'start', payment: 'stripe' }));
    }).then(function (started) {
      reference = started.reference || '';
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
        // Stripe has the money from here on.
        charged = true;
        return post({ action: 'finish', _form: formToken(),
                      reference: started.reference,
                      intent: result.paymentIntent.id });
      });
    }).then(function (done) {
      if (!done.ok) throw new Error(done.error);
      localStorage.removeItem('argflex.cart');
      location.href = '/checkout/?ok=' + encodeURIComponent(done.reference);
    }).catch(function (e) {
      /* The money is already gone and something after it went wrong. Standing
         on the checkout under a red box and a dead button is the worst place
         to leave somebody who has just paid: the frozen basket is still on
         disk and the gateway's webhook finishes the job seconds later, so the
         thank-you page — which says exactly that — is the truthful place to
         be. The server's own words go to the console for whoever has to look.
         Nothing is retried and nothing is charged twice. */
      if (charged && reference) {
        if (window.console && e && e.message) console.error('finishing ' + reference + ':', e.message);
        localStorage.removeItem('argflex.cart');
        location.href = '/checkout/?ok=' + encodeURIComponent(reference);
        return;
      }
      placeBtn.disabled = charged;
      if (charged) placeBtn.textContent = 'Paid — do not pay again';
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
            return post({ action: 'finish', _form: formToken(),
                          reference: payBox.dataset.reference,
                          paypal_order: data.orderID })
              .then(function (done) {
                if (!done.ok) { say(done.error); return; }
                localStorage.removeItem('argflex.cart');
                location.href = '/checkout/?ok=' + encodeURIComponent(done.reference);
              })
              /* The capture is made by our server, so losing the answer here
                 does not tell us whether the money moved. Saying nothing left
                 the customer looking at a spinner that stopped, and pressing
                 again. PayPal refuses a second capture of the same approval,
                 so they cannot be charged twice — but they can be left with a
                 payment they made and a page that never admitted it. */
              .catch(function () {
                say('We did not hear back about that payment. It may well have gone '
                  + 'through — please do not pay again; ring us with reference '
                  + payBox.dataset.reference + ' and we will check.');
              });
          },

          onError: function (err) {
            /* The SDK routes a throw from createOrder here as well, and
               createOrder has already put the real reason on screen — "please
               enter your name", "that way of paying is not available". This
               used to paint over it with a sentence that says nothing, so a
               form with an empty field read as a broken gateway. If something
               is already showing, it is the better message: leave it.

               And the error is REPORTED rather than dropped. It arrives with a
               message from PayPal most of the time, and that was going
               straight in the bin. */
            if (window.console) console.error('PayPal:', err);

            if (noteBox && !noteBox.hidden && noteBox.textContent.trim() !== '') return;

            var why = err && err.message ? String(err.message).trim() : '';
            say('PayPal could not complete that. Nothing has been charged.'
              + (why ? ' ' + why.slice(0, 200) : ''));
          }
        }).render(payBox);
        payBox.hidden = false;
      })
      .catch(function (e) {
        if (window.console) console.error('PayPal SDK:', e);
        say('PayPal could not be loaded. Please choose another way to pay.'
          + (e && e.message ? ' (' + String(e.message).slice(0, 160) + ')' : ''));
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
