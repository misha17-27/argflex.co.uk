<?php
/** @var string $viewName */
$user  = current_user();
$note  = flash();
$bare  = in_array($viewName, ['login', 'setup'], true);
$here  = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$isOn  = fn(string $prefix) => $prefix === '/admin/'
    ? $here === '/admin/' || $here === '/admin'
    : str_starts_with($here, $prefix);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? 'Admin') . ' — ' . SITE_NAME) ?></title>
<link rel="icon" href="/assets/img/favicon/fav-32x32.png" sizes="32x32">
<link rel="stylesheet" href="/admin/assets/admin.css?v=<?= e(ASSET_VER) ?>">
</head>
<body class="<?= $bare ? 'bare' : '' ?>">

<?php if ($bare): ?>

  <main class="auth">
    <?php render_view($viewName, $viewVars); ?>
  </main>

<?php else: ?>

  <div class="shell">
    <aside class="side">
      <a class="brand" href="/admin/">
        <img src="/assets/img/site/logo.png" alt="" width="120" height="32">
        <span>Admin</span>
      </a>
      <?php
      $navIcons = [
        'dashboard'   => '<path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'orders'      => '<path d="M4 5h2l2.2 10.4a2 2 0 0 0 2 1.6h6.9a2 2 0 0 0 2-1.55L21 8H6.5"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/>',
        'reports'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'submissions' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'customers'   => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20c0-3.4 2.9-5.5 6.5-5.5s6.5 2.1 6.5 5.5"/><path d="M16 5.2a3.4 3.4 0 0 1 0 5.6"/><path d="M18 14.8c2.1.7 3.5 2.4 3.5 5.2"/>',
        'products'    => '<path d="M3 7l9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/>',
        'categories'  => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'coupons'     => '<path d="M4 9V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/><path d="M14 8.5l-4 7"/>',
        'reviews'     => '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1.1 5.8-5.3-2.8-5.3 2.8 1.1-5.8L3.5 9.7l5.9-.8z"/>',
        'attributes'  => '<path d="M4 7h6M4 12h10M4 17h7"/><circle cx="17" cy="7" r="2"/><circle cx="19" cy="17" r="2"/>',
        'pages'       => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
        'posts'       => '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'media'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-6 6"/>',
        'seo'         => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'security'    => '<path d="M12 3l7.5 3v5.2c0 4.6-3.1 8.3-7.5 9.8-4.4-1.5-7.5-5.2-7.5-9.8V6z"/><path d="M9 12l2 2 4-4"/>',
        'users'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'status'      => '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
        'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.3 1a7 7 0 0 0-1.7-1L14.5 3h-4l-.4 2.6a7 7 0 0 0-1.7 1l-2.3-1-2 3.4L6 11a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 1.7 1l.4 2.6h4l.4-2.6a7 7 0 0 0 1.7-1l2.3 1 2-3.4-2-1.5c.06-.33.1-.66.1-1z"/>',
      ];
      $navGroups = [
        'Overview' => [
          ['/admin/',            'dashboard',   'Dashboard'],
          ['/admin/orders',      'orders',      'Orders'],
          ['/admin/submissions', 'submissions', 'Enquiries'],
          ['/admin/customers',    'customers',   'Customers'],
          ['/admin/reports',      'reports',     'Reports'],
        ],
        'Content' => [
          ['/admin/products',   'products',   'Products'],
          ['/admin/categories', 'categories', 'Categories'],
          ['/admin/attributes', 'attributes', 'Attributes'],
          ['/admin/coupons',    'coupons',    'Discount codes'],
          ['/admin/reviews',    'reviews',    'Reviews'],
          ['/admin/pages',      'pages',      'Pages'],
          ['/admin/posts',      'posts',      'Blog'],
          ['/admin/media',      'media',      'Images'],
        ],
        'Settings' => [
          ['/admin/seo',      'seo',      'SEO'],
          ['/admin/settings', 'settings', 'Settings'],
          ['/admin/security', 'security', 'Security'],
          ['/admin/users',    'users',    'Users'],
          ['/admin/status',   'status',   'System status'],
        ],
      ];
      $unreadCount  = function_exists('unread_submissions') ? unread_submissions() : 0;
      $pendingCount = function_exists('all_reviews')
          ? count(array_filter(all_reviews(), fn($r) => $r['status'] === 'pending')) : 0;
      ?>
      <nav>
        <?php foreach ($navGroups as $groupLabel => $links): ?>
          <?php
          $visible = array_filter($links, fn($l) => !in_array(trim(str_replace('/admin/', '', $l[0]), '/'), ADMIN_ONLY, true) || is_admin());
          if (!$visible) continue;
          ?>
          <div class="navgroup"><?= e($groupLabel) ?></div>
          <?php foreach ($visible as [$href, $icon, $label]): ?>
            <a href="<?= e($href) ?>" class="<?= $isOn($href) ? 'on' : '' ?>">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><?= $navIcons[$icon] ?></svg>
              <?= e($label) ?>
              <?php if ($icon === 'submissions' && $unreadCount): ?><i class="tally"><?= (int) $unreadCount ?></i><?php endif; ?>
              <?php if ($icon === 'reviews' && $pendingCount): ?><i class="tally"><?= (int) $pendingCount ?></i><?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
      <div class="side-foot">
        <a href="/" target="_blank" rel="noopener">View site ↗</a>
        <a href="/admin/account"><?= e($user['name'] ?? '') ?><?= is_admin() ? '' : ' · editor' ?></a>
        <a href="/admin/logout" class="out">Sign out</a>
      </div>
    </aside>

    <main class="main">
      <header class="top">
        <h1><?= e($title ?? 'Admin') ?></h1>
        <?php if (!empty($actions)) echo $actions; ?>
      </header>

      <?php if ($note): ?>
        <div class="flash <?= e($note['kind']) ?>"><?= e($note['message']) ?></div>
      <?php endif; ?>

      <?php render_view($viewName, $viewVars); ?>
    </main>
  </div>

