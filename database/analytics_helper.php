<?php

function analyticsCanonicalSheetConfig() {
    return array(
        'spreadsheet_id' => '1FKCvoXqNIJd7uYAOfTC39lepsegswNsYujECatuSbj8',
        'sheet_url' => 'https://docs.google.com/spreadsheets/d/1FKCvoXqNIJd7uYAOfTC39lepsegswNsYujECatuSbj8/edit?usp=sharing',
        'orders_sheet_name' => 'Sales Records',
        'orders_sheet_aliases' => array('Sales Records', 'Sales Record', 'Sheet1'),
        'daily_sheet_name' => 'Daily Sales & Performance',
        'apps_script_min_version' => 6,
    );
}

function analyticsSalesRecordHeaders() {
    return array(
        'Order ID', 'Order #', 'Date & Time', 'Customer', 'Email', 'Total (PHP)',
        'Status', 'Payment', 'Fulfillment', 'Source', 'Items', 'Refund',
        'Refund Status', 'Anomaly', 'Anomaly Reason',
    );
}

function analyticsFormatStatusForSheet($status) {
    $status = strtolower(trim((string) $status));
    $map = array(
        'pending' => 'Pending',
        'new' => 'New',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
    );
    return $map[$status] ?? ucfirst($status);
}

function analyticsFormatPaymentForSheet($payment) {
    $payment = strtolower(trim((string) $payment));
    if ($payment === '') {
        return '';
    }
    if (
        strpos($payment, 'gcash') !== false ||
        strpos($payment, 'maya') !== false ||
        strpos($payment, 'e-wallet') !== false ||
        strpos($payment, 'ewallet') !== false ||
        strpos($payment, 'g-cash') !== false
    ) {
        return 'E-Wallet';
    }
    if ($payment === 'cash') {
        return 'Cash';
    }
    return ucwords($payment);
}

function analyticsFormatFulfillmentForSheet($fulfillment) {
    $fulfillment = strtolower(trim((string) $fulfillment));
    if ($fulfillment === '') {
        return '';
    }
    return ucfirst($fulfillment);
}

function analyticsFormatRefundStatusForSheet($refundStatus, $orderStatus, $refundAmount) {
    $refundStatus = strtolower(trim((string) $refundStatus));
    if ($refundStatus === 'refunded' || floatval($refundAmount) > 0) {
        return 'Refunded';
    }
    if (strtolower(trim((string) $orderStatus)) === 'cancelled') {
        return 'Not Applicable';
    }
    return 'NO';
}

function analyticsBuildSalesRecordRow($row) {
    return array(
        $row['order_id'],
        $row['order_num'],
        $row['order_date'],
        $row['customer_name'],
        $row['customer_email'],
        $row['total_amount'],
        analyticsFormatStatusForSheet($row['status']),
        analyticsFormatPaymentForSheet($row['payment_method']),
        analyticsFormatFulfillmentForSheet($row['fulfillment']),
        $row['order_source'],
        $row['item_count'],
        $row['refund_amount'],
        analyticsFormatRefundStatusForSheet($row['refund_status'], $row['status'], $row['refund_amount']),
        intval($row['is_anomaly']) === 1 ? 'YES' : 'NO',
        $row['anomaly_reason'],
    );
}

function analyticsGetConfig($conn) {
    $result = $conn->query("SELECT * FROM analytics_config ORDER BY id ASC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row;
    }
    return null;
}

function analyticsCountOrderItems($itemsRaw) {
    $decoded = json_decode((string) $itemsRaw, true);
    if (!is_array($decoded)) {
        return $itemsRaw ? 1 : 0;
    }
    $count = 0;
    foreach ($decoded as $entry) {
        if (is_array($entry)) {
            $count += max(1, intval($entry['qty'] ?? $entry['quantity'] ?? 1));
        } else {
            $count += 1;
        }
    }
    return max($count, count($decoded));
}

