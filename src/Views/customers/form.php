<?php
use App\Csrf;
use App\Helpers;
$action = $row ? Helpers::url('/customers/' . $row['id']) : Helpers::url('/customers');
?>
<h2><?= $row ? 'Edit Customer' : 'New Customer' ?></h2>
<form method="post" action="<?= Helpers::esc($action) ?>" class="card card-body" style="max-width:720px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Code</label>
      <input class="form-control" name="code" required maxlength="30" value="<?= Helpers::esc($row['code'] ?? '') ?>"></div>
    <div class="col-md-8"><label class="form-label">Name</label>
      <input class="form-control" name="name" required maxlength="150" value="<?= Helpers::esc($row['name'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">GSTIN</label>
      <input class="form-control" name="gstin" maxlength="20" value="<?= Helpers::esc($row['gstin'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Phone</label>
      <input class="form-control" name="phone" maxlength="30" value="<?= Helpers::esc($row['phone'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Email</label>
      <input class="form-control" name="email" type="email" maxlength="120" value="<?= Helpers::esc($row['email'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">Address</label>
      <textarea class="form-control" name="address" rows="2" maxlength="500"><?= Helpers::esc($row['address'] ?? '') ?></textarea></div>
    <div class="col-12 form-check ms-2">
      <input class="form-check-input" type="checkbox" name="active" value="1" id="active"
        <?= (!$row || (int)$row['active'] === 1) ? 'checked' : '' ?>>
      <label class="form-check-label" for="active">Active</label>
    </div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/customers')) ?>">Cancel</a>
  </div>
</form>
