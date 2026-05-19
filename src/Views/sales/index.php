<?php
use App\Auth;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Sales</h2>
  <?php if (Auth::can('write')): ?>
    <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/sales/new')) ?>">+ New Sale</a>
  <?php endif; ?>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select class="form-select" name="customer_id">
    <option value="0">— all customers —</option>
    <?php foreach ($customers as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (int)$filter['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= Helpers::esc($c['name']) ?></option>
    <?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><input type="date" class="form-control" name="from" value="<?= Helpers::esc($filter['from']) ?>"></div>
  <div class="col-md-2"><input type="date" class="form-control" name="to"   value="<?= Helpers::esc($filter['to']) ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>

<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Sale #</th><th>Date</th><th>Customer</th><th class="text-num">Lines</th><th class="text-num">Total</th><th class="text-num">Paid</th><th class="text-num">Pending</th></tr></thead>
  <tbody>
    <?php $grandPending = 0.0; foreach ($rows as $r):
      $pending = (float)$r['total_amount'] - (float)$r['paid_amount'];
      $grandPending += max(0, $pending);
    ?>
      <tr>
        <td><a href="<?= Helpers::esc(Helpers::url('/sales/' . $r['id'])) ?>"><?= Helpers::esc($r['sale_number']) ?></a></td>
        <td><?= Helpers::esc(date('d M Y', strtotime($r['sale_date']))) ?></td>
        <td><?= Helpers::esc($r['customer_name']) ?> <small class="text-secondary">(<?= Helpers::esc($r['customer_code']) ?>)</small></td>
        <td class="text-num"><?= (int)$r['line_count'] ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::money($r['total_amount'])) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::money($r['paid_amount'])) ?></td>
        <td class="text-num">
          <?php if ($pending <= 0.005): ?>
            <span class="badge text-bg-success">Paid</span>
          <?php else: ?>
            <strong class="text-danger"><?= Helpers::esc(Helpers::money($pending)) ?></strong>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary p-3">No sales recorded.</td></tr><?php endif; ?>
  </tbody>
  <?php if ($rows): ?>
  <tfoot><tr><th colspan="6" class="text-end">Total pending</th><th class="text-num text-danger"><?= Helpers::esc(Helpers::money($grandPending)) ?></th></tr></tfoot>
  <?php endif; ?>
</table>
</div></div>