function analyticsDetectAnomalies($orders) {
    $totals = array();
    foreach ($orders as $order) {
        $totals[] = floatval($order['total_amount']);
    }

    $avg = 0;
    $std = 0;
    if (count($totals) > 0) {
        $avg = array_sum($totals) / count($totals);
        if (count($totals) > 1) {
            $variance = 0;
            foreach ($totals as $t) {
                $variance += pow($t - $avg, 2);
            }
            $std = sqrt($variance / count($totals));
        }
    }

    $flags = array();
    foreach ($orders as $order) {
        $reasons = array();
        $total = floatval($order['total_amount']);
        $status = strtolower(trim((string) ($order['status'] ?? '')));
        $refund = floatval($order['refund_amount'] ?? 0);
        $itemCount = analyticsCountOrderItems($order['items'] ?? '');

        if ($total <= 0) {
            $reasons[] = 'Zero or negative sale amount';
        }
        if ($avg > 0 && $total > ($avg + max($std * 2, $avg))) {
            $reasons[] = 'Unusually high order total';
        }
        if ($status === 'cancelled' && $refund > 0 && $refund >= $total * 0.5) {
            $reasons[] = 'Large refund on cancelled order';
        }
        if ($itemCount >= 15) {
            $reasons[] = 'Very high item quantity';
        }
        if ($status === 'cancelled' && $total >= ($avg * 1.5) && $avg > 0) {
            $reasons[] = 'High-value cancellation';
        }

        $flags[intval($order['id'])] = array(
            'is_anomaly' => count($reasons) > 0 ? 1 : 0,
            'anomaly_reason' => implode('; ', $reasons),
        );
    }

    return $flags;
}

