<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Price Lists</h2>
  <div class="d-flex gap-2">
    <?php if (Auth::can('admin')):
      $csv_export_url = Helpers::url('/price-lists/export');
      $csv_import_url = Helpers::url('/price-lists/import');
      $csv_modal_id   = 'csvPriceLists';
      $csv_required   = 'name';
      $csv_label      = 'Price Lists';
      $csv_extra_help = "Optional: description, active (1/0). Upserts by name. To import the prices INSIDE a list, open the list and use Export/Import there.";
      include __DIR__ . '/../partials/csv_tools.php';
    ?>
    <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/price-lists/new')) ?>">+ New Price List</a>
    <?php endif; ?>
  </div>
</div>
<p class="text-secondary small">Each customer is attached to one price list (set on the Customer form). The list provides prices for both raw materials and final products. Items not listed fall back to the item's default sale price.</p>

<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Name</th><th>Description</th><th class="text-num">Items</th><th class="text-num">Customers</th><th>Status</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= Helpers::esc(Helpers::url('/price-lists/' . $r['id'])) ?>"><?= Helpers::esc($r['name']) ?></a></td>
        <td class="text-secondary"><?= Helpers::esc($r['description']) ?></td>
        <td class="text-num"><?= (int)$r['line_count'] ?></td>
        <td class="text-num"><?= (int)$r['customer_count'] ?></td>
        <td><?= $r['active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
        <td class="text-end">
          <?php if (Auth::can('admin')): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= Helpers::esc(Helpers::url('/price-lists/' . $r['id'])) ?>">Edit</a>
          <?php endif; ?>
          <?php if (Auth::can('admin')): ?>
            <form method="post" action="<?= Helpers::esc(Helpers::url('/price-lists/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Delete this price list?')">
              <?= Csrf::field() ?>
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-secondary p-3">No price lists yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
