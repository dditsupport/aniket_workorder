<?php
use App\Csrf;
use App\Helpers;

// JSON catalog for the line editor (read by inline JS)
$catalog = [
    'FG' => array_map(fn($p) => [
        'id' => (int)$p['id'], 'code' => $p['code'], 'name' => $p['name'],
        'unit' => $p['unit'], 'price' => (float)$p['base_price'], 'stock' => (float)$p['stock_qty'],
    ], $products),
    'RM' => array_map(fn($p) => [
        'id' => (int)$p['id'], 'code' => $p['code'], 'name' => $p['name'],
        'unit' => $p['unit'], 'price' => (float)$p['sale_price'], 'stock' => (float)$p['stock_qty'],
    ], $rms),
];
?>
<h2>New Sale</h2>
<form method="post" action="<?= Helpers::esc(Helpers::url('/sales')) ?>" class="card card-body">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-5">
      <label class="form-label">Customer</label>
      <select class="form-select" name="customer_id" id="sale_customer" required>
        <option value="">— select —</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= Helpers::esc($c['code'] . ' — ' . $c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Date</label>
      <input type="date" class="form-control" name="sale_date" value="<?= Helpers::esc(date('Y-m-d')) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Notes</label>
      <input class="form-control" name="notes" maxlength="255">
    </div>
  </div>

  <hr>

  <h5>Items</h5>
  <p class="text-secondary small mb-2">Each line: kind (RM or Product), item, qty, unit price (auto-filled, editable). Stock is reduced when you save.</p>
  <div class="table-responsive">
  <table class="table table-sm align-middle" id="lines">
    <thead><tr>
      <th style="width:90px">Kind</th><th>Item</th>
      <th style="width:110px" class="text-num">Qty</th>
      <th style="width:120px" class="text-num">Unit price</th>
      <th style="width:130px" class="text-num">Line total</th>
      <th></th>
    </tr></thead>
    <tbody></tbody>
  </table>
  </div>
  <button type="button" class="btn btn-outline-secondary btn-sm" id="addLine">+ Add line</button>

  <div class="text-end mt-3">
    <h4>Total: <span id="grand">0.00</span></h4>
  </div>

  <div class="mt-3">
    <button class="btn btn-primary">Save Sale</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/sales')) ?>">Cancel</a>
  </div>
</form>

<script>
(function () {
  const CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>;
  const tbody = document.querySelector('#lines tbody');
  const grand = document.getElementById('grand');
  const cust  = document.getElementById('sale_customer');
  const priceUrl = '<?= Helpers::esc(Helpers::url('/api/sale-price')) ?>';

  function buildItemOptions(kind) {
    return CATALOG[kind].map(it =>
      `<option value="${it.id}" data-price="${it.price}" data-unit="${it.unit}" data-stock="${it.stock}">${it.code} — ${it.name} (stock ${it.stock} ${it.unit})</option>`
    ).join('');
  }

  function recalc() {
    let total = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const q = parseFloat(tr.querySelector('.qty').value || '0');
      const p = parseFloat(tr.querySelector('.up').value || '0');
      const lt = q * p;
      tr.querySelector('.lt').textContent = lt.toFixed(2);
      total += lt;
    });
    grand.textContent = total.toFixed(2);
  }

  async function refreshPrice(tr) {
    if (!cust.value) return;
    const kind = tr.querySelector('.kind').value;
    const item = tr.querySelector('.item').value;
    if (!item) return;
    try {
      const r = await fetch(priceUrl + '?customer_id=' + cust.value + '&item_kind=' + kind + '&item_id=' + item, {credentials: 'same-origin'});
      const j = await r.json();
      if (j && j.price !== null) {
        tr.querySelector('.up').value = Number(j.price).toFixed(2);
        recalc();
      }
    } catch (e) {}
  }

  function addLine() {
    const idx = tbody.children.length;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select class="form-select form-select-sm kind" name="line_kind[]">
          <option value="FG">Product</option>
          <option value="RM">RM</option>
        </select>
      </td>
      <td>
        <select class="form-select form-select-sm item" name="line_item_id[]" required>
          ${buildItemOptions('FG')}
        </select>
      </td>
      <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm text-end qty" name="line_qty[]" required></td>
      <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end up" name="line_price[]"></td>
      <td class="text-num lt">0.00</td>
      <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger del">×</button></td>
    `;
    tbody.appendChild(tr);
    const kindSel = tr.querySelector('.kind');
    const itemSel = tr.querySelector('.item');
    const qty     = tr.querySelector('.qty');
    const up      = tr.querySelector('.up');
    kindSel.addEventListener('change', () => {
      itemSel.innerHTML = buildItemOptions(kindSel.value);
      refreshPrice(tr);
    });
    itemSel.addEventListener('change', () => refreshPrice(tr));
    qty.addEventListener('input', recalc);
    up.addEventListener('input', recalc);
    tr.querySelector('.del').addEventListener('click', () => { tr.remove(); recalc(); });
    refreshPrice(tr);
  }

  document.getElementById('addLine').addEventListener('click', addLine);
  cust.addEventListener('change', () => tbody.querySelectorAll('tr').forEach(refreshPrice));
  addLine(); // start with one line
})();
</script>