function analyticsBuildRowsFromOrders($conn) {
    $query = "SELECT id, customer_name, customer_email, items, total_amount, status,
                     fulfillment, delivery_location, order_num, order_source,
                     payment_method, notes, cancel_num, refund_amount, refund_status,
                     cancelled_at, created_at
              FROM orders
              ORDER BY created_at DESC";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $orders = array();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $anomalies = analyticsDetectAnomalies($orders);
    $rows = array();

    foreach ($orders as $order) {
        $id = intval($order['id']);
        $flag = $anomalies[$id] ?? array('is_anomaly' => 0, 'anomaly_reason' => '');
        $rows[] = array(
            'order_id' => $id,
            'order_num' => $order['order_num'],
            'order_date' => $order['created_at'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'total_amount' => floatval($order['total_amount']),
            'status' => $order['status'],
            'payment_method' => $order['payment_method'],
            'fulfillment' => $order['fulfillment'],
            'order_source' => $order['order_source'],
            'item_count' => analyticsCountOrderItems($order['items']),
            'refund_amount' => floatval($order['refund_amount']),
            'refund_status' => $order['refund_status'],
            'is_anomaly' => intval($flag['is_anomaly']),
            'anomaly_reason' => $flag['anomaly_reason'],
        );
    }

    return $rows;
}

function analyticsPersistRows($conn, $rows) {
    $conn->query('DELETE FROM sales_analytics_rows');

    $stmt = $conn->prepare(
        "INSERT INTO sales_analytics_rows
        (order_id, order_num, order_date, customer_name, customer_email, total_amount, status,
         payment_method, fulfillment, order_source, item_count, refund_amount, refund_status,
         is_anomaly, anomaly_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    foreach ($rows as $row) {
        $stmt->bind_param(
            'issssdssssidsis',
            $row['order_id'],
            $row['order_num'],
            $row['order_date'],
            $row['customer_name'],
            $row['customer_email'],
            $row['total_amount'],
            $row['status'],
            $row['payment_method'],
            $row['fulfillment'],
            $row['order_source'],
            $row['item_count'],
            $row['refund_amount'],
            $row['refund_status'],
            $row['is_anomaly'],
            $row['anomaly_reason']
        );
        $stmt->execute();
    }

    $stmt->close();
}

function analyticsBuildSummary($rows) {
    $totalSales = 0;
    $completedSales = 0;
    $anomalyCount = 0;
    $tz = new DateTimeZone('Asia/Manila');
    $today = (new DateTime('now', $tz))->format('Y-m-d');

    foreach ($rows as $row) {
        $amount = floatval($row['total_amount']);
        $status = strtolower(trim((string) $row['status']));
        $totalSales += $amount;
        if (!in_array($status, array('cancelled'), true)) {
            $completedSales += $amount;
        }
        if (intval($row['is_anomaly']) === 1) {
            $anomalyCount += 1;
        }
    }

    $ordersToday = 0;
    $salesToday = 0.0;
    foreach ($rows as $row) {
        if (strpos((string) $row['order_date'], $today) === 0) {
            $ordersToday += 1;
            $status = strtolower(trim((string) $row['status']));
            if (!in_array($status, array('cancelled'), true)) {
                $salesToday += floatval($row['total_amount']);
            }
        }
    }

    return array(
        'order_count' => count($rows),
        'orders_today' => $ordersToday,
        'sales_today' => round($salesToday, 2),
        'total_sales' => round($totalSales, 2),
        'net_sales' => round($completedSales, 2),
        'anomaly_count' => $anomalyCount,
    );
}

function analyticsBase64UrlEncode($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function analyticsGetGoogleAccessToken($config) {
    $email = trim((string) ($config['service_account_email'] ?? ''));
    $privateKey = trim((string) ($config['private_key_pem'] ?? ''));

    if ($email === '' || $privateKey === '') {
        return array('success' => false, 'error' => 'Google service account credentials are not configured in analytics_config table.');
    }

    $privateKey = str_replace('\\n', "\n", $privateKey);
    $now = time();
    $header = analyticsBase64UrlEncode(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
    $claim = analyticsBase64UrlEncode(json_encode(array(
        'iss' => $email,
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    )));
    $unsigned = $header . '.' . $claim;

    $signature = '';
    $signed = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$signed) {
        return array('success' => false, 'error' => 'Could not sign Google API JWT.');
    }

    $jwt = $unsigned . '.' . analyticsBase64UrlEncode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        CURLOPT_POSTFIELDS => http_build_query(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        )),
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $payload = json_decode((string) $response, true);
    if ($httpCode >= 400 || !isset($payload['access_token'])) {
        $message = isset($payload['error_description']) ? $payload['error_description'] : 'Google token request failed.';
        return array('success' => false, 'error' => $message);
    }

    return array('success' => true, 'access_token' => $payload['access_token']);
}

function analyticsRowsToSheetValues($rows) {
    $values = array(analyticsSalesRecordHeaders());
    foreach ($rows as $row) {
        $values[] = analyticsBuildSalesRecordRow($row);
    }
    return $values;
}

function analyticsRowsToSheetDataOnly($rows) {
    $colCount = count(analyticsSalesRecordHeaders());
    $values = array();
    foreach ($rows as $row) {
        $line = analyticsBuildSalesRecordRow($row);
        while (count($line) < $colCount) {
            $line[] = '';
        }
        if (count($line) > $colCount) {
            $line = array_slice($line, 0, $colCount);
        }
        $values[] = $line;
    }
    return $values;
}

function analyticsBuildDailyPerformanceSheetValues($summary, $rows) {
    $tz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);
    $updatedAt = $now->format('Y-m-d H:i:s');

    $values = array(
        array('Daily Sales & Performance', 'Value', 'Notes'),
        array('Total Orders', intval($summary['order_count'] ?? 0), 'All orders in database'),
        array('Orders Today', intval($summary['orders_today'] ?? 0), 'Orders created today (PH time)'),
        array('Net Sales (PHP)', floatval($summary['net_sales'] ?? 0), 'Excludes cancelled orders'),
        array('Today\'s Sales (PHP)', floatval($summary['sales_today'] ?? 0), 'Net sales today (PH time)'),
        array('Anomalies', intval($summary['anomaly_count'] ?? 0), 'Flagged unusual orders'),
        array('Last Sync', $updatedAt, 'Auto-updated from Sip & Pulse admin'),
        array('', '', ''),
        array('Date', 'Orders', 'Net Sales (PHP)'),
    );

    $byDay = array();
    for ($i = 13; $i >= 0; $i--) {
        $day = clone $now;
        $day->modify('-' . $i . ' days');
        $key = $day->format('Y-m-d');
        $byDay[$key] = array('orders' => 0, 'sales' => 0.0);
    }

    foreach ($rows as $row) {
        $dateKey = substr((string) ($row['order_date'] ?? ''), 0, 10);
        if ($dateKey === '' || !isset($byDay[$dateKey])) {
            continue;
        }
        $byDay[$dateKey]['orders'] += 1;
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (!in_array($status, array('cancelled'), true)) {
            $byDay[$dateKey]['sales'] += floatval($row['total_amount'] ?? 0);
        }
    }

    foreach ($byDay as $dateKey => $day) {
        $values[] = array($dateKey, $day['orders'], round($day['sales'], 2));
    }

    return $values;
}

function analyticsSyncGoogleSheetViaApi($config, $rows, $accessToken) {
    $canonical = analyticsCanonicalSheetConfig();
    $spreadsheetId = $canonical['spreadsheet_id'];
    $sheetName = $canonical['orders_sheet_name'];

    $values = analyticsRowsToSheetDataOnly($rows);
    $colCount = count(analyticsSalesRecordHeaders());
    $endRow = max(2, count($values) + 1);
    $range = rawurlencode($sheetName) . '!A2:' . chr(64 + $colCount) . $endRow;
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId) . '/values/' . $range . '?valueInputOption=RAW';

    $clearRange = rawurlencode($sheetName) . '!A2:' . chr(64 + $colCount);
    $clearUrl = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId) . '/values/' . $clearRange . ':clear';

    $headers = array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    );

    $clearCh = curl_init($clearUrl);
    curl_setopt_array($clearCh, array(
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => '{}',
    ));
    curl_exec($clearCh);
    curl_close($clearCh);

    $putCh = curl_init($url);
    curl_setopt_array($putCh, array(
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode(array('values' => $values)),
    ));

    $response = curl_exec($putCh);
    $httpCode = curl_getinfo($putCh, CURLINFO_HTTP_CODE);
    curl_close($putCh);

    $payload = json_decode((string) $response, true);
    if ($httpCode >= 400) {
        $message = isset($payload['error']['message']) ? $payload['error']['message'] : 'Google Sheets update failed.';
        return array('success' => false, 'error' => $message);
    }

    return array(
        'success' => true,
        'updated_cells' => $payload['updatedCells'] ?? count($values) * count($values[0]),
        'spreadsheet_id' => $spreadsheetId,
        'sheet_name' => $sheetName,
        'method' => 'service_account',
    );
}

