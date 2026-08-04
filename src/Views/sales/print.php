<?php
use App\Config;
use App\Helpers;

$appName = (string)Config::get('app_name', 'Inventory & WorkOrder');
$firm = [
    'name'    => (string)Config::get('company.name', $appName),
    'address' => (string)Config::get('company.address', ''),
    'gstin'   => (string)Config::get('company.gstin', ''),
    'phone'   => (string)Config::get('company.phone', ''),
    'email'   => (string)Config::get('company.email', ''),
];

// Three copies on one A4 sheet. Labels are configurable so the wording can be
// changed without touching the template.
$copyLabels = Config::get('bill.copy_labels', [
    'Original for Buyer',
    'Duplicate for Transporter',
    'Triplicate for Supplier',
]);
if (!is_array($copyLabels) || count($copyLabels) !== 3) {
    $copyLabels = ['Original for Buyer', 'Duplicate for Transporter', 'Triplicate for Supplier'];
}

// Addresses are folded onto one flowing line so a multi-line address cannot
// push the phone/GSTIN lines out of the (capped) header block.
$flatten = static fn (string $s): string => trim((string)preg_replace('/\s*\R\s*/', ', ', trim($s)));
$firm['address'] = $flatten($firm['address']);
$custAddress = $flatten((string)($sale['customer_address'] ?? ''));

$total   = (float)$sale['total_amount'];
$paid    = (float)$sale['paid_amount'];
$pending = $total - $paid;

