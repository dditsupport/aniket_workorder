<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Products (Final)</h2>
  <div class="d-flex gap-2">
    <?php if (Auth::can('admin')):
      $csv_export_url = Helpers::url('/products/export');
      $csv_import_url = Helpers::url('/products/import');
      $csv_modal_id   = 'csvProducts';
      $csv_required   = 'code, name';
      $csv_label      = 'Products';
      $csv_extra_help = "Optional: unit, base_price, stock_qty (only used for NEW rows; existing rows keep their stock).";
      include __DIR__ . '/../partials/csv_tools.php';
    ?>
    <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/products/new')) ?>">+ New Product</a>
    <?php endif; ?>
  </div>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Code</th><th>Name</th><th>Unit</th><th class="text-num">Base price</th><th class="text-num">Stock</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= Helpers::esc($r['code']) ?></td>
        <td><?= Helpers::esc($r['name']) ?></td>
        <td><?= Helpers::esc($r['unit']) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::money($r['base_price'])) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-info" href="<?= Helpers::esc(Helpers::url('/products/' . $r['id'] . '/bom')) ?>">BOM</a>
          <?php if (Auth::can('admin')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= Helpers::esc(Helpers::url('/products/' . $r['id'] . '/edit')) ?>">Edit</a>
          <?php endif; ?>
          <?php if (Auth::can('admin')): ?>
            <form method="post" action="<?= Helpers::esc(Helpers::url('/products/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Delete this product?')">
              <?= Csrf::field() ?>
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-secondary p-3">No products yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
