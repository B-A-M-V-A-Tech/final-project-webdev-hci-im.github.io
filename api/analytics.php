<?php
ob_start();
header('Content-Type: application/json');
include __DIR__ . '/cors.php';
allowApiCors();
include '../database/db_connect.php';
include '../database/analytics_helper.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'summary';

    if ($method === 'GET' && $action === 'config') {
        $config = analyticsGetConfig($conn);
        if (!$config) {
            throw new Exception('Analytics configuration not found.');
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'powerbi_title' => $config['powerbi_title'],
                'powerbi_embed_url' => $config['powerbi_embed_url'],
                'google_sheet_url' => $config['google_sheet_url'],
                'google_spreadsheet_id' => $config['google_spreadsheet_id'],
                'google_sheet_name' => $config['google_sheet_name'],
                'last_sync_at' => $config['last_sync_at'],
                'last_sync_status' => $config['last_sync_status'],
            ],
        ]);
        exit;
    }

    if ($method === 'GET' && $action === 'summary') {
        $rows = analyticsBuildRowsFromOrders($conn);
        analyticsPersistRows($conn, $rows);
        $summary = analyticsBuildSummary($rows);
        $config = analyticsGetConfig($conn);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'config' => $config ? [
                    'powerbi_embed_url' => $config['powerbi_embed_url'],
                    'google_sheet_url' => $config['google_sheet_url'],
                    'last_sync_at' => $config['last_sync_at'],
                    'last_sync_status' => $config['last_sync_status'],
                ] : null,
            ],
        ]);
        exit;
    }

    if (($method === 'GET' || $method === 'POST') && $action === 'sync') {
        $result = analyticsRunPipeline($conn);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Sales analytics synced from database.',
            'data' => $result,
        ]);
        exit;
    }

    throw new Exception('Invalid analytics action: ' . $action);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
} finally {
    $conn->close();
}
?>