<?php endif; ?>

<script>
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-row]');
  if (!add) return;
  var tpl = document.querySelector(add.dataset.addRow);
  var row = tpl.content.cloneNode(true);
  tpl.parentNode.insertBefore(row, tpl);
});
document.addEventListener('click', function (e) {
  var rm = e.target.closest('[data-remove-row]');
  if (rm) rm.closest('[data-row]').remove();
});

/* Turn any textarea marked data-rich into a small visual editor.
   The textarea stays in the form and is kept in sync, so saving is unchanged
   and switching the editor off would lose nothing. */
/* Build one option row per combination of the attributes ticked
   "used for options", keeping any price already typed against a match. */
var genBtn = document.getElementById('gen-variants');
if (genBtn) genBtn.addEventListener('click', function () {
  var groups = [];
  document.querySelectorAll('#attr-rows .attr-line').forEach(function (row) {
    // "used for options" is the last checkbox on the row; the ones before it
    // are the values themselves.
    var used = row.querySelector('.attr-head input[type=checkbox]');
    if (!used || !used.checked) return;

    var name = (row.querySelector('.attr-name').value || '').trim();

    // The ticked values, plus anything typed into the box underneath. Reading
    // only the typed box — as this did before the values became tick boxes —
    // found nothing at all on a product whose attributes were already set.
    var terms = [];
    row.querySelectorAll('.term-opt input:checked').forEach(function (box) {
      terms.push(box.value.trim());
    });
    (row.querySelector('.attr-terms').value || '').split(',').forEach(function (t) {
      t = t.trim();
      if (t && terms.indexOf(t) === -1) terms.push(t);
    });

    if (name && terms.length) groups.push({ name: name, terms: terms });
  });
  if (!groups.length) { alert('Tick at least one value on an attribute marked "used for options" first.'); return; }

  var combos = [[]];
  groups.forEach(function (g) {
    var next = [];
    combos.forEach(function (combo) {
      g.terms.forEach(function (t) { next.push(combo.concat(g.name + ': ' + t)); });
    });
    combos = next;
  });
  if (combos.length > 200) { alert('That would make ' + combos.length + ' options. Trim the attributes first.'); return; }

  /** What a row currently names, whether by lists or by a typed label. */
  function labelOf(row) {
    var picks = row.querySelectorAll('.var-picks select');
    if (picks.length) {
      return Array.prototype.map.call(picks, function (s) {
        return s.getAttribute('aria-label') + ': ' + s.value;
      }).join(', ');
    }
    var field = row.querySelector('input[type=text]');
    return field ? field.value.trim() : '';
  }

  // Prices already entered are kept where the option still exists.
  var kept = {};
  document.querySelectorAll('#variant-rows .row-line').forEach(function (row) {
    var label = labelOf(row);
    var money = row.querySelectorAll('input[type=number]');
    if (label) kept[label] = { price: money[0] ? money[0].value : '',
                               sale:  money[1] ? money[1].value : '' };
  });

  var host = document.getElementById('variant-rows');
  host.querySelectorAll('.row-line').forEach(function (r) { r.remove(); });
  var tpl = document.getElementById('variant-tpl');

  combos.forEach(function (combo, i) {
    var label = combo.join(', ');
    var row   = tpl.content.cloneNode(true).querySelector('.row-line');

    // Point the row at its own index, and set each list to this combination.
    row.querySelectorAll('.var-picks select').forEach(function (sel, axis) {
      var axisName = groups[axis] ? groups[axis].name : sel.getAttribute('aria-label');
      sel.name = 'variant[' + i + '][pick][' + axisName + ']';
      var term = (combo[axis] || '').split(': ').slice(1).join(': ');
      if (term) {
        // the attribute may have gained a value since the page was drawn
        if (!Array.prototype.some.call(sel.options, function (o) { return o.value === term; })) {
          sel.add(new Option(term, term));
        }
        sel.value = term;
      }
    });

    var text = row.querySelector('input[type=text]');
    if (text) { text.name = 'variant[' + i + '][label]'; text.value = label; }

    var money = row.querySelectorAll('input[type=number]');
    if (money[0]) { money[0].name = 'variant[' + i + '][price]'; money[0].value = (kept[label] || {}).price || ''; }
    if (money[1]) { money[1].name = 'variant[' + i + '][sale]';  money[1].value = (kept[label] || {}).sale  || ''; }

    host.insertBefore(row, tpl);
  });
});

