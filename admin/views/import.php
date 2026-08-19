<p class="back"><a href="/admin/products">&larr; All products</a></p>

<form method="post" enctype="multipart/form-data" class="card pad-card narrow-card">
  <?= csrf_field() ?>
  <h2>Import products from CSV</h2>
  <p class="muted">
    Rows are matched on <code>slug</code>: a slug already in the catalogue is updated,
    a new one is added. Anything missing from the file is left untouched, so an
    import can never quietly empty the shop.
  </p>

  <label for="csv">CSV file</label>
  <input id="csv" name="csv" type="file" accept=".csv,text/csv" required>

  <p class="hint">
    Export first to get the exact columns:
    <code>slug, name, sku, status, featured, stock, categories, price_min, price_max, options, short, description, images, created</code>.
    Options look like <code>Length: 1m = 1.10 | Length: 50m = 55.00</code>.
  </p>

  <button type="submit">Import</button>
  <a class="ghost block" href="/admin/products/export">Download the current catalogue first</a>
</form>
