<?php
use App\Helpers;
?>
<h2 class="mb-3">Customer Outstanding</h2>
<div class="card"><div class="card-body p-0">
<table class="table table-striped mb-0">
  <thead><tr><th>Code</th><th>Customer</th><th class="text-num">Billed</th><th class="text-num">Received</th><th class="text-num">Outstanding</th></tr></thead>
  <tbody>
    <?php $tB=0; $tR=0; foreach ($rows as $r): $tB += (float)$r['billed']; $tR += (float)$r['received']; ?>
    <tr>
      <td><?= Helpers::esc($r['code']) ?></td>
      <td><?= Helpers::esc($r['name']) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($r['billed'])) ?></td>
      <td class="text-num"><?= Helpers::esc(Helpers::money($r['received'])) ?></td>
      <td class="text-num <?= $r['outstanding'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= Helpers::esc(Helpers::money($r['outstanding'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <th colspan="2" class="text-end">Totals</th>
      <th class="text-num"><?= Helpers::esc(Helpers::money($tB)) ?></th>
      <th class="text-num"><?= Helpers::esc(Helpers::money($tR)) ?></th>
      <th class="text-num"><?= Helpers::esc(Helpers::money($tB - $tR)) ?></th>
    </tr>
  </tfoot>
</table>
</div></div>
<p class="text-secondary small">Billed = sum of all non-cancelled work order totals. Outstanding = Billed &minus; Received.</p>
