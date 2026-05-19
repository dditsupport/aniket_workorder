<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Customers</h2>
  <div class="d-flex gap-2">
    <?php if (Auth::can('admin')):
      $csv_export_url = Helpers::url('/customers/export');
      $csv_import_url = Helpers::url('/customers/import');
      $csv_modal_id   = 'csvCustomers';
      $csv_required   = 'code, name';
      $csv_label      = 'Customers';
      $csv_extra_help = "Optional columns: gstin, phone, email, address, active (1/0), price_list (name).";
      include __DIR__ . '/../partials/csv_tools.php';
    endif; ?>
    <?php if (Auth::can('write')): ?>
      <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/customers/new')) ?>">+ New Customer</a>
    <?php endif; ?>
  </div>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Code</th><th>Name</th><th>GSTIN</th><th>Phone</th><th>Price List</th><th>Status</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= Helpers::esc($r['code']) ?></td>
        <td><?= Helpers::esc($r['name']) ?></td>
        <td><?= Helpers::esc($r['gstin']) ?></td>
        <td><?= Helpers::esc($r['phone']) ?></td>
        <td><?php if ($r['price_list_name']): ?>
          <span class="badge text-bg-info"><?= Helpers::esc($r['price_list_name']) ?></span>
        <?php else: ?>
          <span class="text-secondary small">default</span>
        <?php endif; ?></td>
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
