<?php
/**
 * All 6 Power BI tables in ONE file.
 * Power BI Desktop: Get data → Web → paste this URL → check all sheets → Load
 */
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

include __DIR__ . '/../database/db_connect.php';
include __DIR__ . '/../database/powerbi_data_helper.php';
include __DIR__ . '/../database/powerbi_xlsx_export.php';

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'xlsx';

try {
    $datasets = powerbiBuildAllDatasets($conn);

    if ($format === 'json' || $format === 'bundle') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = array();
        foreach ($datasets as $name => $dataset) {
            $key = strtolower(str_replace(' ', '_', $name));
            $payload[$key] = $dataset['rows'];
        }
        $payload['generated_at'] = gmdate('c');
        $payload['database'] = 'sip_and_pulse_db';
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tmp = powerbiExportWorkbook($datasets);
    $filename = 'sip_and_pulse_powerbi_all_tables.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'Export error: ' . $e->getMessage();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
