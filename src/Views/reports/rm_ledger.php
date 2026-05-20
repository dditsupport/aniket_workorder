<?php
use App\Helpers;

$refLabels = [
    'WO_RESERVE' => 'WO reserve', 'WO_RELEASE' => 'WO release',
    'WO_CONSUME' => 'WO consume', 'WO_PRODUCE' => 'WO produce',
    'ADJUST' => 'Adjust', 'SALE_OUT' => 'Sale',
];
?>
<h2 class="mb-3">RM Stock Ledger</h2>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-4">
    <select class="form-select" name="rm_id" required>
      <option value="0">— select raw material —</option>
      <?php foreach ($rms as $r): ?>
        <option value="<?= (int)$r['id'] ?>" <?= (int)$filter['rm_id'] === (int)$r['id'] ? 'selected' : '' ?>>
          <?= Helpers::esc($r['code'] . ' — ' . $r['name']) ?> (stock <?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?> <?= Helpers::esc($r['unit']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= Helpers::esc($filter['from']) ?>" title="From date"></div>
  <div class="col-md-2"><input class="form-control" type="date" name="to"   value="<?= Helpers::esc($filter['to']) ?>" title="To date"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Show</button></div>
</form>

<?php if (!$rm): ?>
  <div class="alert alert-secondary">Pick a raw material to view its movement ledger with a running balance.</div>
<?php else: ?>
  <div class="card"><div class="card-body p-0">
  <table class="table table-striped mb-0">
    <thead><tr>
      <th>Date</th><th>Type</th><th>Note</th><th>By</th>
      <th class="text-num">In</th><th class="text-num">Out</th><th class="text-num">Balance</th>
    </tr></thead>
    <tbody>
      <tr class="table-light">
        <td colspan="6"><strong>Opening balance<?= $filter['from'] !== '' ? ' (before ' . Helpers::esc(date('d M Y', strtotime($filter['from']))) . ')' : '' ?></strong></td>
        <td class="text-num"><strong><?= Helpers::esc(Helpers::qty($opening)) ?></strong></td>
      </tr>
      <?php foreach ($rows as $m): ?>
        <tr>
          <td><?= Helpers::esc(date('d M Y H:i', strtotime($m['created_at']))) ?></td>
          <td><?= Helpers::esc($refLabels[$m['ref_type']] ?? $m['ref_type']) ?><?= $m['ref_id'] ? ' #' . (int)$m['ref_id'] : '' ?></td>
          <td><small><?= Helpers::esc($m['note']) ?></small></td>
          <td><small class="text-secondary"><?= Helpers::esc($m['user_name']) ?></small></td>
          <td class="text-num text-success"><?= (float)$m['qty_in'] > 0 ? Helpers::esc(Helpers::qty($m['qty_in'])) : '' ?></td>
          <td class="text-num text-danger"><?= (float)$m['qty_out'] > 0 ? Helpers::esc(Helpers::qty($m['qty_out'])) : '' ?></td>
          <td class="text-num"><?= Helpers::esc(Helpers::qty($m['balance'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary p-3">No movements in this period.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr class="table-light">
      <td colspan="6" class="text-end"><strong>Closing balance</strong></td>
      <td class="text-num"><strong><?= Helpers::esc(Helpers::qty($closing)) ?> <?= Helpers::esc($rm['unit']) ?></strong></td>
    </tr></tfoot>
  </table>
  </div></div>
<?php endif; ?>