/* Live character counts and Google preview */
document.querySelectorAll('[data-counter]').forEach(function (field) {
  var out  = document.querySelector(field.dataset.counter);
  var serp = field.dataset.serp
    ? document.querySelector('[data-serp-' + field.dataset.serp + ']') : null;
  var fallback = serp ? serp.textContent : '';
  field.addEventListener('input', function () {
    if (out) out.textContent = field.value.length;
    if (serp) serp.textContent = field.value.trim() || fallback;
  });
});

/* Pick an image from the library into the last empty row, or a new one */
var picker = document.getElementById('picker');
if (picker) {
  var target = null;
  document.querySelectorAll('[data-pick-image]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      target = document.querySelector(btn.dataset.pickImage);
      picker.hidden = false;
    });
  });
  picker.querySelectorAll('[data-picker-close]').forEach(function (el) {
    el.addEventListener('click', function () { picker.hidden = true; });
  });
  picker.querySelectorAll('.pick').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!target) return;
      var empty = Array.prototype.filter.call(
        target.querySelectorAll('input[type=text]'), function (i) { return !i.value.trim(); })[0];
      if (empty) {
        empty.value = btn.dataset.src;
      } else {
        var tpl = target.querySelector('template');
        var row = tpl.content.cloneNode(true).querySelector('.row-line');
        row.querySelector('input').value = btn.dataset.src;
        target.insertBefore(row, tpl);
      }
      picker.hidden = true;
    });
  });
}

