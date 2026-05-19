<?php
use App\Csrf;
use App\Helpers;
?>
<h2>New Work Order</h2>
<form method="post" action="<?= Helpers::esc(Helpers::url('/work-orders')) ?>" class="card card-body" style="max-width:780px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Customer</label>
      <select class="form-select" name="customer_id" id="customer_id" required>
        <option value="">— select —</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= Helpers::esc($c['code'] . ' — ' . $c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Product</label>
      <select class="form-select" name="product_id" id="product_id" required>
        <option value="">— select —</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>" data-base="<?= Helpers::esc(number_format((float)$p['base_price'],2,'.','')) ?>">
            <?= Helpers::esc($p['code'] . ' — ' . $p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Quantity</label>
      <input class="form-control" type="number" step="0.001" min="0.001" name="quantity" id="quantity" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Unit price (auto if blank)</label>
      <input class="form-control" type="number" step="0.01" min="0" name="unit_price" id="unit_price" placeholder="auto">
    </div>
    <div class="col-md-4">
      <label class="form-label">Total (preview)</label>
      <input class="form-control" id="total_preview" readonly value="0.00">
    </div>
    <div class="col-12">
      <label class="form-label">Notes</label>
      <textarea class="form-control" name="notes" rows="2"></textarea>
    </div>
  </div>
  <p class="text-secondary small mt-3">The WO will be created in <strong>Draft</strong> status. Stock movement only happens when you move it to <em>In Progress</em>.</p>
  <div>
    <button class="btn btn-primary">Create Work Order</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/work-orders')) ?>">Cancel</a>
  </div>
</form>
<script>
(function () {
  const cust = document.getElementById('customer_id');
  const prod = document.getElementById('product_id');
  const qty  = document.getElementById('quantity');
  const up   = document.getElementById('unit_price');
  const tot  = document.getElementById('total_preview');

  async function refreshPrice() {
    if (!cust.value || !prod.value) return;
    try {
      const r = await fetch('<?= Helpers::esc(Helpers::url('/api/price')) ?>?customer_id=' + cust.value + '&product_id=' + prod.value, {credentials:'same-origin'});
      const j = await r.json();
      if (j && j.price !== null && !up.value) {
        up.placeholder = Number(j.price).toFixed(2) + ' (auto)';
        up.dataset.auto = j.price;
      }
      recalc();
    } catch (e) {}
  }
  function recalc() {
    const q = parseFloat(qty.value || '0');
    const p = parseFloat(up.value || up.dataset.auto || '0');
    tot.value = (q * p).toFixed(2);
  }
  cust.addEventListener('change', refreshPrice);
  prod.addEventListener('change', refreshPrice);
  qty.addEventListener('input', recalc);
  up.addEventListener('input', recalc);
})();
</script>