function analyticsVerifyAppsScriptVersion($config) {
    $url = trim((string) ($config['google_apps_script_url'] ?? ''));
    if ($url === '') {
        return array(
            'success' => false,
            'error' => 'Google Apps Script web app URL is not configured.',
            'needs_script_update' => true,
        );
    }

    $canonical = analyticsCanonicalSheetConfig();
    $spreadsheetId = trim((string) ($config['google_spreadsheet_id'] ?? $canonical['spreadsheet_id']));
    $checkUrl = $url . (strpos($url, '?') !== false ? '&' : '?')
        . 'action=version&spreadsheetId=' . rawurlencode($spreadsheetId);

    $ch = curl_init($checkUrl);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    $payload = json_decode((string) $response, true);
    if (!is_array($payload) && preg_match('/\{.*\}/s', (string) $response, $matches)) {
        $payload = json_decode($matches[0], true);
    }

    $version = is_array($payload) ? intval($payload['version'] ?? 0) : 0;
    if ($version < $canonical['apps_script_min_version']) {
        return array(
            'success' => false,
            'error' => 'Old Apps Script still active. Deploy NEW script v6 (Copy script → New deployment → Save & Sync). Fixes row sync error on Sales Records tab.',
            'needs_script_update' => true,
            'script_version' => $version,
        );
    }

    return array(
        'success' => true,
        'version' => $version,
        'orders_tab' => $payload['orders_tab'] ?? $canonical['orders_sheet_name'],
    );
}

