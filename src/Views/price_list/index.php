<?php
use App\Auth;
use App\Csrf;
use App\Helpers;
?>
<h2 class="mb-3">Customer Price List</h2>
<div class="row g-3">
  <div class="col-md-8">
    <div class="card"><div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead><tr><th>Customer</th><th>Product</th><th class="text-num">Price</th><th class="text-num">Base</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= Helpers::esc($r['customer_name']) ?> <small class="text-secondary">(<?= Helpers::esc($r['customer_code']) ?>)</small></td>
            <td><?= Helpers::esc($r['product_name']) ?> <small class="text-secondary">(<?= Helpers::esc($r['product_code']) ?>)</small></td>
            <td class="text-num"><?= Helpers::esc(Helpers::money($r['price'])) ?></td>
            <td class="text-num text-secondary"><?= Helpers::esc(Helpers::money($r['base_price'])) ?></td>
            <td class="text-end">
              <?php if (Auth::can('write')): ?>
              <form method="post" action="<?= Helpers::esc(Helpers::url('/price-list/' . $r['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('Remove this price?')">
                <?= Csrf::field() ?>
                <button class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-secondary p-3">No customer-specific prices yet — products use base price.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div></div>
  </div>
  <?php if (Auth::can('write')): ?>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h5>Add / Update price</h5>
      <form method="post" action="<?= Helpers::esc(Helpers::url('/price-list')) ?>">
        <?= Csrf::field() ?>
        <div class="mb-2"><label class="form-label">Customer</label>
          <select class="form-select" name="customer_id" required>
            <option value="">— select —</option>
            <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= Helpers::esc($c['code'] . ' — ' . $c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label">Product</label>
          <select class="form-select" name="product_id" required>
            <option value="">— select —</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= (int)$p['id'] ?>" data-base="<?= Helpers::esc(number_format((float)$p['base_price'],2,'.','')) ?>">
                <?= Helpers::esc($p['code'] . ' — ' . $p['name']) ?> (base <?= Helpers::esc(Helpers::money($p['base_price'])) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3"><label class="form-label">Price</label>
          <input class="form-control" type="number" step="0.01" min="0" name="price" required></div>
        <button class="btn btn-primary">Save</button>
      </form>
    </div></div>
  </div>
  <?php endif; ?>
</div>
