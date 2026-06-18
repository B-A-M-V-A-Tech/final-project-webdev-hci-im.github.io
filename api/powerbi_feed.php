<?php
/**
 * Power BI multi-table feed for BI Final Project rubric.
 * Open api/powerbi_index.php in browser for all URLs.
 */
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

include __DIR__ . '/../database/db_connect.php';
include __DIR__ . '/../database/powerbi_data_helper.php';

$resource = isset($_GET['resource']) ? strtolower(trim((string) $_GET['resource'])) : 'orders';
$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'csv';

try {
    $rows = powerbiDatasetRows($conn, $resource);
    $columns = powerbiDatasetColumns($resource);

    if ($format === 'csv') {
        powerbiOutputCsv($rows, $columns);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($format === 'csv') {
        header('Content-Type: text/plain; charset=utf-8', true, 400);
        echo 'Error: ' . $e->getMessage();
    } else {
        header('Content-Type: application/json; charset=utf-8', true, 400);
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
