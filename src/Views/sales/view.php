<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
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
      <dt class="col-sm-4">Total</dt><dd class="col-sm-8"><strong><?= Helpers::esc(Helpers::money($sale['total_amount'])) ?></strong></dd>
      <?php if ($sale['notes']): ?>
        <dt class="col-sm-4">Notes</dt><dd class="col-sm-8"><?= nl2br(Helpers::esc($sale['notes'])) ?></dd>
      <?php endif; ?>
    </dl>
  </div></div></div>

  <?php if (Auth::can('admin')): ?>
  <div class="col-md-6"><div class="card"><div class="card-body">
    <h5>Admin</h5>
    <form method="post" action="<?= Helpers::esc(Helpers::url('/sales/' . $sale['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this sale and restore stock?')">
      <?= Csrf::field() ?>
      <button class="btn btn-outline-danger">Delete sale &amp; restore stock</button>
    </form>
  </div></div></div>
  <?php endif; ?>
</div>

<div class="card mt-3"><div class="card-header">Lines</div><div class="card-body p-0">
<table class="table mb-0">
  <thead><tr><th>Kind</th><th>Item</th><th class="text-num">Qty</th><th class="text-num">Unit price</th><th class="text-num">Line total</th></tr></thead>
  <tbody>
    <?php $t = 0; foreach ($lines as $ln): $t += (float)$ln['line_total']; ?>
    <tr>
      <td><span class="badge text-bg-<?= $ln['item_kind']==='FG' ? 'success' : 'info' ?>"><?= Helpers::esc($ln['item_kind']) ?></span></td>
      <td><?= Helpers::esc($ln['item_code']) ?> — <?= Helpers::esc($ln['item_name']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::qty($ln['qty'])) ?> <?= Helpers::esc($ln['unit']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($ln['unit_price'])) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($ln['line_total'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="4" class="text-end">Total</th><th class="text-num"><?= Helpers::esc(Helpers::money($t)) ?></th></tr></tfoot>
</table>
</div></div>