function analyticsSyncGoogleSheetViaAppsScript($config, $rows, $summary) {
    $url = trim((string) ($config['google_apps_script_url'] ?? ''));
    if ($url === '') {
        return array('success' => false, 'error' => 'Google Apps Script web app URL is not configured.');
    }

    $versionCheck = analyticsVerifyAppsScriptVersion($config);
    $versionWarning = !$versionCheck['success'] ? ($versionCheck['error'] ?? '') : '';

    $canonical = analyticsCanonicalSheetConfig();
    $values = analyticsRowsToSheetDataOnly($rows);
    $dailyValues = analyticsBuildDailyPerformanceSheetValues($summary, $rows);
    $body = json_encode(array(
        'spreadsheetId' => $canonical['spreadsheet_id'],
        'sheetName' => $canonical['orders_sheet_name'],
        'values' => $values,
        'ordersDataOnly' => true,
        'dailySheetName' => $canonical['daily_sheet_name'],
        'dailyValues' => $dailyValues,
    ));

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 90,
    ));

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return array('success' => false, 'error' => 'Apps Script request failed: ' . $curlError);
    }

    $payload = json_decode((string) $response, true);
    if (!is_array($payload) && preg_match('/\{.*\}/s', (string) $response, $matches)) {
        $payload = json_decode($matches[0], true);
    }

    if (!is_array($payload) || empty($payload['success'])) {
        $message = is_array($payload) && isset($payload['error'])
            ? $payload['error']
            : 'Google Apps Script sync failed (HTTP ' . $httpCode . ').';
        return array('success' => false, 'error' => $message, 'needs_script_update' => true);
    }

    $dailyRows = intval($payload['daily_rows'] ?? 0);
    $orderRows = intval($payload['rows'] ?? 0);
    $needsScriptUpdate = $dailyRows <= 0 || $versionWarning !== '';

    if ($dailyRows <= 0 && $orderRows <= 0 && count($values) > 0) {
        return array(
            'success' => false,
            'error' => $versionWarning !== ''
                ? $versionWarning
                : 'Could not update Sales Records or Daily Sales & Performance. Deploy NEW Apps Script v5 from admin setup.',
            'needs_script_update' => true,
            'daily_rows' => $dailyRows,
            'method' => 'apps_script',
        );
    }

    if ($dailyRows <= 0) {
        return array(
            'success' => false,
            'error' => 'Sales Records may have updated but Daily Sales & Performance did not. Deploy NEW Apps Script v5 and Save & Sync again.',
            'needs_script_update' => true,
            'daily_rows' => $dailyRows,
            'orders_synced' => $orderRows,
            'method' => 'apps_script',
        );
    }

    return array(
        'success' => true,
        'updated_cells' => $orderRows > 0 ? $orderRows : count($values),
        'daily_rows' => $dailyRows,
        'orders_tab' => $payload['orders_tab'] ?? $canonical['orders_sheet_name'],
        'synced_tabs' => $payload['synced_tabs'] ?? array($canonical['orders_sheet_name'], $canonical['daily_sheet_name']),
        'needs_script_update' => $needsScriptUpdate && $versionWarning !== '',
        'version_warning' => $versionWarning,
        'method' => 'apps_script',
    );
}

function analyticsSyncGoogleSheet($config, $rows) {
    $spreadsheetId = trim((string) ($config['google_spreadsheet_id'] ?? ''));
    if ($spreadsheetId === '') {
        return array('success' => false, 'error' => 'Google Spreadsheet ID is missing in analytics_config.');
    }

    $summary = analyticsBuildSummary($rows);

    $email = trim((string) ($config['service_account_email'] ?? ''));
    $privateKey = trim((string) ($config['private_key_pem'] ?? ''));
    if ($email !== '' && $privateKey !== '') {
        $tokenResult = analyticsGetGoogleAccessToken($config);
        if ($tokenResult['success']) {
            $apiResult = analyticsSyncGoogleSheetViaApi($config, $rows, $tokenResult['access_token']);
            if ($apiResult['success']) {
                $apiResult['daily_sheet'] = 'Daily Sales & Performance (use Apps Script for daily summary tab)';
            }
            return $apiResult;
        }
    }

    $appsScriptUrl = trim((string) ($config['google_apps_script_url'] ?? ''));
    if ($appsScriptUrl !== '') {
        return analyticsSyncGoogleSheetViaAppsScript($config, $rows, $summary);
    }

    return array(
        'success' => false,
        'error' => 'Set up Google Sheet sync: deploy Apps Script (see admin Analytics setup) or add service account credentials.',
    );
}

