<?php
use App\Helpers;
?>
<h2 class="mb-3">WO Status Report</h2>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select class="form-select" name="customer_id">
    <option value="0">— all customers —</option>
    <?php foreach ($customers as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (int)$filter['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= Helpers::esc($c['name']) ?></option>
    <?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><select class="form-select" name="status">
    <option value="">— any status —</option>
    <?php foreach (['draft','in_progress','completed','cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= $filter['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><input type="date" class="form-control" name="from" value="<?= Helpers::esc($filter['from']) ?>"></div>
  <div class="col-md-2"><input type="date" class="form-control" name="to"   value="<?= Helpers::esc($filter['to']) ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>

<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>WO #</th><th>Customer</th><th>Product</th><th class="text-num">Qty</th><th class="text-num">Total</th><th>Status</th><th>Created</th></tr></thead>
  <tbody>
    <?php $sum = 0; foreach ($rows as $r): if ($r['status'] !== 'cancelled') $sum += (float)$r['total_amount']; ?>
    <tr>
      <td><a href="<?= Helpers::esc(Helpers::url('/work-orders/' . $r['id'])) ?>"><?= Helpers::esc($r['wo_number']) ?></a></td>
      <td><?= Helpers::esc($r['customer_name']) ?></td>
      <td><?= Helpers::esc($r['product_name']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::qty($r['quantity'])) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($r['total_amount'])) ?></td>
      <td><?= Helpers::esc($r['status']) ?></td>
      <td><small><?= Helpers::esc(date('d M Y', strtotime($r['created_at']))) ?></small></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="4" class="text-end">Total (non-cancelled)</th><th class="text-num"><?= Helpers::esc(Helpers::money($sum)) ?></th><th colspan="2"></th></tr></tfoot>
</table>
</div></div>
