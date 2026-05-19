<?php
use App\Csrf;
use App\Helpers;
$action = $row ? Helpers::url('/raw-materials/' . $row['id']) : Helpers::url('/raw-materials');
?>
<h2><?= $row ? 'Edit Raw Material' : 'New Raw Material' ?></h2>
<form method="post" action="<?= Helpers::esc($action) ?>" class="card card-body" style="max-width:720px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Code</label>
      <input class="form-control" name="code" required maxlength="30" value="<?= Helpers::esc($row['code'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Name</label>
      <input class="form-control" name="name" required maxlength="150" value="<?= Helpers::esc($row['name'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="form-label">Unit</label>
      <input class="form-control" name="unit" required maxlength="20" value="<?= Helpers::esc($row['unit'] ?? 'pcs') ?>"></div>
    <div class="col-md-4"><label class="form-label">Reorder level</label>
      <input class="form-control" type="number" step="0.001" min="0" name="reorder_level" value="<?= Helpers::esc(Helpers::qty($row['reorder_level'] ?? 0)) ?>"></div>
    <?php if (!$row): ?>
    <div class="col-md-4"><label class="form-label">Opening stock</label>
      <input class="form-control" type="number" step="0.001" min="0" name="stock_qty" value="0"></div>
    <?php endif; ?>
  </div>
  <?php if ($row): ?>
    <p class="text-secondary small mt-3">To change current stock, use the <strong>Adjust</strong> button on the list page.</p>
  <?php endif; ?>
  <div class="mt-3">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/raw-materials')) ?>">Cancel</a>
  </div>
</form>
