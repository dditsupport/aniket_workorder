<?php
use App\Helpers;
?>
<h2 class="mb-3">Final Product Stock</h2>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Code</th><th>Name</th><th>Unit</th><th class="text-num">Stock</th><th class="text-num">Base price</th><th class="text-num">Stock value</th></tr></thead>
  <tbody>
    <?php $totalVal = 0; foreach ($rows as $r): $val = (float)$r['stock_qty'] * (float)$r['base_price']; $totalVal += $val; ?>
    <tr>
      <td><?= Helpers::esc($r['code']) ?></td>
      <td><?= Helpers::esc($r['name']) ?></td>
      <td><?= Helpers::esc($r['unit']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::qty($r['stock_qty'])) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($r['base_price'])) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($val)) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="5" class="text-end">Total (at base price)</th><th class="text-num"><?= Helpers::esc(Helpers::money($totalVal)) ?></th></tr></tfoot>
</table>
</div></div>