/* Settings -> General: the country lists only matter for "selected" */
document.querySelectorAll('select[data-toggle]').forEach(function (sel) {
  sel.addEventListener('change', function () {
    var box = document.querySelector(sel.dataset.toggle);
    if (box) box.hidden = sel.value !== 'selected';
  });
});

/* A checkbox that reveals the fields it controls */
document.querySelectorAll('input[type=checkbox][data-toggle-block]').forEach(function (box) {
  box.addEventListener('change', function () {
    var block = document.querySelector(box.dataset.toggleBlock);
    if (block) block.hidden = !box.checked;
  });
});

/* Simple or variable, and the fields that belong to each.

   The hidden cards have their inputs disabled as well, so a product marked
   simple does not quietly post the option rows it is no longer showing and
   get priced from them. */
(function () {
  var picks = document.querySelectorAll('[data-type-pick]');
  if (!picks.length) return;

  var optionsCard = null;
  document.querySelectorAll('.card h2').forEach(function (h) {
    if (h.textContent.trim() === 'Price and options') optionsCard = h.closest('.card');
  });

  function reflect() {
    var variable = document.querySelector('[data-type-pick][value=variable]').checked;

    document.querySelectorAll('[data-when-variable]').forEach(function (card) {
      card.hidden = !variable;
      card.querySelectorAll('input, select, textarea').forEach(function (field) {
        field.disabled = !variable;
      });
    });

    if (!optionsCard) return;
    // the single price is for a simple product; the option rows for a variable one
    optionsCard.querySelectorAll('#variant-rows input, #gen-variants').forEach(function (field) {
      field.disabled = !variable;
    });
    var head = optionsCard.querySelector('.var-head');
    var rows = optionsCard.querySelector('#variant-rows');
    var gen  = optionsCard.querySelector('.gen-bar');
    [head, rows, gen].forEach(function (el) { if (el) el.hidden = !variable; });
    optionsCard.querySelectorAll('h3').forEach(function (h) {
      if (h.textContent.trim() === 'Options') h.hidden = !variable;
    });
    var add = optionsCard.querySelector('[data-add-row="#variant-tpl"]');
    if (add) add.hidden = !variable;

    ['price', 'sale_price'].forEach(function (id) {
      var field = document.getElementById(id);
      if (field) field.closest('div').hidden = variable;
    });
  }

  picks.forEach(function (p) { p.addEventListener('change', reflect); });
  reflect();
})();

/* Changing which attribute a row is for redraws its values. */
document.addEventListener('change', function (e) {
  if (!e.target.matches('select.attr-name')) return;
  var row   = e.target.closest('[data-row]');
  var boxes = row && row.querySelector('.term-boxes');
  var terms = (window.ARGFLEX_TERMS || {})[e.target.value] || [];
  if (!boxes) return;

  var field = row.querySelector('.attr-terms');
  var index = (field.name.match(/attr\[(\d+)\]/) || [])[1] || '0';
  var safe  = function (t) {
    return String(t).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  };

  boxes.innerHTML =
    '<div class="term-drop" data-term-drop>'
    + '<button type="button" class="term-toggle" aria-expanded="false" aria-label="Values of '
    + safe(e.target.value) + '">'
    + '<span class="term-chips" data-term-summary>Choose the values this product comes in</span>'
    + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
    + ' stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>'
    + '<div class="term-panel" hidden><div class="term-tools">'
    + '<button type="button" class="ghost" data-pick-all>Select all</button>'
    + '<button type="button" class="ghost" data-pick-none>Select none</button></div>'
    + terms.map(function (t) {
        return '<label class="check term-opt"><input type="checkbox" name="attr['
             + index + '][pick][]" value="' + safe(t) + '"><span>' + safe(t) + '</span></label>';
      }).join('')
    + '</div></div>';
});