function analyticsParsePowerBiEmbedUrl($embedUrl) {
    $embedUrl = trim((string) $embedUrl);
    if ($embedUrl === '') {
        return array();
    }

    $parts = parse_url($embedUrl);
    $query = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $token = isset($query['r']) ? (string) $query['r'] : '';
    if ($token === '') {
        return array();
    }

    $padding = strlen($token) % 4;
    if ($padding > 0) {
        $token .= str_repeat('=', 4 - $padding);
    }

    $decoded = json_decode(base64_decode(strtr($token, '-_', '+/')), true);
    if (!is_array($decoded)) {
        return array();
    }

    return array(
        'report_id' => isset($decoded['r']) ? (string) $decoded['r'] : '',
        'tenant_id' => isset($decoded['t']) ? (string) $decoded['t'] : '',
    );
}

function analyticsEnsurePowerBiIds($conn, $config) {
    if (!$config) {
        return $config;
    }

    $tenantId = trim((string) ($config['powerbi_tenant_id'] ?? ''));
    $reportId = trim((string) ($config['powerbi_report_id'] ?? ''));
    $parsed = analyticsParsePowerBiEmbedUrl($config['powerbi_embed_url'] ?? '');
    $nextTenant = $tenantId !== '' ? $tenantId : ($parsed['tenant_id'] ?? '');
    $nextReport = $reportId !== '' ? $reportId : ($parsed['report_id'] ?? '');

    if ($nextTenant === $tenantId && $nextReport === $reportId) {
        return $config;
    }

    $stmt = $conn->prepare(
        'UPDATE analytics_config SET powerbi_tenant_id = ?, powerbi_report_id = ? ORDER BY id ASC LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('ss', $nextTenant, $nextReport);
        $stmt->execute();
        $stmt->close();
    }

    $config['powerbi_tenant_id'] = $nextTenant;
    $config['powerbi_report_id'] = $nextReport;
    return $config;
}

function analyticsGetPowerBiAccessToken($config) {
    $tenantId = trim((string) ($config['powerbi_tenant_id'] ?? ''));
    $clientId = trim((string) ($config['powerbi_client_id'] ?? ''));
    $clientSecret = trim((string) ($config['powerbi_client_secret'] ?? ''));

    if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
        return array('success' => false, 'error' => 'Power BI API credentials not configured.');
    }

    $url = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        CURLOPT_POSTFIELDS => http_build_query(array(
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://analysis.windows.net/powerbi/api/.default',
        )),
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $payload = json_decode((string) $response, true);
    if ($httpCode >= 400 || !isset($payload['access_token'])) {
        $message = isset($payload['error_description']) ? $payload['error_description'] : 'Power BI token request failed.';
        return array('success' => false, 'error' => $message);
    }

    return array('success' => true, 'access_token' => $payload['access_token']);
}

function analyticsPowerBiApiRequest($method, $url, $accessToken, $body = null) {
    $headers = array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    );

    $ch = curl_init($url);
    $options = array(
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 90,
    );
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
    }
    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return array('success' => false, 'error' => 'Power BI API request failed: ' . $curlError);
    }

    $payload = json_decode((string) $response, true);
    if ($httpCode >= 400) {
        $message = is_array($payload) && isset($payload['error']['message'])
            ? $payload['error']['message']
            : 'Power BI API error (HTTP ' . $httpCode . ').';
        return array('success' => false, 'error' => $message, 'http_code' => $httpCode);
    }

    return array('success' => true, 'data' => $payload, 'http_code' => $httpCode);
}