// Each copy gets one third of the sheet, which holds up to FIT_ROWS item rows.
// A longer bill would have to be truncated to fit, so instead of dropping lines
// it falls back to one full-page copy per sheet — still three copies, just on
// three pages.
$FIT_ROWS = 7;
$rows = $lines;
$threeUp = count($rows) <= $FIT_ROWS;
$dense = count($rows) > 5;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Helpers::esc($sale['sale_number']) ?> &middot; Bill</title>
<style>
  @page { size: A4 portrait; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    background: #6c757d;
    font-family: "Segoe UI", Arial, Helvetica, sans-serif;
    color: #000;
    font-size: 8.5pt;
  }

  /* Screen-only toolbar */
  .toolbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; gap: .5rem; align-items: center; justify-content: center;
    padding: 8px; background: #212529; color: #fff;
  }
  .toolbar a, .toolbar button {
    font: inherit; padding: 6px 14px; border-radius: 4px;
    border: 1px solid transparent; cursor: pointer; text-decoration: none;
  }
  .toolbar button { background: #0d6efd; color: #fff; }
  .toolbar a { background: transparent; color: #dee2e6; border-color: #495057; }

  .sheet {
    width: 210mm; height: 297mm;
    margin: 10px auto; padding: 5mm 7mm;
    background: #fff;
    box-shadow: 0 0 8px rgba(0,0,0,.4);
  }
  /* 3 equal parts of the usable sheet height (297mm - 10mm padding). */
  .copy {
    height: 95.6mm;
    padding-bottom: 2.5mm;
    border-bottom: 1px dashed #888;
    overflow: hidden;
    display: flex; flex-direction: column;
  }
  .copy:last-child { border-bottom: 0; padding-bottom: 0; }
  /* Fallback for long bills: one copy per sheet, height driven by content.
     Nothing is clipped here — a copy that outgrows a page simply flows onto
     the next one. */
  .sheet.single { height: auto; min-height: 297mm; }
  /* Plain block flow here — flex containers do not fragment reliably across
     printed pages. */
  .sheet.single .copy { display: block; height: auto; min-height: 100%;
                        border-bottom: 0; padding-bottom: 0; overflow: visible; }
  .sheet.single .items-wrap { overflow: visible; }
  .sheet.single .firm-meta,
  .sheet.single .parties .meta,
  .sheet.single .addr,
  .sheet.single .foot .notes { max-height: none; overflow: visible; }

  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 6mm;
          border-bottom: 1.2pt solid #000; padding-bottom: .8mm; }
  .firm-name { font-size: 11pt; font-weight: 700; line-height: 1.1; }
  /* Addresses and notes are capped in the compact 3-up layout so a long one
     can never squeeze the item rows off the copy. Caps are exact multiples of
     the line box, so text is cut between lines and never mid-line. */
  .firm-meta { font-size: 7pt; line-height: 3.1mm; max-height: 12.4mm; overflow: hidden; }
  .addr { display: block; max-height: 6.2mm; overflow: hidden; }
  .copy-tag { text-align: right; white-space: nowrap; }
  .copy-tag .tag { display: inline-block; border: 1pt solid #000; padding: .6mm 2mm;
                   font-size: 7.5pt; font-weight: 700; text-transform: uppercase; }
  .copy-tag .doc { font-size: 8pt; margin-top: 1mm; }

  .parties { display: flex; justify-content: space-between; gap: 6mm; padding: 1mm 0; }
  .parties .label { font-size: 6.5pt; text-transform: uppercase; color: #444; }
  .parties .who { font-weight: 700; }
  .parties .meta { font-size: 7pt; line-height: 3.1mm; max-height: 12.4mm; overflow: hidden; }
  .parties .right { text-align: right; white-space: nowrap; }

  /* Items take whatever height is left; the totals and signature below are
     never pushed off the copy. */
  .items-wrap { flex: 1 1 auto; min-height: 0; overflow: hidden; }
  table.items { width: 100%; border-collapse: collapse; }
  table.items th, table.items td { border: .5pt solid #000; padding: .8mm 1.5mm; }
  table.items th { background: #eee; font-size: 7.5pt; text-transform: uppercase; }
  .dense table.items th, .dense table.items td { padding: .3mm 1.2mm; font-size: 7.5pt; }
  .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .ctr { text-align: center; }
  .item-name { font-weight: 600; }
  .item-code { color: #444; font-size: 7.5pt; }

  .foot { display: flex; justify-content: space-between; gap: 6mm; margin-top: 1.5mm; flex: 0 0 auto; }
  .foot .notes { font-size: 7.5pt; max-width: 95mm; line-height: 3.2mm; max-height: 9.6mm; overflow: hidden; }
  .sums { min-width: 62mm; }
  .sums table { width: 100%; border-collapse: collapse; }
  .sums td { padding: .5mm 1.5mm; }
  .sums .k { text-align: right; }
  .sums .grand td { border-top: .8pt solid #000; border-bottom: .8pt solid #000; font-weight: 700; font-size: 9.5pt; }
  .sums .due td { font-weight: 700; }

  .sign { display: flex; justify-content: space-between; align-items: flex-end;
          flex: 0 0 auto; padding-top: 3mm; font-size: 7.5pt; }
  .sign .line { border-top: .5pt solid #000; padding-top: .8mm; min-width: 45mm; text-align: center; }

  @media print {
    body { background: #fff; }
    .toolbar { display: none !important; }
    .sheet { margin: 0; box-shadow: none; width: auto; height: auto; }
    .sheet + .sheet { break-before: page; page-break-before: always; }
    .copy { break-inside: avoid; page-break-inside: avoid; }
    /* A full-page copy may legitimately span pages, so let it break. */
    .sheet.single .copy { break-inside: auto; page-break-inside: auto; }
    .sheet.single table.items thead { display: table-header-group; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <button type="button" onclick="window.print()">Print (3 copies / A4)</button>
  <a href="<?= Helpers::esc(Helpers::url('/sales/' . (int)$sale['id'])) ?>">Back to invoice</a>
</div>

<?php if ($threeUp): ?><div class="sheet"><?php endif; ?>
<?php foreach ($copyLabels as $label): ?>
  <?php if (!$threeUp): ?><div class="sheet single"><?php endif; ?>
  <section class="copy <?= $dense ? 'dense' : '' ?>">

    <div class="head">
      <div>
        <div class="firm-name"><?= Helpers::esc($firm['name']) ?></div>
        <div class="firm-meta">
          <?php if ($firm['address'] !== ''): ?><span class="addr"><?= Helpers::esc($firm['address']) ?></span><?php endif; ?>
          <?php if ($firm['phone'] !== '' || $firm['email'] !== ''): ?>
            <span>
              <?php if ($firm['phone'] !== ''): ?>Ph: <?= Helpers::esc($firm['phone']) ?><?php endif; ?>
              <?php if ($firm['phone'] !== '' && $firm['email'] !== ''): ?> &middot; <?php endif; ?>
              <?php if ($firm['email'] !== ''): ?><?= Helpers::esc($firm['email']) ?><?php endif; ?>
            </span><br>
          <?php endif; ?>
          <?php if ($firm['gstin'] !== ''): ?>GSTIN: <?= Helpers::esc($firm['gstin']) ?><?php endif; ?>
        </div>
      </div>
      <div class="copy-tag">
        <span class="tag"><?= Helpers::esc((string)$label) ?></span>
        <div class="doc"><strong>Invoice</strong></div>
      </div>
    </div>

    <div class="parties">
      <div>
        <div class="label">Bill to</div>
        <div class="who"><?= Helpers::esc($sale['customer_name']) ?> (<?= Helpers::esc($sale['customer_code']) ?>)</div>
        <div class="meta">
          <?php if ($custAddress !== ''): ?><span class="addr"><?= Helpers::esc($custAddress) ?></span><?php endif; ?>
          <?php if (!empty($sale['customer_phone'])): ?>Ph: <?= Helpers::esc($sale['customer_phone']) ?><br><?php endif; ?>
          <?php if (!empty($sale['customer_gstin'])): ?>GSTIN: <?= Helpers::esc($sale['customer_gstin']) ?><?php endif; ?>
        </div>
      </div>
      <div class="right">
        <div class="label">Invoice no.</div>
        <div class="who"><?= Helpers::esc($sale['sale_number']) ?></div>
        <div class="meta">Date: <?= Helpers::esc(date('d M Y', strtotime((string)$sale['sale_date']))) ?></div>
      </div>
    </div>

    <div class="items-wrap">
    <table class="items">
      <thead>
        <tr>
          <th style="width:7mm">#</th>
          <th>Item</th>
          <th style="width:20mm" class="num">Qty</th>
          <th style="width:22mm" class="num">Rate</th>
          <th style="width:26mm" class="num">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ($rows as $ln): $i++; ?>
        <tr>
          <td class="ctr"><?= $i ?></td>
          <td>
            <span class="item-name"><?= Helpers::esc($ln['item_name']) ?></span>
            <span class="item-code">(<?= Helpers::esc($ln['item_code']) ?>)</span>
          </td>
          <td class="num"><?= Helpers::esc(Helpers::qty($ln['qty'])) ?> <?= Helpers::esc($ln['unit']) ?></td>
          <td class="num"><?= Helpers::esc(Helpers::money($ln['unit_price'])) ?></td>
          <td class="num"><?= Helpers::esc(Helpers::money($ln['line_total'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$lines): ?>
        <tr><td colspan="5" class="ctr">No items on this invoice.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

    <div class="foot">
      <div class="notes">
        <?php if (!empty($sale['notes'])): ?>
          <strong>Notes:</strong> <?= nl2br(Helpers::esc($sale['notes'])) ?>
        <?php endif; ?>
      </div>
      <div class="sums">
        <table>
          <tr class="grand"><td class="k">Total</td><td class="num"><?= Helpers::esc(Helpers::money($total)) ?></td></tr>
          <tr><td class="k">Received</td><td class="num"><?= Helpers::esc(Helpers::money($paid)) ?></td></tr>
          <tr class="due">
            <td class="k">Pending</td>
            <td class="num"><?= $pending <= 0.005 ? 'PAID' : Helpers::esc(Helpers::money($pending)) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <div class="sign">
      <div class="line">Receiver's signature</div>
      <div class="line">For <?= Helpers::esc($firm['name']) ?></div>
    </div>

  </section>
  <?php if (!$threeUp): ?></div><?php endif; ?>
<?php endforeach; ?>
<?php if ($threeUp): ?></div><?php endif; ?>

<?php if (!empty($autoprint)): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
