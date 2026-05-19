<?php
declare(strict_types=1);

namespace App;

final class Csv
{
    /**
     * Stream a CSV download. Sends headers, writes BOM + header row + each
     * data row, and exits. $rows is iterable of associative arrays whose keys
     * match $columns; missing keys are emitted blank.
     */
    public static function download(string $filename, array $columns, iterable $rows): never
    {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        // UTF-8 BOM helps Excel detect encoding correctly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $columns);
        foreach ($rows as $r) {
            $line = [];
            foreach ($columns as $c) {
                $v = $r[$c] ?? '';
                $line[] = $v === null ? '' : (string)$v;
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    /**
     * Parse an uploaded CSV file ($_FILES key). Returns an array of associative
     * rows keyed by the file's first row (header). Strips the UTF-8 BOM if
     * present. Throws RuntimeException on upload/parse errors. Empty trailing
     * rows are skipped.
     *
     * @param string[] $requiredColumns Header names that MUST be present.
     * @return array<int, array<string, string>>
     */
    public static function parseUpload(string $field, array $requiredColumns = []): array
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES[$field]['error'] ?? -1;
            throw new \RuntimeException('No CSV file uploaded (error code ' . $err . ').');
        }
        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            throw new \RuntimeException('Upload tampered.');
        }
        $fh = fopen($_FILES[$field]['tmp_name'], 'r');
        if (!$fh) throw new \RuntimeException('Could not open uploaded file.');

        $header = fgetcsv($fh);
        if (!$header) { fclose($fh); throw new \RuntimeException('CSV is empty or unreadable.'); }
        // Strip BOM from first header cell, trim every column name.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
        $header = array_map(fn($h) => trim((string)$h), $header);

        $missing = array_diff($requiredColumns, $header);
        if ($missing) {
            fclose($fh);
            throw new \RuntimeException('CSV is missing required columns: ' . implode(', ', $missing));
        }

        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === 1 && ($line[0] === null || trim((string)$line[0]) === '')) {
                continue; // skip blank lines
            }
            // Pad/truncate to header length.
            $line = array_pad($line, count($header), '');
            $line = array_slice($line, 0, count($header));
            $assoc = [];
            foreach ($header as $i => $h) {
                $assoc[$h] = is_string($line[$i]) ? trim($line[$i]) : (string)$line[$i];
            }
            $rows[] = $assoc;
        }
        fclose($fh);
        return $rows;
    }

    /** Convenience: "1", "yes", "y", "true", "active" → 1, else 0. */
    public static function bool(?string $v): int
    {
        $v = strtolower(trim((string)$v));
        return in_array($v, ['1', 'yes', 'y', 'true', 'active', 'on'], true) ? 1 : 0;
    }
}
