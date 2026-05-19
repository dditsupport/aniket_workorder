<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
$status = $wo['status'];
$next = match ($status) {
    'draft'       => ['in_progress' => 'Start (move to In Progress)', 'cancelled' => 'Cancel'],
    'in_progress' => ['completed'   => 'Mark Completed',              'cancelled' => 'Cancel & Release RM'],
    default       => [],
};
$badge = match ($status) {
    'draft'       => '<span class="badge text-bg-secondary">Draft</span>',
    'in_progress' => '<span class="badge text-bg-warning">In Progress</span>',
    'completed'   => '<span class="badge text-bg-success">Completed</span>',
    'cancelled'   => '<span class="badge text-bg-dark">Cancelled</span>',
    default       => '',
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><?= Helpers::esc($wo['wo_number']) ?> <?= $badge ?></h2>
  <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/work-orders')) ?>">&larr; All WOs</a>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-4">Customer</dt><dd class="col-sm-8"><?= Helpers::esc($wo['customer_name']) ?> <small class="text-secondary">(<?= Helpers::esc($wo['customer_code']) ?>)</small></dd>
        <dt class="col-sm-4">Product</dt><dd class="col-sm-8"><?= Helpers::esc($wo['product_name']) ?> <small class="text-secondary">(<?= Helpers::esc($wo['product_code']) ?>)</small></dd>
        <dt class="col-sm-4">Quantity</dt><dd class="col-sm-8"><?= Helpers::esc(Helpers::qty($wo['quantity'])) ?> <?= Helpers::esc($wo['product_unit']) ?></dd>
        <dt class="col-sm-4">Unit price</dt><dd class="col-sm-8"><?= Helpers::esc(Helpers::money($wo['unit_price'])) ?></dd>
        <dt class="col-sm-4">Total</dt><dd class="col-sm-8"><strong><?= Helpers::esc(Helpers::money($wo['total_amount'])) ?></strong></dd>
        <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?= Helpers::esc(date('d M Y H:i', strtotime($wo['created_at']))) ?></dd>
        <?php if ($wo['completed_at']): ?>
          <dt class="col-sm-4">Completed</dt><dd class="col-sm-8"><?= Helpers::esc(date('d M Y H:i', strtotime($wo['completed_at']))) ?></dd>
        <?php endif; ?>
        <?php if ($wo['notes']): ?>
          <dt class="col-sm-4">Notes</dt><dd class="col-sm-8"><?= nl2br(Helpers::esc($wo['notes'])) ?></dd>
        <?php endif; ?>
      </dl>
    </div></div>
  </div>

  <?php if (Auth::can('write') && $next): ?>
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h5>Actions</h5>
      <?php foreach ($next as $to => $label): ?>
        <form method="post" action="<?= Helpers::esc(Helpers::url('/work-orders/' . $wo['id'] . '/status')) ?>" class="d-inline">
          <?= Csrf::field() ?>
          <input type="hidden" name="to" value="<?= Helpers::esc($to) ?>">
          <button class="btn btn-<?= $to==='cancelled' ? 'outline-danger' : ($to==='completed' ? 'success' : 'warning') ?> mb-2"
            onclick="return confirm('<?= Helpers::esc($label) ?>?')">
            <?= Helpers::esc($label) ?>
          </button>
        </form>
      <?php endforeach; ?>

      <?php if (Auth::can('admin') && in_array($status, ['draft','cancelled'], true)): ?>
        <hr>
        <form method="post" action="<?= Helpers::esc(Helpers::url('/work-orders/' . $wo['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this work order?')">
          <?= Csrf::field() ?>
          <button class="btn btn-sm btn-outline-danger">Delete WO</button>
        </form>
      <?php endif; ?>
    </div></div>
  </div>
  <?php endif; ?>
</div>

<div class="card mt-3"><div class="card-header">
  <?= $bom !== null ? 'Required RM (BOM × qty)' : 'Allocated RM (snapshot)' ?>
</div><div class="card-body p-0">
<table class="table mb-0">
  <thead><tr><th>Code</th><th>Name</th><th class="text-num">Per unit</th><th class="text-num">Required / Allocated</th><th class="text-num">Stock</th><th>Status</th></tr></thead>
  <tbody>
    <?php if ($bom !== null): foreach ($bom as $r):
      $req = (float)$r['required']; $ok = (float)$r['stock_qty'] >= $req;
    ?>
      <tr>
        <td><?= Helpers::esc($r['code']) ?></td>
        <td><?= Helpers::esc($r['name']) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($r['qty_per_unit'])) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($req)) ?> <?= Helpers::esc($r['unit']) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?></td>
        <td><?= $ok ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Short</span>' ?></td>
      </tr>
    <?php endforeach; if (!$bom): ?><tr><td colspan="6" class="text-secondary p-3">No BOM lines for this product.</td></tr><?php endif; ?>
    <?php elseif ($allocations !== null): foreach ($allocations as $a): ?>
      <tr>
        <td><?= Helpers::esc($a['code']) ?></td>
        <td><?= Helpers::esc($a['name']) ?></td>
        <td class="text-num">—</td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($a['allocated'])) ?> <?= Helpers::esc($a['unit']) ?></td>
        <td class="text-num">—</td>
        <td>—</td>
      </tr>
    <?php endforeach; if (!$allocations): ?><tr><td colspan="6" class="text-secondary p-3">No allocations recorded.</td></tr><?php endif; endif; ?>
  </tbody>
</table>
</div></div>

<div class="card mt-3"><div class="card-header">Stock Movements</div><div class="card-body p-0">
<table class="table mb-0">
  <thead><tr><th>When</th><th>Item</th><th>Type</th><th>Ref</th><th class="text-num">In</th><th class="text-num">Out</th><th>Note</th></tr></thead>
  <tbody>
    <?php foreach ($movements as $m): ?>
      <tr>
        <td><small><?= Helpers::esc(date('d M Y H:i', strtotime($m['created_at']))) ?></small></td>
        <td><?= Helpers::esc(($m['item_type']==='RM'?'RM':'FG') . ' · ' . $m['item_code'] . ' — ' . $m['item_name']) ?></td>
        <td><?= Helpers::esc($m['item_type']) ?></td>
        <td><?= Helpers::esc($m['ref_type']) ?></td>
        <td class="text-num"><?= (float)$m['qty_in'] > 0 ? Helpers::esc(Helpers::qty($m['qty_in'])) : '' ?></td>
        <td class="text-num"><?= (float)$m['qty_out'] > 0 ? Helpers::esc(Helpers::qty($m['qty_out'])) : '' ?></td>
        <td><small><?= Helpers::esc($m['note']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$movements): ?><tr><td colspan="7" class="text-secondary p-3">No movements yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
