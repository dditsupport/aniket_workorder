<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Users</h2>
  <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/users/new')) ?>">+ New User</a>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Active</th><th>Created</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= Helpers::esc($r['username']) ?></td>
      <td><?= Helpers::esc($r['name']) ?></td>
      <td><span class="badge text-bg-info"><?= Helpers::esc($r['role']) ?></span></td>
      <td><?= $r['active'] ? 'Yes' : 'No' ?></td>
      <td><small><?= Helpers::esc(date('d M Y', strtotime($r['created_at']))) ?></small></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-secondary" href="<?= Helpers::esc(Helpers::url('/users/' . $r['id'] . '/edit')) ?>">Edit</a>
        <?php if ((int)$r['id'] !== Auth::userId()): ?>
        <form method="post" action="<?= Helpers::esc(Helpers::url('/users/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Delete this user?')">
          <?= Csrf::field() ?>
          <button class="btn btn-sm btn-outline-danger">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>
