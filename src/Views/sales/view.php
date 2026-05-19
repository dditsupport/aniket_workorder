<?php
use App\Auth;
use App\Csrf;
use App\Helpers;

$total   = (float)$sale['total_amount'];
$paid    = (float)$sale['paid_amount'];
$pending = $total - $paid;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><?= Helpers::esc($sale['sale_number']) ?></h2>
  <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/sales')) ?>">&larr; All Sales</a>
</div>

<div class="row g-3">
  <div class="col-md-6"><div class="card"><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-4">Customer</dt><dd class="col-sm-8"><?= Helpers::esc($sale['customer_name']) ?> <small class="text-secondary">(<?= Helpers::esc($sale['customer_code']) ?>)</small></dd>
      <dt class="col-sm-4">Date</dt><dd class="col-sm-8"><?= Helpers::esc(date('d M Y', strtotime($sale['sale_date']))) ?></dd>
      <dt class="col-sm-4">Total</dt><dd class="col-sm-8"><strong><?= Helpers::esc(Helpers::money($total)) ?></strong></dd>
      <dt class="col-sm-4">Received</dt><dd class="col-sm-8"><?= Helpers::esc(Helpers::money($paid)) ?></dd>
      <dt class="col-sm-4">Pending</dt><dd class="col-sm-8">
        <?php if ($pending <= 0.005): ?>
          <span class="badge text-bg-success">Fully paid</span>
        <?php else: ?>
          <strong class="text-danger"><?= Helpers::esc(Helpers::money($pending)) ?></strong>
        <?php endif; ?>
      </dd>
      <?php if ($sale['notes']): ?>
        <dt class="col-sm-4">Notes</dt><dd class="col-sm-8"><?= nl2br(Helpers::esc($sale['notes'])) ?></dd>
      <?php endif; ?>
    </dl>
  </div></div></div>

  <div class="col-md-6"><div class="card"><div class="card-body">
    <h5>Receipts against this invoice</h5>
    <?php if ($receipts): ?>
      <table class="table table-sm mb-2">
        <thead><tr><th>Date</th><th>Mode</th><th>Ref</th><th class="text-num">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($receipts as $r): ?>
          <tr>
            <td><?= Helpers::esc(date('d M Y', strtotime($r['receipt_date']))) ?></td>
            <td><?= Helpers::esc(strtoupper($r['mode'])) ?></td>
            <td><small class="text-secondary"><?= Helpers::esc($r['reference']) ?></small></td>
            <td class="text-num"><?= Helpers::esc(Helpers::money($r['amount'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-secondary mb-2">No receipts recorded against this invoice yet.</p>
    <?php endif; ?>

    <?php if ($pending > 0.005 && Auth::can('write')): ?>
      <a class="btn btn-primary btn-sm"
         href="<?= Helpers::esc(Helpers::url('/receipts/new?customer_id=' . $sale['customer_id'] . '&sale_id=' . $sale['id'])) ?>">
        + Record receipt for this invoice
      </a>
    <?php endif; ?>
    <?php if (Auth::can('admin')): ?>
      <form method="post" action="<?= Helpers::esc(Helpers::url('/sales/' . $sale['id'] . '/delete')) ?>" class="d-inline ms-2" onsubmit="return confirm('Delete this sale and restore stock?')">
        <?= Csrf::field() ?>
        <button class="btn btn-outline-danger btn-sm">Delete sale</button>
      </form>
    <?php endif; ?>
  </div></div></div>
</div>

<div class="card mt-3"><div class="card-header">Lines</div><div class="card-body p-0">
<table class="table mb-0">
  <thead><tr><th>Kind</th><th>Item</th><th class="text-num">Qty</th><th class="text-num">Unit price</th><th class="text-num">Line total</th></tr></thead>
  <tbody>
    <?php $t = 0; foreach ($lines as $ln): $t += (float)$ln['line_total']; ?>
    <tr>
      <td><span class="badge text-bg-<?= $ln['item_kind']==='FG' ? 'success' : 'info' ?>"><?= Helpers::esc($ln['item_kind']) ?></span></td>
      <td><?= Helpers::esc($ln['item_code']) ?> &mdash; <?= Helpers::esc($ln['item_name']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::qty($ln['qty'])) ?> <?= Helpers::esc($ln['unit']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($ln['unit_price'])) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($ln['line_total'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="4" class="text-end">Total</th><th class="text-num"><?= Helpers::esc(Helpers::money($t)) ?></th></tr></tfoot>
</table>
</div></div>
