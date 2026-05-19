<?php
use App\Csrf;
use App\Helpers;
$action = $row ? Helpers::url('/products/' . $row['id']) : Helpers::url('/products');
?>
<h2><?= $row ? 'Edit Product' : 'New Product' ?></h2>
<form method="post" action="<?= Helpers::esc($action) ?>" class="card card-body" style="max-width:720px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Code</label>
      <input class="form-control" name="code" required maxlength="30" value="<?= Helpers::esc($row['code'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Name</label>
      <input class="form-control" name="name" required maxlength="150" value="<?= Helpers::esc($row['name'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="form-label">Unit</label>
      <input class="form-control" name="unit" required maxlength="20" value="<?= Helpers::esc($row['unit'] ?? 'pcs') ?>"></div>
    <div class="col-md-4"><label class="form-label">Base price</label>
      <input class="form-control" type="number" step="0.01" min="0" name="base_price" value="<?= Helpers::esc(number_format((float)($row['base_price'] ?? 0), 2, '.', '')) ?>"></div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/products')) ?>">Cancel</a>
  </div>
</form>
