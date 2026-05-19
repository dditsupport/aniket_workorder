<?php
use App\Helpers;
?>
<h2 class="mb-3">Dashboard</h2>
<div class="row g-3">
  <div class="col-md-3"><div class="card text-bg-primary"><div class="card-body"><div class="small">Customers</div><div class="fs-3"><?= (int)$stats['customers'] ?></div></div></div></div>
  <div class="col-md-3"><div class="card text-bg-success"><div class="card-body"><div class="small">Products (Final)</div><div class="fs-3"><?= (int)$stats['products'] ?></div></div></div></div>
  <div class="col-md-3"><div class="card text-bg-info"><div class="card-body"><div class="small">Raw Materials</div><div class="fs-3"><?= (int)$stats['raw_materials'] ?></div></div></div></div>
  <div class="col-md-3"><div class="card text-bg-warning"><div class="card-body"><div class="small">Open Work Orders</div><div class="fs-3"><?= (int)$stats['open_wo'] ?></div></div></div></div>
</div>

<div class="row mt-4 g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>Low stock alerts</span>
        <a href="<?= Helpers::esc(Helpers::url('/reports/rm-stock')) ?>" class="small">All RM stock &raquo;</a>
      </div>
      <div class="card-body p-0">
        <?php if (!$lowStock): ?>
          <p class="text-secondary p-3 mb-0">No items below reorder level.</p>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>Code</th><th>Name</th><th class="text-num">Stock</th><th class="text-num">Reorder</th></tr></thead>
          <tbody>
          <?php foreach ($lowStock as $r): ?>
            <tr class="low-stock">
              <td><?= Helpers::esc($r['code']) ?></td>
              <td><?= Helpers::esc($r['name']) ?></td>
              <td class="text-num"><?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?> <?= Helpers::esc($r['unit']) ?></td>
              <td class="text-num"><?= Helpers::esc(Helpers::qty($r['reorder_level'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>Recent Work Orders</span>
        <a href="<?= Helpers::esc(Helpers::url('/work-orders')) ?>" class="small">All &raquo;</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>WO #</th><th>Customer</th><th>Product</th><th>Status</th><th class="text-num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($recentWo as $w): ?>
            <tr>
              <td><a href="<?= Helpers::esc(Helpers::url('/work-orders/' . $w['id'])) ?>"><?= Helpers::esc($w['wo_number']) ?></a></td>
              <td><?= Helpers::esc($w['customer_name']) ?></td>
              <td><?= Helpers::esc($w['product_name']) ?></td>
              <td><span class="badge text-bg-secondary"><?= Helpers::esc($w['status']) ?></span></td>
              <td class="text-num"><?= Helpers::esc(Helpers::money($w['total_amount'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recentWo): ?><tr><td colspan="5" class="text-secondary p-3">No work orders yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-end"><strong>Total outstanding: <?= Helpers::esc(Helpers::money($outstanding)) ?></strong></div>
    </div>
  </div>
</div>
