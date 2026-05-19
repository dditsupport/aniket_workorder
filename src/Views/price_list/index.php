<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<h2 class="mb-3">Customer Price List</h2>
<div class="row g-3">
  <div class="col-md-8">
    <div class="card"><div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead><tr><th>Customer</th><th>Kind</th><th>Item</th><th class="text-num">Price</th><th class="text-num">Default</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= Helpers::esc($r['customer_name']) ?> <small class="text-secondary">(<?= Helpers::esc($r['customer_code']) ?>)</small></td>
            <td><span class="badge text-bg-<?= $r['item_kind']==='FG' ? 'success' : 'info' ?>"><?= Helpers::esc($r['item_kind']) ?></span></td>
            <td><?= Helpers::esc($r['item_name']) ?> <small class="text-secondary">(<?= Helpers::esc($r['item_code']) ?>)</small></td>
            <td class="text-num"><?= Helpers::esc(Helpers::money($r['price'])) ?></td>
            <td class="text-num text-secondary"><?= Helpers::esc(Helpers::money($r['base_price'])) ?></td>
            <td class="text-end">
              <?php if (Auth::can('write')): ?>
              <form method="post" action="<?= Helpers::esc(Helpers::url('/price-list/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Remove this price?')">
                <?= Csrf::field() ?>
                <button class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-secondary p-3">No customer-specific prices yet — items use their default (RM sale price / Product base price).</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div></div>
  </div>
  <?php if (Auth::can('write')): ?>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h5>Add / Update price</h5>
      <form method="post" action="<?= Helpers::esc(Helpers::url('/price-list')) ?>">
        <?= Csrf::field() ?>
        <div class="mb-2"><label class="form-label">Customer</label>
          <select class="form-select" name="customer_id" required>
            <option value="">— select —</option>
            <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= Helpers::esc($c['code'] . ' — ' . $c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label">Item type</label>
          <select class="form-select" name="item_kind" id="pl_item_kind" required>
            <option value="FG">Product (Final)</option>
            <option value="RM">Raw Material</option>
          </select>
        </div>
        <div class="mb-2"><label class="form-label">Item</label>
          <select class="form-select" name="item_id" id="pl_item_id" required>
            <optgroup label="Products" data-kind="FG">
              <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>" data-kind="FG"><?= Helpers::esc($p['code'] . ' — ' . $p['name']) ?> (base <?= Helpers::esc(Helpers::money($p['base_price'])) ?>)</option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Raw Materials" data-kind="RM">
              <?php foreach ($rms as $rm): ?>
                <option value="<?= (int)$rm['id'] ?>" data-kind="RM" hidden><?= Helpers::esc($rm['code'] . ' — ' . $rm['name']) ?> (sale <?= Helpers::esc(Helpers::money($rm['sale_price'])) ?>)</option>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>
        <div class="mb-3"><label class="form-label">Price</label>
          <input class="form-control" type="number" step="0.01" min="0" name="price" required></div>
        <button class="btn btn-primary">Save</button>
      </form>
    </div></div>
  </div>
  <?php endif; ?>
</div>
<script>
(function () {
  const kindSel = document.getElementById('pl_item_kind');
  const itemSel = document.getElementById('pl_item_id');
  if (!kindSel || !itemSel) return;
  function syncOptions() {
    const k = kindSel.value;
    let firstVisible = null;
    Array.from(itemSel.querySelectorAll('option')).forEach(o => {
      const ok = o.dataset.kind === k;
      o.hidden = !ok;
      o.disabled = !ok;
      if (ok && firstVisible === null) firstVisible = o;
    });
    if (firstVisible) itemSel.value = firstVisible.value;
  }
  kindSel.addEventListener('change', syncOptions);
  syncOptions();
})();
</script>
