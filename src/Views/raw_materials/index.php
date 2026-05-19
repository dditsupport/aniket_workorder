<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Raw Materials</h2>
  <div class="d-flex gap-2">
    <?php if (Auth::can('admin')):
      $csv_export_url = Helpers::url('/raw-materials/export');
      $csv_import_url = Helpers::url('/raw-materials/import');
      $csv_modal_id   = 'csvRM';
      $csv_required   = 'code, name';
      $csv_label      = 'Raw Materials';
      $csv_extra_help = "Optional: unit, reorder_level, stock_qty (only used for NEW rows; existing rows keep their stock — use Bulk Adjust to change stock), sale_price.";
      include __DIR__ . '/../partials/csv_tools.php';
    ?>
    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#csvRMAdjust">Bulk Adjust Stock</button>
    <div class="modal fade" id="csvRMAdjust" tabindex="-1">
      <div class="modal-dialog"><div class="modal-content">
        <form method="post" enctype="multipart/form-data" action="<?= Helpers::esc(Helpers::url('/raw-materials/adjust-import')) ?>">
          <?= Csrf::field() ?>
          <div class="modal-header"><h5 class="modal-title">Bulk Adjust RM Stock</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <p class="text-secondary small">Upload a CSV with columns <code>code, delta, note</code>. Positive <code>delta</code> adds stock, negative removes. Each row writes a stock_movements <code>ADJUST</code> entry.</p>
            <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-warning">Apply Adjustments</button>
          </div>
        </form>
      </div></div>
    </div>
    <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/raw-materials/new')) ?>">+ New RM</a>
    <?php endif; ?>
  </div>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Code</th><th>Name</th><th>Unit</th><th class="text-num">Stock</th><th class="text-num">Reorder</th><th class="text-num">Sale price</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r):
      $low = ((float)$r['reorder_level'] > 0 && (float)$r['stock_qty'] <= (float)$r['reorder_level']);
    ?>
      <tr class="<?= $low ? 'low-stock' : '' ?>">
        <td><?= Helpers::esc($r['code']) ?></td>
        <td><?= Helpers::esc($r['name']) ?></td>
        <td><?= Helpers::esc($r['unit']) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($r['reorder_level'])) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::money($r['sale_price'])) ?></td>
        <td class="text-end">
          <?php if (Auth::can('admin')): ?>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adj<?= (int)$r['id'] ?>">Adjust</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= Helpers::esc(Helpers::url('/raw-materials/' . $r['id'] . '/edit')) ?>">Edit</a>
          <?php endif; ?>
          <?php if (Auth::can('admin')): ?>
            <form method="post" action="<?= Helpers::esc(Helpers::url('/raw-materials/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Delete this raw material?')">
              <?= Csrf::field() ?>
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary p-3">No raw materials yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>

<?php if (Auth::can('admin')): foreach ($rows as $r): ?>
<div class="modal fade" id="adj<?= (int)$r['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="<?= Helpers::esc(Helpers::url('/raw-materials/' . $r['id'] . '/adjust')) ?>">
    <?= Csrf::field() ?>
    <div class="modal-header"><h5 class="modal-title">Adjust stock &mdash; <?= Helpers::esc($r['code']) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="text-secondary small">Current: <?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?> <?= Helpers::esc($r['unit']) ?>. Enter positive number to add, negative to remove.</p>
      <div class="mb-2"><label class="form-label">Delta</label>
        <input type="number" step="0.001" class="form-control" name="delta" required></div>
      <div><label class="form-label">Note</label>
        <input class="form-control" name="note" placeholder="e.g. GRN-001, opening stock"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-primary">Save adjustment</button>
    </div>
  </form>
</div></div></div>
<?php endforeach; endif; ?>
