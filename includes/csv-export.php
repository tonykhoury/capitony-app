<?php
/**
 * Streams an array of associative-array rows as a downloadable CSV.
 * Column order is taken from the keys of the first row.
 */
function stream_csv(array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
    } else {
        fputcsv($out, ['No data']);
    }

    fclose($out);
    exit;
}
