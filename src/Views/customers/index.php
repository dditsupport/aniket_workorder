<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Customers</h2>
  <?php if (Auth::can('write')): ?>
    <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/customers/new')) ?>">+ New Customer</a>
  <?php endif; ?>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Code</th><th>Name</th><th>GSTIN</th><th>Phone</th><th>Email</th><th>Status</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= Helpers::esc($r['code']) ?></td>
        <td><?= Helpers::esc($r['name']) ?></td>
        <td><?= Helpers::esc($r['gstin']) ?></td>
        <td><?= Helpers::esc($r['phone']) ?></td>
        <td><?= Helpers::esc($r['email']) ?></td>
        <td><?= $r['active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
        <td class="text-end">
          <?php if (Auth::can('write')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= Helpers::esc(Helpers::url('/customers/' . $r['id'] . '/edit')) ?>">Edit</a>
          <?php endif; ?>
          <?php if (Auth::can('admin')): ?>
            <form method="post" action="<?= Helpers::esc(Helpers::url('/customers/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Delete this customer?')">
              <?= Csrf::field() ?>
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary p-3">No customers yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
