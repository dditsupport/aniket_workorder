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
$woData = array_map(fn($w) => [
    'id' => (int)$w['id'], 'customer_id' => (int)$w['customer_id'],
    'wo_number' => $w['wo_number'], 'product_id' => (int)$w['product_id'],
    'qty' => (float)$w['quantity'], 'status' => $w['status'],
    'product_code' => $w['product_code'], 'product_name' => $w['product_name'],
], $workorders ?? []);
?>
<h2>New Sale</h2>
<form method="post" action="<?= Helpers::esc(Helpers::url('/sales')) ?>" class="card card-body">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-4">
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
    <div class="col-md-5">
      <label class="form-label">Add from Work Order <small class="text-secondary">(optional)</small></label>
      <select class="form-select" id="sale_wo">
        <option value="">— pick a work order —</option>
        <?php foreach ($woData as $w): ?>
          <option value="<?= $w['id'] ?>" data-customer="<?= $w['customer_id'] ?>">
            <?= Helpers::esc($w['wo_number'] . ' — ' . $w['product_name'] . ' × ' . rtrim(rtrim(number_format($w['qty'],3,'.',''), '0'), '.') . ' (' . $w['status'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Adds the WO's product &amp; quantity as a line.</div>
    </div>
    <div class="col-12">
      <label class="form-label">Notes</label>
      <input class="form-control" name="notes" maxlength="255">
    </div>
  </div>

  <hr>

  <h5>Items</h5>
  <p class="text-secondary small mb-2">Each line: kind (RM or Product), item, qty, unit price (auto-filled, editable). Tick <strong>Qty disc</strong> to price that line by the quantity-break tier on the customer's price list. Stock is reduced when you save.</p>
  <div class="table-responsive">
  <table class="table table-sm align-middle" id="lines">
    <thead><tr>
      <th style="width:90px">Kind</th><th>Item</th>
      <th style="width:100px" class="text-num">Qty</th>
      <th style="width:80px" class="text-center">Qty disc</th>
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
  const WOS     = <?= json_encode($woData, JSON_UNESCAPED_UNICODE) ?>;
  const tbody = document.querySelector('#lines tbody');
  const grand = document.getElementById('grand');
  const cust  = document.getElementById('sale_customer');
  const woSel = document.getElementById('sale_wo');
  const priceUrl = '<?= Helpers::esc(Helpers::url('/api/sale-price')) ?>';

  function buildItemOptions(kind, selectedId) {
    return CATALOG[kind].map(it =>
      `<option value="${it.id}" data-price="${it.price}" data-unit="${it.unit}" data-stock="${it.stock}" ${String(it.id)===String(selectedId)?'selected':''}>${it.code} — ${it.name} (stock ${it.stock} ${it.unit})</option>`
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
    const qty  = tr.querySelector('.qty').value || '0';
    const tier = tr.querySelector('.qd').checked ? '1' : '0';
    if (!item) return;
    try {
      const url = priceUrl + '?customer_id=' + cust.value + '&item_kind=' + kind +
                  '&item_id=' + item + '&qty=' + encodeURIComponent(qty) + '&tier=' + tier;
      const r = await fetch(url, {credentials: 'same-origin'});
      const j = await r.json();
      if (j && j.price !== null) {
        tr.querySelector('.up').value = Number(j.price).toFixed(2);
        recalc();
      }
    } catch (e) {}
  }

  function addLine(opts) {
    opts = opts || {};
    const tr = document.createElement('tr');
    const kind = opts.kind || 'FG';
    tr.innerHTML = `
      <td>
        <select class="form-select form-select-sm kind" name="line_kind[]">
          <option value="FG" ${kind==='FG'?'selected':''}>Product</option>
          <option value="RM" ${kind==='RM'?'selected':''}>RM</option>
        </select>
      </td>
      <td>
        <select class="form-select form-select-sm item" name="line_item_id[]" required>
          ${buildItemOptions(kind, opts.itemId)}
        </select>
      </td>
      <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm text-end qty" name="line_qty[]" value="${opts.qty || ''}" required></td>
      <td class="text-center">
        <input type="hidden" name="line_qtydisc[]" class="qd-hidden" value="0">
        <input type="checkbox" class="form-check-input qd" title="Apply quantity-break tier">
      </td>
      <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end up" name="line_price[]"></td>
      <td class="text-num lt">0.00</td>
      <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger del">×</button></td>
    `;
    tbody.appendChild(tr);
    const kindSel = tr.querySelector('.kind');
    const itemSel = tr.querySelector('.item');
    const qty     = tr.querySelector('.qty');
    const up      = tr.querySelector('.up');
    const qd      = tr.querySelector('.qd');
    const qdHidden= tr.querySelector('.qd-hidden');
    kindSel.addEventListener('change', () => {
      itemSel.innerHTML = buildItemOptions(kindSel.value);
      refreshPrice(tr);
    });
    itemSel.addEventListener('change', () => refreshPrice(tr));
    qty.addEventListener('input', () => { recalc(); if (qd.checked) refreshPrice(tr); });
    up.addEventListener('input', recalc);
    qd.addEventListener('change', () => { qdHidden.value = qd.checked ? '1' : '0'; refreshPrice(tr); });
    tr.querySelector('.del').addEventListener('click', () => { tr.remove(); recalc(); });
    refreshPrice(tr);
    return tr;
  }

  document.getElementById('addLine').addEventListener('click', () => addLine());
  cust.addEventListener('change', () => tbody.querySelectorAll('tr').forEach(refreshPrice));

  // "Add from Work Order" — set the customer (if blank) and append a line.
  woSel.addEventListener('change', () => {
    const id = woSel.value;
    if (!id) return;
    const wo = WOS.find(w => String(w.id) === String(id));
    if (!wo) return;
    if (!cust.value) cust.value = String(wo.customer_id);
    addLine({ kind: 'FG', itemId: wo.product_id, qty: wo.qty });
    woSel.value = '';
  });

  addLine(); // start with one line
})();
</script>
