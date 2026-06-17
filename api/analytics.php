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

    if ($method === 'GET' && $action === 'export_csv') {
        $rows = analyticsBuildRowsFromOrders($conn);
        ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Content-Disposition: inline; filename="sip_and_pulse_sales.csv"');
        echo analyticsBuildExportCsv($rows);
        exit;
    }

    if ($method === 'GET' && $action === 'config') {
        $config = analyticsGetConfig($conn);
        if (!$config) {
            throw new Exception('Analytics configuration not found.');
        }
        $config = analyticsEnsurePowerBiIds($conn, $config);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'powerbi_title' => $config['powerbi_title'],
                'powerbi_embed_url' => $config['powerbi_embed_url'],
                'google_sheet_url' => $config['google_sheet_url'],
                'google_spreadsheet_id' => $config['google_spreadsheet_id'],
                'google_sheet_name' => $config['google_sheet_name'],
                'google_apps_script_configured' => trim((string) ($config['google_apps_script_url'] ?? '')) !== '',
                'google_apps_script_url' => trim((string) ($config['google_apps_script_url'] ?? '')),
                'powerbi_api_configured' => trim((string) ($config['powerbi_client_id'] ?? '')) !== ''
                    && trim((string) ($config['powerbi_client_secret'] ?? '')) !== '',
                'powerbi_group_id' => $config['powerbi_group_id'] ?? '',
                'powerbi_dataset_id' => $config['powerbi_dataset_id'] ?? '',
                'powerbi_last_refresh_at' => $config['powerbi_last_refresh_at'],
                'powerbi_last_refresh_status' => $config['powerbi_last_refresh_status'],
                'export_csv_url' => 'analytics.php?action=export_csv',
                'last_sync_at' => $config['last_sync_at'],
                'last_sync_status' => $config['last_sync_status'],
            ],
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'save_powerbi_config') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON body.');
        }

        $clientId = trim((string) ($data['powerbi_client_id'] ?? ''));
        $clientSecret = trim((string) ($data['powerbi_client_secret'] ?? ''));
        $groupId = trim((string) ($data['powerbi_group_id'] ?? ''));
        $datasetId = trim((string) ($data['powerbi_dataset_id'] ?? ''));

        $stmt = $conn->prepare(
            'UPDATE analytics_config SET powerbi_client_id = ?, powerbi_client_secret = ?, powerbi_group_id = ?, powerbi_dataset_id = ? ORDER BY id ASC LIMIT 1'
        );
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('ssss', $clientId, $clientSecret, $groupId, $datasetId);
        if (!$stmt->execute()) {
            throw new Exception('Could not save Power BI config: ' . $stmt->error);
        }
        $stmt->close();

        $result = analyticsRunPipeline($conn);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Power BI API saved and refresh triggered.',
            'data' => analyticsFormatApiResult($result),
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'reset_google_setup') {
        $stmt = $conn->prepare(
            'UPDATE analytics_config SET google_apps_script_url = ?, last_sync_status = ?, last_sync_at = NULL ORDER BY id ASC LIMIT 1'
        );
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $empty = '';
        $pending = 'pending';
        $stmt->bind_param('ss', $empty, $pending);
        if (!$stmt->execute()) {
            throw new Exception('Could not reset Google setup: ' . $stmt->error);
        }
        $stmt->close();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Google Sheet setup reset. Deploy a new Apps Script web app and paste the URL below.',
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'save_config') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON body.');
        }

        if (!isset($data['google_apps_script_url'])) {
            throw new Exception('Missing google_apps_script_url.');
        }

        $appsScriptUrl = trim((string) $data['google_apps_script_url']);
        $stmt = $conn->prepare(
            'UPDATE analytics_config SET google_apps_script_url = ? ORDER BY id ASC LIMIT 1'
        );
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('s', $appsScriptUrl);
        if (!$stmt->execute()) {
            throw new Exception('Could not save analytics config: ' . $stmt->error);
        }
        $stmt->close();

        $result = analyticsRunPipeline($conn);
        $sheetOk = is_array($result['google_sheets'] ?? null) && !empty($result['google_sheets']['success']);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'url_saved' => true,
            'sheet_sync_success' => $sheetOk,
            'message' => $sheetOk
                ? 'Google Sheet setup saved and synced successfully.'
                : 'URL saved. Google Sheet sync needs attention — see setup panel.',
            'data' => analyticsFormatApiResult($result),
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
                    'google_apps_script_url' => trim((string) ($config['google_apps_script_url'] ?? '')),
                    'powerbi_api_configured' => trim((string) ($config['powerbi_client_id'] ?? '')) !== ''
                        && trim((string) ($config['powerbi_client_secret'] ?? '')) !== '',
                    'powerbi_last_refresh_at' => $config['powerbi_last_refresh_at'],
                    'powerbi_last_refresh_status' => $config['powerbi_last_refresh_status'],
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
            'data' => analyticsFormatApiResult($result),
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
