<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Receipts</h2>
  <?php if (Auth::can('write')): ?>
    <a class="btn btn-primary" href="<?= Helpers::esc(Helpers::url('/receipts/new')) ?>">+ New Receipt</a>
  <?php endif; ?>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3">
    <select class="form-select" name="customer_id">
      <option value="0">— all customers —</option>
      <?php foreach ($customers as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= (int)$filter['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= Helpers::esc($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= Helpers::esc($filter['from']) ?>"></div>
  <div class="col-md-2"><input class="form-control" type="date" name="to"   value="<?= Helpers::esc($filter['to']) ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>

<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Date</th><th>Customer</th><th class="text-num">Amount</th><th>Mode</th><th>Reference</th><th>Note</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= Helpers::esc(date('d M Y', strtotime($r['receipt_date']))) ?></td>
        <td><?= Helpers::esc($r['customer_name']) ?></td>
        <td class="text-num"><?= Helpers::esc(Helpers::money($r['amount'])) ?></td>
        <td><?= Helpers::esc(strtoupper($r['mode'])) ?></td>
        <td><?= Helpers::esc($r['reference']) ?></td>
        <td><small><?= Helpers::esc($r['note']) ?></small></td>
        <td class="text-end">
          <?php if (Auth::can('admin')): ?>
          <form method="post" action="<?= Helpers::esc(Helpers::url('/receipts/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Delete this receipt?')">
            <?= Csrf::field() ?>
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary p-3">No receipts yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
