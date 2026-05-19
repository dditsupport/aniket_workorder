<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>BOM &mdash; <?= Helpers::esc($product['name']) ?> <small class="text-secondary">(<?= Helpers::esc($product['code']) ?>)</small></h2>
  <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/products')) ?>">&larr; Products</a>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card"><div class="card-body p-0">
    <table class="table mb-0">
      <thead><tr><th>RM Code</th><th>RM Name</th><th class="text-num">Qty per unit</th><th>Unit</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= Helpers::esc($r['code']) ?></td>
            <td><?= Helpers::esc($r['name']) ?></td>
            <td class="text-num"><?= Helpers::esc(Helpers::qty($r['qty_per_unit'])) ?></td>
            <td><?= Helpers::esc($r['unit']) ?></td>
            <td class="text-end">
              <?php if (Auth::can('write')): ?>
              <form method="post" action="<?= Helpers::esc(Helpers::url('/products/' . $product['id'] . '/bom/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Remove this BOM line?')">
                <?= Csrf::field() ?>
                <button class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-secondary p-3">No BOM lines defined.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div></div>
  </div>

  <?php if (Auth::can('write')): ?>
  <div class="col-md-5">
    <div class="card"><div class="card-body">
      <h5>Add / Update line</h5>
      <form method="post" action="<?= Helpers::esc(Helpers::url('/products/' . $product['id'] . '/bom')) ?>">
        <?= Csrf::field() ?>
        <div class="mb-2"><label class="form-label">Raw material</label>
          <select class="form-select" name="raw_material_id" required>
            <option value="">— select —</option>
            <?php foreach ($rms as $rm): ?>
              <option value="<?= (int)$rm['id'] ?>"><?= Helpers::esc($rm['code'] . ' — ' . $rm['name'] . ' (' . $rm['unit'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3"><label class="form-label">Qty per unit of product</label>
          <input class="form-control" type="number" step="0.0001" min="0.0001" name="qty_per_unit" required></div>
        <button class="btn btn-primary">Save line</button>
      </form>
      <p class="text-secondary small mt-3">If the RM is already in BOM, its qty will be updated.</p>
    </div></div>
  </div>
  <?php endif; ?>
</div>
