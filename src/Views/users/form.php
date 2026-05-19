<?php
use App\Csrf;
use App\Helpers;
$action = $row ? Helpers::url('/users/' . $row['id']) : Helpers::url('/users');
?>
<h2><?= $row ? 'Edit User' : 'New User' ?></h2>
<form method="post" action="<?= Helpers::esc($action) ?>" class="card card-body" style="max-width:720px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Username</label>
      <input class="form-control" name="username" required maxlength="50" value="<?= Helpers::esc($row['username'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Full name</label>
      <input class="form-control" name="name" required maxlength="100" value="<?= Helpers::esc($row['name'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Role</label>
      <select class="form-select" name="role">
        <?php foreach ($roles as $rl): ?>
          <option value="<?= Helpers::esc($rl) ?>" <?= ($row['role'] ?? 'viewer') === $rl ? 'selected' : '' ?>><?= Helpers::esc($rl) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label">Password
        <?php if ($row): ?>
          <small class="text-secondary">(current: <code><?= Helpers::esc((string)$row['password']) ?></code>; leave blank to keep)</small>
        <?php endif; ?>
      </label>
      <input class="form-control" type="text" name="password" <?= $row ? '' : 'required' ?> minlength="6" autocomplete="off"></div>
    <div class="col-md-4 form-check ms-2 align-self-end">
      <input class="form-check-input" type="checkbox" name="active" value="1" id="active"
        <?= (!$row || (int)$row['active'] === 1) ? 'checked' : '' ?>>
      <label class="form-check-label" for="active">Active</label>
    </div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/users')) ?>">Cancel</a>
  </div>
</form>
