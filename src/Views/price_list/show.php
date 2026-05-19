<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="mb-0"><?= Helpers::esc($list['name']) ?>
      <?php if (!$list['active']): ?><span class="badge text-bg-secondary">inactive</span><?php endif; ?>
    </h2>
    <?php if ($list['description']): ?><small class="text-secondary"><?= Helpers::esc($list['description']) ?></small><?php endif; ?>
  </div>
  <div>
    <?php if (Auth::can('admin')): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= Helpers::esc(Helpers::url('/price-lists/' . $list['id'] . '/edit')) ?>">Edit list</a>
    <?php endif; ?>
    <a class="btn btn-link btn-sm" href="<?= Helpers::esc(Helpers::url('/price-lists')) ?>">&larr; All Lists</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-8">
    <div class="card"><div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead><tr><th>Kind</th><th>Item</th><th class="text-num">Price</th><th class="text-num">Default</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($lines as $ln): ?>
          <tr>
            <td><span class="badge text-bg-<?= $ln['item_kind']==='FG' ? 'success' : 'info' ?>"><?= Helpers::esc($ln['item_kind']) ?></span></td>
            <td><?= Helpers::esc($ln['item_name']) ?> <small class="text-secondary">(<?= Helpers::esc($ln['item_code']) ?>)</small></td>
            <td class="text-num" style="min-width:170px">
              <?php if (Auth::can('admin')): ?>
                <form method="post" action="<?= Helpers::esc(Helpers::url('/price-lists/' . $list['id'] . '/items/' . $ln['id'])) ?>" class="d-flex gap-1 justify-content-end">
                  <?= Csrf::field() ?>
                  <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="price"
                         value="<?= Helpers::esc(number_format((float)$ln['price'], 2, '.', '')) ?>" required style="max-width:110px">
                  <button class="btn btn-sm btn-primary">Save</button>
                </form>
              <?php else: ?>
                <?= Helpers::esc(Helpers::money($ln['price'])) ?>
              <?php endif; ?>
            </td>
            <td class="text-num text-secondary"><?= Helpers::esc(Helpers::money($ln['base_price'])) ?></td>
            <td class="text-end">
              <?php if (Auth::can('admin')): ?>
              <form method="post" action="<?= Helpers::esc(Helpers::url('/price-lists/' . $list['id'] . '/items/' . $ln['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Remove this price?')">
                <?= Csrf::field() ?>
                <button class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lines): ?><tr><td colspan="5" class="text-secondary p-3">No prices in this list yet — items not listed fall back to their default sale price.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div></div>
  </div>

  <?php if (Auth::can('admin')): ?>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h5>Add / Update price</h5>
      <form method="post" action="<?= Helpers::esc(Helpers::url('/price-lists/' . $list['id'] . '/items')) ?>">
        <?= Csrf::field() ?>
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
