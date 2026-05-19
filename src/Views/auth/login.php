<?php
use App\Config;
use App\Csrf;
use App\Helpers;
?>
<div class="row justify-content-center mt-5">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title text-center mb-3"><?= Helpers::esc((string)Config::get('app_name')) ?></h3>
        <form method="post" action="<?= Helpers::esc(Helpers::url('/login')) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" autofocus required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required>
          </div>
          <button class="btn btn-primary w-100">Sign in</button>
        </form>
      </div>
    </div>
  </div>
</div>
