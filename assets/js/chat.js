/* The chat bubble: three questions, then WhatsApp.
 *
 * Kept out of site.js on purpose. site.js runs the basket and the checkout —
 * the things an order depends on — and a fault in a marketing widget must not
 * be able to take those down with it.
 *
 * The conversation is a tiny state machine rather than a chain of callbacks,
 * because "go back one step" and "start again" are then one line each instead
 * of an unwinding problem.
 */
(function () {
  var root = document.querySelector('[data-chat]');
  if (!root) return;

  var openBtn = root.querySelector('[data-chat-open]');
  var panel   = root.querySelector('.chat-pn');
  var log     = root.querySelector('[data-chat-log]');
  var form    = root.querySelector('[data-chat-form]');
  var say     = root.querySelector('[data-chat-say]');
  var trap    = root.querySelector('[data-chat-trap]');
  var picksEl = root.querySelector('[data-chat-picks]');

  var PICKS = [];
  try { PICKS = JSON.parse(picksEl.textContent) || []; } catch (e) { PICKS = []; }

  var answers = {product: '', name: '', phone: ''};
  var step = 'product';
  var busy = false;

  /* ------------------------------------------------------------- drawing */

  function bubble(text, who) {
    var el = document.createElement('div');
    el.className = 'chat-msg ' + (who || 'bot');
    el.textContent = text;
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  function choices(items, onPick) {
    var wrap = document.createElement('div');
    wrap.className = 'chat-picks';
    items.forEach(function (label) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chat-pick';
      b.textContent = label;
      b.addEventListener('click', function () {
        // the chosen one stays as what the visitor said; the rest go
        wrap.remove();
        bubble(label, 'me');
        onPick(label);
      });
      wrap.appendChild(b);
    });
    log.appendChild(wrap);
    log.scrollTop = log.scrollHeight;
  }

  function askingFor(what) {
    say.type = what === 'phone' ? 'tel' : 'text';
    say.autocomplete = what === 'name' ? 'name' : (what === 'phone' ? 'tel' : 'off');
    say.placeholder = what === 'name'  ? 'Your name…'
                    : what === 'phone' ? 'Your phone number…'
                    : 'Write a message…';
  }

  /* ------------------------------------------------------------- the flow */

  function start() {
    log.innerHTML = '';
    answers = {product: '', name: '', phone: ''};
    step = 'product';
    form.hidden = false;
    askingFor('product');
    bubble('Hello. Tell us what you need and we will come back with a price and a lead time — usually the same working day.');
    bubble('What are you looking for?');
    choices(PICKS, function (label) { answers.product = label; askName(); });
  }

  function askName() {
    step = 'name';
    askingFor('name');
    bubble('Thanks. And your name?');
    say.focus();
  }

  function askPhone() {
    step = 'phone';
    askingFor('phone');
    bubble('A phone number we can reach you on?');
    say.focus();
  }

  function finish(wa) {
    step = 'done';
    form.hidden = true;

    bubble('Got it — ' + answers.name + ', ' + answers.phone + '. We have your enquiry either way.');

    if (!wa) {                       // no number configured, or a bot was caught
      bubble('We will be in touch shortly.');
      return;
    }

    bubble('Carry on in WhatsApp and we can send photos and prices straight back:');
    var a = document.createElement('a');
    a.className = 'chat-wa';
    a.href = wa;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = 'Continue in WhatsApp';
    log.appendChild(a);
    log.scrollTop = log.scrollHeight;
  }

  /* ---------------------------------------------------------- the sending */

  function send() {
    if (busy) return;
    busy = true;

    var waiting = bubble('Sending…');
    var body = new URLSearchParams({
      _form:   root.dataset.token,
      product: answers.product,
      name:    answers.name,
      phone:   answers.phone,
      website: trap ? trap.value : '',
    });

    fetch(root.dataset.endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body,
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        waiting.remove();
        if (d && d.ok) { finish(d.wa || ''); return; }
        /* A refusal is the server's to explain — a stale token and six
           enquiries in an hour need different answers from the visitor. */
        bubble(d && d.say ? d.say : 'Something went wrong sending that. Please try once more.');
        step = 'phone';
        askingFor('phone');
      })
      .catch(function () {
        waiting.remove();
        bubble('We could not send that just now. Please call us instead.');
      })
      .then(function () { busy = false; });
  }

  /* ------------------------------------------------------------- plumbing */

  function open() {
    panel.hidden = false;
    openBtn.setAttribute('aria-expanded', 'true');
    root.classList.add('is-open');
    if (!log.childNodes.length) start();
    else say.focus();
  }

  function close() {
    panel.hidden = true;
    openBtn.setAttribute('aria-expanded', 'false');
    root.classList.remove('is-open');
    openBtn.focus();
  }

  openBtn.addEventListener('click', function () { panel.hidden ? open() : close(); });
  root.querySelector('[data-chat-close]').addEventListener('click', close);
  root.querySelector('[data-chat-reset]').addEventListener('click', function () { start(); say.focus(); });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !panel.hidden) close();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = say.value.trim();
    if (!text) return;
    say.value = '';
    bubble(text, 'me');

    if (step === 'product') { answers.product = text; askName(); return; }
    if (step === 'name')    { answers.name = text;    askPhone(); return; }
    if (step === 'phone')   { answers.phone = text;   send();     return; }
  });
})();
