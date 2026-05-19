<?php
use App\Csrf;
use App\Helpers;
?>
<h2>New Receipt</h2>
<form method="post" action="<?= Helpers::esc(Helpers::url('/receipts')) ?>" class="card card-body" style="max-width:720px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Customer</label>
      <select class="form-select" name="customer_id" required>
        <option value="">— select —</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= Helpers::esc($c['code'] . ' — ' . $c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label">Date</label>
      <input class="form-control" type="date" name="receipt_date" value="<?= Helpers::esc(date('Y-m-d')) ?>" required></div>
    <div class="col-md-3"><label class="form-label">Amount</label>
      <input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
    <div class="col-md-3"><label class="form-label">Mode</label>
      <select class="form-select" name="mode">
        <option value="cash">Cash</option>
        <option value="bank">Bank</option>
        <option value="upi">UPI</option>
        <option value="cheque">Cheque</option>
        <option value="other">Other</option>
      </select></div>
    <div class="col-md-5"><label class="form-label">Reference (txn/cheque no.)</label>
      <input class="form-control" name="reference" maxlength="100"></div>
    <div class="col-md-4"><label class="form-label">&nbsp;</label></div>
    <div class="col-12"><label class="form-label">Note</label>
      <textarea class="form-control" name="note" rows="2"></textarea></div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary">Save Receipt</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/receipts')) ?>">Cancel</a>
  </div>
</form>
