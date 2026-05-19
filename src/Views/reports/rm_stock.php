<?php
use App\Helpers;
?>
<h2 class="mb-3">RM Stock Report</h2>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Code</th><th>Name</th><th>Unit</th><th class="text-num">Stock</th><th class="text-num">Reorder</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['low'] ? 'low-stock' : '' ?>">
      <td><?= Helpers::esc($r['code']) ?></td>
      <td><?= Helpers::esc($r['name']) ?></td>
      <td><?= Helpers::esc($r['unit']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::qty($r['reorder_level'])) ?></td>
      <td><?= $r['low'] ? '<span class="badge text-bg-danger">Low</span>' : '<span class="badge text-bg-success">OK</span>' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>