/* The dropdown of values: opening it, saying what is picked, and picking
   every one or none — twenty-five bores is a lot of clicking either way. */
function termSummary(drop) {
  var out = drop.querySelector('[data-term-summary]');
  if (!out) return;
  var picked = Array.prototype.map.call(
    drop.querySelectorAll('.term-opt input:checked'), function (b) { return b.value; });
  out.textContent = picked.length ? picked.join(', ')
                                  : 'Choose the values this product comes in';
}

document.addEventListener('click', function (e) {
  var toggle = e.target.closest('.term-toggle');
  if (toggle) {
    var drop = toggle.closest('[data-term-drop]');
    var open = toggle.getAttribute('aria-expanded') === 'true';
    // only one open at a time, or two panels overlap
    document.querySelectorAll('[data-term-drop]').forEach(function (d) {
      d.querySelector('.term-toggle').setAttribute('aria-expanded', 'false');
      d.querySelector('.term-panel').hidden = true;
    });
    if (!open) {
      toggle.setAttribute('aria-expanded', 'true');
      drop.querySelector('.term-panel').hidden = false;
    }
    return;
  }

  var all  = e.target.closest('[data-pick-all]');
  var none = e.target.closest('[data-pick-none]');
  if (all || none) {
    var box = (all || none).closest('[data-term-drop]');
    box.querySelectorAll('.term-opt input').forEach(function (b) { b.checked = !!all; });
    termSummary(box);
    return;
  }

  // a click anywhere else closes what is open
  if (!e.target.closest('[data-term-drop]')) {
    document.querySelectorAll('[data-term-drop]').forEach(function (d) {
      d.querySelector('.term-toggle').setAttribute('aria-expanded', 'false');
      d.querySelector('.term-panel').hidden = true;
    });
  }
});

document.addEventListener('change', function (e) {
  if (!e.target.matches('.term-opt input')) return;
  termSummary(e.target.closest('[data-term-drop]'));
});

document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('[data-term-drop] .term-panel:not([hidden])').forEach(function (panel) {
    var drop = panel.closest('[data-term-drop]');
    drop.querySelector('.term-toggle').setAttribute('aria-expanded', 'false');
    panel.hidden = true;
    drop.querySelector('.term-toggle').focus();
  });
});

/* A colour swatch and its hex field follow each other */
document.querySelectorAll('input[data-syncs]').forEach(function (swatch) {
  var field = document.querySelector(swatch.dataset.syncs);
  if (!field) return;
  swatch.addEventListener('input', function () { field.value = swatch.value; });
  field.addEventListener('input', function () {
    if (/^#[0-9a-fA-F]{6}$/.test(field.value)) swatch.value = field.value;
  });
});

/* Delivery zones and payment methods are repeaters. New rows get their index
   stamped in from a counter, because the names are nested arrays and cloning
   a row blindly would make two rows share one index. */
var rowSeq = 0;
function stamp(tplId, marks) {
  var html = document.getElementById(tplId).innerHTML;
  Object.keys(marks).forEach(function (mark) { html = html.split(mark).join(marks[mark]); });
  return html;
}
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-zone]');
  if (!add) return;
  add.insertAdjacentHTML('beforebegin', stamp('zone-tpl', { __z__: 'n' + (rowSeq++) }));
});
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-method]');
  if (!add) return;
  var zone = add.closest('[data-zone]');
  zone.querySelector('.ship-rows').insertAdjacentHTML('beforeend',
    stamp('method-tpl', { __z__: zone.dataset.zone, __m__: 'n' + (rowSeq++) }));
});
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-rate]');
  if (!add) return;
  add.insertAdjacentHTML('beforebegin', stamp('rate-tpl', { __r__: 'n' + (rowSeq++) }));
});
document.addEventListener('click', function (e) {
  var rr = e.target.closest('[data-remove-rate]');
  if (rr) rr.closest('[data-rate]').remove();
});
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-pay]');
  if (!add) return;
  add.insertAdjacentHTML('beforebegin', stamp('pay-tpl', { __p__: 'n' + (rowSeq++) }));
});
document.addEventListener('click', function (e) {
  var rm = e.target.closest('[data-remove-method]');
  if (rm) rm.closest('[data-method]').remove();
  var rz = e.target.closest('[data-remove-zone]');
  if (rz) rz.closest('[data-zone]').remove();
  var rp = e.target.closest('[data-remove-pay]');
  if (rp) rp.closest('[data-pay]').remove();
});

