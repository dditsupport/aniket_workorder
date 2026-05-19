<?php
use App\Csrf;
use App\Helpers;
$action = $row ? Helpers::url('/price-lists/' . $row['id']) : Helpers::url('/price-lists');
?>
<h2><?= $row ? 'Rename Price List' : 'New Price List' ?></h2>
<?php if ($row): ?>
  <p class="text-secondary">This form only edits the list's <strong>name / description / active</strong>.
    To add or change item prices, open
    <a href="<?= Helpers::esc(Helpers::url('/price-lists/' . $row['id'])) ?>">Edit Prices</a>.
  </p>
<?php endif; ?>
<form method="post" action="<?= Helpers::esc($action) ?>" class="card card-body" style="max-width:640px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-12"><label class="form-label">Name</label>
      <input class="form-control" name="name" required maxlength="100" value="<?= Helpers::esc($row['name'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">Description</label>
      <input class="form-control" name="description" maxlength="255" value="<?= Helpers::esc($row['description'] ?? '') ?>"></div>
    <div class="col-12 form-check ms-2">
      <input class="form-check-input" type="checkbox" name="active" value="1" id="active"
        <?= (!$row || (int)$row['active'] === 1) ? 'checked' : '' ?>>
      <label class="form-check-label" for="active">Active</label>
    </div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/price-lists')) ?>">Cancel</a>
  </div>
</form>
