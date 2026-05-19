<?php
/**
 * Reusable Export / Import CSV toolbar.
 * Required vars:
 *   $csv_export_url  - GET URL that returns the CSV file
 *   $csv_import_url  - POST URL that accepts file=<csv> + _csrf
 *   $csv_modal_id    - unique DOM id for the modal
 *   $csv_required    - comma-separated list of required header columns (string)
 * Optional:
 *   $csv_label       - label shown in modal title ("Customers", "BOM" etc.)
 *   $csv_extra_help  - extra HTML shown beneath the file picker
 */
use App\Csrf;
use App\Helpers;
$label = $csv_label ?? 'CSV';
?>
<a class="btn btn-sm btn-outline-secondary" href="<?= Helpers::esc($csv_export_url) ?>">Export CSV</a>
<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#<?= Helpers::esc($csv_modal_id) ?>">Import CSV</button>

<div class="modal fade" id="<?= Helpers::esc($csv_modal_id) ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" action="<?= Helpers::esc($csv_import_url) ?>">
        <?= Csrf::field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Import <?= Helpers::esc($label) ?> CSV</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary small mb-2">
            Upload a CSV with the same headers as the export. Required columns:
            <code><?= Helpers::esc($csv_required) ?></code>.
            Rows are upserted by their key column; existing records are updated, new ones inserted.
          </p>
          <?php if (!empty($csv_extra_help)): ?>
            <p class="text-secondary small"><?= $csv_extra_help ?></p>
          <?php endif; ?>
          <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>
