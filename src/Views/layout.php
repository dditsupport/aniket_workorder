<?php
use App\Auth;
use App\Config;
use App\Helpers;
$appName = (string)Config::get('app_name', 'Inventory & WorkOrder');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Helpers::esc($title ?? $appName) ?> &middot; <?= Helpers::esc($appName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= Helpers::esc(Helpers::url('assets/custom.css')) ?>">
</head>
<body>
<?php if (Auth::check()) require __DIR__ . '/partials/nav.php'; ?>
<main class="container-fluid py-3">
<?php require __DIR__ . '/partials/flash.php'; ?>
<?= $content ?? '' ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
