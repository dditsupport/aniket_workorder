<?php
use App\Auth;
use App\Config;
use App\Helpers;
$u = Auth::user();
$role = $u['role'] ?? '';
?>
<nav class="navbar navbar-expand-lg bg-dark navbar-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= Helpers::esc(Helpers::url('/')) ?>"><?= Helpers::esc((string)Config::get('app_name', 'Inventory')) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= Helpers::esc(Helpers::url('/work-orders')) ?>">Work Orders</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= Helpers::esc(Helpers::url('/sales')) ?>">Sales</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= Helpers::esc(Helpers::url('/receipts')) ?>">Receipts</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Masters</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/customers')) ?>">Customers</a></li>
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/raw-materials')) ?>">Raw Materials</a></li>
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/products')) ?>">Products (Final)</a></li>
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/price-list')) ?>">Customer Price List</a></li>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Reports</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/reports/rm-stock')) ?>">RM Stock</a></li>
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/reports/fg-stock')) ?>">Final Product Stock</a></li>
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/reports/wo-status')) ?>">WO Status</a></li>
            <li><a class="dropdown-item" href="<?= Helpers::esc(Helpers::url('/reports/customer-outstanding')) ?>">Customer Outstanding</a></li>
          </ul>
        </li>
        <?php if ($role === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= Helpers::esc(Helpers::url('/users')) ?>">Users</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><span class="navbar-text me-3"><?= Helpers::esc($u['name'] ?? '') ?> <small class="text-secondary">(<?= Helpers::esc($role) ?>)</small></span></li>
        <li class="nav-item"><a class="nav-link" href="<?= Helpers::esc(Helpers::url('/logout')) ?>">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