function analyticsResolvePowerBiDatasetId($config, $accessToken) {
    $datasetId = trim((string) ($config['powerbi_dataset_id'] ?? ''));
    if ($datasetId !== '') {
        return array('success' => true, 'dataset_id' => $datasetId);
    }

    $reportId = trim((string) ($config['powerbi_report_id'] ?? ''));
    if ($reportId === '') {
        return array('success' => false, 'error' => 'Power BI report ID is missing.');
    }

    $groupId = trim((string) ($config['powerbi_group_id'] ?? ''));
    $url = $groupId !== ''
        ? 'https://api.powerbi.com/v1.0/myorg/groups/' . rawurlencode($groupId) . '/reports/' . rawurlencode($reportId)
        : 'https://api.powerbi.com/v1.0/myorg/reports/' . rawurlencode($reportId);

    $result = analyticsPowerBiApiRequest('GET', $url, $accessToken);
    if (!$result['success']) {
        return $result;
    }

    $datasetId = isset($result['data']['datasetId']) ? (string) $result['data']['datasetId'] : '';
    if ($datasetId === '') {
        return array('success' => false, 'error' => 'Could not resolve Power BI dataset ID from report.');
    }

    return array('success' => true, 'dataset_id' => $datasetId);
}

function analyticsLogPowerBiRefresh($conn, $status) {
    $conn->query(
        "UPDATE analytics_config SET powerbi_last_refresh_at = NOW(), powerbi_last_refresh_status = '"
        . $conn->real_escape_string($status) . "' ORDER BY id ASC LIMIT 1"
    );
}

function analyticsRefreshPowerBiDataset($conn, $config) {
    $tokenResult = analyticsGetPowerBiAccessToken($config);
    if (!$tokenResult['success']) {
        return array(
            'success' => false,
            'skipped' => true,
            'error' => $tokenResult['error'],
            'hint' => 'Set scheduled refresh in Power BI Service (Dataset → Schedule refresh), or add Power BI API credentials in admin.',
        );
    }

    $datasetResult = analyticsResolvePowerBiDatasetId($config, $tokenResult['access_token']);
    if (!$datasetResult['success']) {
        analyticsLogPowerBiRefresh($conn, 'failed');
        return $datasetResult;
    }

    $datasetId = $datasetResult['dataset_id'];
    $groupId = trim((string) ($config['powerbi_group_id'] ?? ''));
    $refreshUrl = $groupId !== ''
        ? 'https://api.powerbi.com/v1.0/myorg/groups/' . rawurlencode($groupId) . '/datasets/' . rawurlencode($datasetId) . '/refreshes'
        : 'https://api.powerbi.com/v1.0/myorg/datasets/' . rawurlencode($datasetId) . '/refreshes';

    $refreshResult = analyticsPowerBiApiRequest('POST', $refreshUrl, $tokenResult['access_token'], '{}');
    if (!$refreshResult['success']) {
        analyticsLogPowerBiRefresh($conn, 'failed');
        return $refreshResult;
    }

    analyticsLogPowerBiRefresh($conn, 'triggered');
    return array(
        'success' => true,
        'status' => 'triggered',
        'dataset_id' => $datasetId,
        'message' => 'Power BI dataset refresh started.',
    );
}

function analyticsVerifySheetRowCount($config, $expectedRows) {
    $url = trim((string) ($config['google_apps_script_url'] ?? ''));
    if ($url === '') {
        return array('success' => false, 'error' => 'Apps Script URL missing.');
    }

    $canonical = analyticsCanonicalSheetConfig();
    $sheetName = $canonical['orders_sheet_name'];
    $spreadsheetId = $canonical['spreadsheet_id'];
    $checkUrl = $url . (strpos($url, '?') !== false ? '&' : '?')
        . 'action=count&sheetName=' . rawurlencode($sheetName)
        . '&spreadsheetId=' . rawurlencode($spreadsheetId);

    $ch = curl_init($checkUrl);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    $payload = json_decode((string) $response, true);
    if (!is_array($payload) || empty($payload['success'])) {
        return array('success' => false, 'error' => 'Could not verify Google Sheet row count.');
    }

    $rows = intval($payload['rows'] ?? 0);
    return array(
        'success' => true,
        'rows' => $rows,
        'matches' => $rows === intval($expectedRows),
    );
}

