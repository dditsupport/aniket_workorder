<?php
use App\Csrf;
use App\Helpers;

// Group open sales by customer for the dropdown filter.
$salesByCustomer = [];
foreach ($sales as $s) {
    $pending = (float)$s['total_amount'] - (float)$s['paid_amount'];
    if ($pending <= 0.005) continue; // skip fully paid invoices
    $salesByCustomer[(int)$s['customer_id']][] = $s + ['pending' => $pending];
}
?>
<h2>New Receipt</h2>
<form method="post" action="<?= Helpers::esc(Helpers::url('/receipts')) ?>" class="card card-body" style="max-width:760px">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Customer</label>
      <select class="form-select" name="customer_id" id="rc_customer" required>
        <option value="">— select —</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $preselect_customer === (int)$c['id'] ? 'selected' : '' ?>><?= Helpers::esc($c['code'] . ' — ' . $c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Against invoice <small class="text-secondary">(optional — leave blank for on-account)</small></label>
      <select class="form-select" name="sale_id" id="rc_sale">
        <option value="">— on account —</option>
        <?php foreach ($salesByCustomer as $custId => $rows): foreach ($rows as $s): ?>
          <option value="<?= (int)$s['id'] ?>"
                  data-customer="<?= (int)$custId ?>"
                  data-pending="<?= number_format((float)$s['pending'], 2, '.', '') ?>"
                  <?= $preselect_sale === (int)$s['id'] ? 'selected' : '' ?>>
            <?= Helpers::esc($s['sale_number']) ?> &mdash; <?= Helpers::esc(date('d M Y', strtotime((string)$s['sale_date']))) ?>
            (pending <?= Helpers::esc(Helpers::money($s['pending'])) ?>)
          </option>
        <?php endforeach; endforeach; ?>
      </select>
      <div class="form-text" id="rc_pending_hint">&nbsp;</div>
    </div>
    <div class="col-md-3"><label class="form-label">Date</label>
      <input class="form-control" type="date" name="receipt_date" value="<?= Helpers::esc(date('Y-m-d')) ?>" required></div>
    <div class="col-md-3"><label class="form-label">Amount</label>
      <input class="form-control" type="number" step="0.01" min="0.01" name="amount" id="rc_amount" required></div>
    <div class="col-md-3"><label class="form-label">Mode</label>
      <select class="form-select" name="mode">
        <option value="cash">Cash</option>
        <option value="bank">Bank</option>
        <option value="upi">UPI</option>
        <option value="cheque">Cheque</option>
        <option value="other">Other</option>
      </select></div>
    <div class="col-md-3"><label class="form-label">Reference</label>
      <input class="form-control" name="reference" maxlength="100" placeholder="txn / cheque no."></div>
    <div class="col-12"><label class="form-label">Note</label>
      <textarea class="form-control" name="note" rows="2"></textarea></div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary">Save Receipt</button>
    <a class="btn btn-link" href="<?= Helpers::esc(Helpers::url('/receipts')) ?>">Cancel</a>
  </div>
</form>

<script>
(function () {
  const custSel  = document.getElementById('rc_customer');
  const saleSel  = document.getElementById('rc_sale');
  const amount   = document.getElementById('rc_amount');
  const hint     = document.getElementById('rc_pending_hint');

  function filterSales() {
    const cust = custSel.value;
    let firstVisible = null;
    Array.from(saleSel.querySelectorAll('option')).forEach(o => {
      if (!o.value) { o.hidden = false; return; }       // keep "— on account —"
      const match = o.dataset.customer === cust;
      o.hidden = !match;
      o.disabled = !match;
      if (match && !firstVisible) firstVisible = o;
    });
    // If current selection no longer matches the customer, reset to on-account.
    const cur = saleSel.options[saleSel.selectedIndex];
    if (!cur || cur.hidden) saleSel.value = '';
    showHint();
  }
  function showHint() {
    const cur = saleSel.options[saleSel.selectedIndex];
    if (cur && cur.dataset.pending) {
      hint.textContent = 'Pending on this invoice: ' + cur.dataset.pending;
      amount.max = cur.dataset.pending;
    } else {
      hint.textContent = '';
      amount.removeAttribute('max');
    }
  }
  custSel.addEventListener('change', filterSales);
  saleSel.addEventListener('change', showHint);
  filterSales();
})();
</script>
