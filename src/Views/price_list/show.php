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
  <div class="d-flex gap-2 align-items-center">
    <?php if (Auth::can('admin')):
      $csv_export_url = Helpers::url('/price-lists/' . $list['id'] . '/items/export');
      $csv_import_url = Helpers::url('/price-lists/' . $list['id'] . '/items/import');
      $csv_modal_id   = 'csvPLItems';
      $csv_required   = 'item_kind, item_code, price';
      $csv_label      = 'Price List Items';
      $csv_extra_help = "item_kind is RM or FG. item_code is the master code. Upserts by (item_kind, item_code).";
      include __DIR__ . '/../partials/csv_tools.php';
    endif; ?>
    <a class="btn btn-link btn-sm" href="<?= Helpers::esc(Helpers::url('/price-lists')) ?>">&larr; All Lists</a>
  </div>
</div>

<?php if (Auth::can('admin')): ?>
<details class="card mb-3"><summary class="card-header" style="cursor:pointer">List settings (name / description / active)</summary>
  <div class="card-body">
    <form method="post" action="<?= Helpers::esc(Helpers::url('/price-lists/' . $list['id'])) ?>" class="row g-2 align-items-end">
      <?= Csrf::field() ?>
      <div class="col-md-4"><label class="form-label">Name</label>
        <input class="form-control" name="name" required maxlength="100" value="<?= Helpers::esc($list['name']) ?>"></div>
      <div class="col-md-5"><label class="form-label">Description</label>
        <input class="form-control" name="description" maxlength="255" value="<?= Helpers::esc((string)$list['description']) ?>"></div>
      <div class="col-md-2 form-check ms-3 mb-2">
        <input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= (int)$list['active'] === 1 ? 'checked' : '' ?>>
        <label class="form-check-label" for="active">Active</label>
      </div>
      <div class="col-md-1"><button class="btn btn-outline-primary w-100">Save</button></div>
    </form>
  </div>
</details>
<?php endif; ?>

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

<?php if (Auth::can('admin')): ?>
<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Quantity-break tiers <small class="text-secondary">(optional — applied per sale line when "Qty disc" is ticked)</small></span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-8">
        <table class="table table-sm mb-0">
          <thead><tr><th>Kind</th><th>Item</th><th class="text-num">Min qty</th><th class="text-num">Max qty</th><th class="text-num">Price</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($tiers as $t): ?>
              <tr>
                <td><span class="badge text-bg-<?= $t['item_kind']==='FG' ? 'success' : 'info' ?>"><?= Helpers::esc($t['item_kind']) ?></span></td>
                <td><?= Helpers::esc($t['item_name']) ?> <small class="text-secondary">(<?= Helpers::esc($t['item_code']) ?>)</small></td>
                <td class="text-num"><?= Helpers::esc(Helpers::qty($t['min_qty'])) ?></td>
                <td class="text-num"><?= $t['max_qty'] === null ? '<span class="text-secondary">&amp; above</span>' : Helpers::esc(Helpers::qty($t['max_qty'])) ?></td>
                <td class="text-num"><?= Helpers::esc(Helpers::money($t['price'])) ?></td>
                <td class="text-end">
                  <form method="post" action="<?= Helpers::esc(Helpers::url('/price-lists/' . $list['id'] . '/tiers/' . $t['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Remove this tier?')">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-outline-danger">×</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$tiers): ?><tr><td colspan="6" class="text-secondary p-3">No quantity tiers. Lines fall back to the flat price above.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="col-md-4">
        <form method="post" action="<?= Helpers::esc(Helpers::url('/price-lists/' . $list['id'] . '/tiers')) ?>" class="border rounded p-3">
          <?= Csrf::field() ?>
          <h6>Add tier</h6>
          <div class="mb-2"><label class="form-label">Item type</label>
            <select class="form-select form-select-sm" name="item_kind" id="tier_kind" required>
              <option value="FG">Product (Final)</option>
              <option value="RM">Raw Material</option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Item</label>
            <select class="form-select form-select-sm" name="item_id" id="tier_item" required>
              <optgroup label="Products" data-kind="FG">
                <?php foreach ($products as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" data-kind="FG"><?= Helpers::esc($p['code'] . ' — ' . $p['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Raw Materials" data-kind="RM">
                <?php foreach ($rms as $rm): ?>
                  <option value="<?= (int)$rm['id'] ?>" data-kind="RM" hidden><?= Helpers::esc($rm['code'] . ' — ' . $rm['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Min qty</label>
              <input class="form-control form-control-sm" type="number" step="0.001" min="0.001" name="min_qty" required></div>
            <div class="col-6"><label class="form-label">Max qty</label>
              <input class="form-control form-control-sm" type="number" step="0.001" min="0" name="max_qty" placeholder="blank = & above"></div>
          </div>
          <div class="mb-2 mt-2"><label class="form-label">Price</label>
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="price" required></div>
          <button class="btn btn-sm btn-primary">Add tier</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function plKindSync(kindId, itemId) {
  const kindSel = document.getElementById(kindId);
  const itemSel = document.getElementById(itemId);
  if (!kindSel || !itemSel) return;
  function sync() {
    const k = kindSel.value;
    let first = null;
    Array.from(itemSel.querySelectorAll('option')).forEach(o => {
      const ok = o.dataset.kind === k;
      o.hidden = !ok; o.disabled = !ok;
      if (ok && !first) first = o;
    });
    if (first) itemSel.value = first.value;
  }
  kindSel.addEventListener('change', sync);
  sync();
}
plKindSync('pl_item_kind', 'pl_item_id');
plKindSync('tier_kind', 'tier_item');
</script>
