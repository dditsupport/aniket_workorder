<?php
use App\Helpers;
foreach (Helpers::takeFlash() as $f):
    $cls = match ($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
?>
<div class="alert <?= $cls ?> alert-dismissible fade show" role="alert">
  <?= Helpers::esc($f['msg']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>