var checkAll = document.querySelector('[data-check-all]');
if (checkAll) {
  checkAll.addEventListener('change', function () {
    document.querySelectorAll('#bulk input[name="slugs[]"]').forEach(function (b) {
      b.checked = checkAll.checked;
    });
  });
}

document.querySelectorAll('textarea[data-rich]').forEach(function (area) {
  var wrap = document.createElement('div');
  wrap.className = 'rt-wrap';
  var bar = document.createElement('div');
  bar.className = 'rt-bar';
  var edit = document.createElement('div');
  edit.className = 'rt-area';
  edit.contentEditable = 'true';
  edit.innerHTML = area.value;

  [['bold', '<b>B</b>', 'Bold'],
   ['italic', '<i>I</i>', 'Italic'],
   ['formatBlock:H2', 'H2', 'Heading'],
   ['formatBlock:H3', 'H3', 'Smaller heading'],
   ['formatBlock:P', '¶', 'Paragraph'],
   ['insertUnorderedList', '• list', 'Bulleted list'],
   ['insertOrderedList', '1. list', 'Numbered list'],
   ['createLink', '🔗', 'Link'],
   ['unlink', 'unlink', 'Remove link'],
   ['removeFormat', 'clear', 'Clear formatting']].forEach(function (item) {
    var b = document.createElement('button');
    b.type = 'button';
    b.innerHTML = item[1];
    b.title = item[2];
    b.addEventListener('mousedown', function (ev) {
      ev.preventDefault();
      edit.focus();
      var parts = item[0].split(':');
      if (parts[0] === 'createLink') {
        var url = prompt('Link address:', 'https://');
        if (url) document.execCommand('createLink', false, url);
      } else if (parts[0] === 'formatBlock') {
        document.execCommand('formatBlock', false, parts[1]);
      } else {
        document.execCommand(parts[0], false, null);
      }
      area.value = edit.innerHTML;
    });
    bar.appendChild(b);
  });

  edit.addEventListener('input', function () { area.value = edit.innerHTML; });
  edit.addEventListener('blur',  function () { area.value = edit.innerHTML; });
  var form = area.closest('form');
  if (form) form.addEventListener('submit', function () { area.value = edit.innerHTML; });

  area.style.display = 'none';
  area.parentNode.insertBefore(wrap, area);
  wrap.appendChild(bar);
  wrap.appendChild(edit);
  wrap.appendChild(area);

  var toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'ghost';
  toggle.style.marginTop = '8px';
  toggle.textContent = 'Edit the HTML instead';
  toggle.addEventListener('click', function () {
    var showingSource = area.style.display !== 'none';
    if (showingSource) { edit.innerHTML = area.value; }
    area.style.display = showingSource ? 'none' : 'block';
    edit.style.display = showingSource ? 'block' : 'none';
    bar.style.display  = showingSource ? 'flex' : 'none';
    toggle.textContent = showingSource ? 'Edit the HTML instead' : 'Back to the visual editor';
  });
  wrap.parentNode.insertBefore(toggle, wrap.nextSibling);
});
</script>
</body>
</html>
