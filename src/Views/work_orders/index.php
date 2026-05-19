<?php
use App\Auth;
use App\Helpers;
$statusBadge = function (string $s): string {
    return match ($s) {
        'draft'       => '<span class="badge text-bg-secondary">Draft</span>',
        'in_progress' => '<span class="badge text-bg-warning">In Progress</span>',
        'completed'   => '<span class="badge text-bg-success">Completed</span>',
        'cancelled'   => '<span class="badge text-bg-dark">Cancelled</span>',
        default       => '<span class="badge text-bg-light">' . htmlspecialchars($s) . '</span>',
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Work Orders</h2>
  <?php if (Auth::can('write')): ?>
    <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/work-orders/new')) ?>">+ New Work Order</a>
  <?php endif; ?>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3">
    <select class="form-select" name="customer_id">
      <option value="0">— all customers —</option>
      <?php foreach ($customers as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)$filter['customer_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= Helpers::esc($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select class="form-select" name="status">
      <option value="">— any status —</option>
      <?php foreach (['draft','in_progress','completed','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $filter['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>

<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>WO #</th><th>Customer</th><th>Product</th><th class="text-num">Qty</th><th class="text-num">Unit ₹</th><th class="text-num">Total ₹</th><th>Status</th><th>Created</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= Helpers::esc(Helpers::url('/work-orders/' . $r['id'])) ?>"><?= Helpers::esc($r['wo_number']) ?></a></td>
        <td><?= Helpers::esc($r['customer_name']) ?></td>
        <td><?= Helpers::esc($r['product_name']) ?> <small class="text-secondary">(<?= Helpers::esc($r['product_code']) ?>)</small></td>
        <td class="text-num"><?= Helpers::esc(Helpers::qty($r['quantity'])) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::money($r['unit_price'])) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::money($r['total_amount'])) ?></td>
        <td><?= $statusBadge($r['status']) ?></td>
        <td><small><?= Helpers::esc(date('d M Y', strtotime($r['created_at']))) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-secondary p-3">No work orders match.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