function analyticsBuildExportCsv($rows) {
    $values = analyticsRowsToSheetValues($rows);
    $handle = fopen('php://temp', 'r+');
    foreach ($values as $line) {
        fputcsv($handle, $line);
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return $csv;
}

function analyticsLogSync($conn, $status, $message, $rowCount) {
    $stmt = $conn->prepare(
        "INSERT INTO analytics_sync_log (sync_status, message, row_count) VALUES (?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('ssi', $status, $message, $rowCount);
        $stmt->execute();
        $stmt->close();
    }

    $conn->query(
        "UPDATE analytics_config SET last_sync_at = NOW(), last_sync_status = '"
        . $conn->real_escape_string($status) . "' ORDER BY id ASC LIMIT 1"
    );
}

function analyticsRunPipeline($conn) {
    $config = analyticsGetConfig($conn);
    if (!$config) {
        throw new Exception('Analytics configuration not found.');
    }

    $config = analyticsEnsurePowerBiIds($conn, $config);

    $rows = analyticsBuildRowsFromOrders($conn);
    analyticsPersistRows($conn, $rows);
    $summary = analyticsBuildSummary($rows);

    $sheetResult = analyticsSyncGoogleSheet($config, $rows);
    $sheetVerify = null;
    $powerBiResult = null;

    if ($sheetResult['success']) {
        $method = isset($sheetResult['method']) ? $sheetResult['method'] : 'api';
        analyticsLogSync(
            $conn,
            'success',
            'Synced ' . count($rows) . ' orders + Daily Sales & Performance to Google Sheets (' . $method . ').',
            count($rows)
        );
        $sheetVerify = analyticsVerifySheetRowCount($config, count($rows));
        $powerBiResult = analyticsRefreshPowerBiDataset($conn, $config);
    } else {
        analyticsLogSync(
            $conn,
            'partial',
            'Database updated. Google Sheets: ' . $sheetResult['error'],
            count($rows)
        );
    }

    $freshConfig = analyticsGetConfig($conn);

    return array(
        'summary' => $summary,
        'rows' => $rows,
        'google_sheets' => $sheetResult,
        'google_sheet_verify' => $sheetVerify,
        'powerbi_refresh' => $powerBiResult,
        'config' => array(
            'powerbi_embed_url' => $config['powerbi_embed_url'],
            'google_sheet_url' => $config['google_sheet_url'],
            'google_apps_script_url' => $config['google_apps_script_url'] ?? '',
            'powerbi_last_refresh_at' => $freshConfig ? $freshConfig['powerbi_last_refresh_at'] : null,
            'powerbi_last_refresh_status' => $freshConfig ? $freshConfig['powerbi_last_refresh_status'] : 'pending',
            'powerbi_api_configured' => trim((string) ($config['powerbi_client_id'] ?? '')) !== ''
                && trim((string) ($config['powerbi_client_secret'] ?? '')) !== '',
            'last_sync_at' => $freshConfig ? $freshConfig['last_sync_at'] : null,
            'last_sync_status' => $freshConfig ? $freshConfig['last_sync_status'] : 'partial',
        ),
    );
}

function analyticsTriggerAfterOrderChange($conn) {
    try {
        analyticsRunPipeline($conn);
    } catch (Throwable $e) {
        error_log('Analytics sync after order change failed: ' . $e->getMessage());
    }
}

function analyticsTriggerAfterOrderChangeDeferred() {
    register_shutdown_function(function () {
        $asyncConn = null;
        try {
            if (!function_exists('dbNewConnection')) {
                require_once __DIR__ . '/db_connect.php';
            }
            if (!function_exists('analyticsRunPipeline')) {
                require_once __DIR__ . '/analytics_helper.php';
            }
            $asyncConn = dbNewConnection();
            analyticsTriggerAfterOrderChange($asyncConn);
        } catch (Throwable $e) {
            error_log('Deferred analytics sync failed: ' . $e->getMessage());
        } finally {
            if ($asyncConn instanceof mysqli) {
                $asyncConn->close();
            }
        }
    });
}

function analyticsFormatApiResult($result) {
    if (!is_array($result)) {
        return $result;
    }
    unset($result['rows']);
    return $result;
}

?>
