<?php

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
    $today = date('Y-m-d');

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
    foreach ($rows as $row) {
        if (strpos((string) $row['order_date'], $today) === 0) {
            $ordersToday += 1;
        }
    }

    return array(
        'order_count' => count($rows),
        'orders_today' => $ordersToday,
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
    $values = array(array(
        'Order ID', 'Order #', 'Date', 'Customer', 'Email', 'Total (PHP)',
        'Status', 'Payment', 'Fulfillment', 'Source', 'Items', 'Refund',
        'Refund Status', 'Anomaly', 'Anomaly Reason',
    ));

    foreach ($rows as $row) {
        $values[] = array(
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
            intval($row['is_anomaly']) === 1 ? 'YES' : 'NO',
            $row['anomaly_reason'],
        );
    }

    return $values;
}

function analyticsSyncGoogleSheet($config, $rows) {
    $spreadsheetId = trim((string) ($config['google_spreadsheet_id'] ?? ''));
    $sheetName = trim((string) ($config['google_sheet_name'] ?? 'Sheet1'));

    if ($spreadsheetId === '') {
        return array('success' => false, 'error' => 'Google Spreadsheet ID is missing in analytics_config.');
    }

    $tokenResult = analyticsGetGoogleAccessToken($config);
    if (!$tokenResult['success']) {
        return $tokenResult;
    }

    $values = analyticsRowsToSheetValues($rows);
    $range = rawurlencode($sheetName) . '!A1';
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId) . '/values/' . $range . '?valueInputOption=RAW';

    $clearUrl = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId) . '/values/' . rawurlencode($sheetName) . ':clear';

    $headers = array(
        'Authorization: Bearer ' . $tokenResult['access_token'],
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
    );
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
        "UPDATE analytics_config SET last_sync_at = NOW(), last_sync_status = '" . $conn->real_escape_string($status) . "'
         ORDER BY id ASC LIMIT 1"
    );
}

function analyticsRunPipeline($conn) {
    $config = analyticsGetConfig($conn);
    if (!$config) {
        throw new Exception('Analytics configuration not found.');
    }

    $rows = analyticsBuildRowsFromOrders($conn);
    analyticsPersistRows($conn, $rows);
    $summary = analyticsBuildSummary($rows);

    $sheetResult = analyticsSyncGoogleSheet($config, $rows);
    if ($sheetResult['success']) {
        analyticsLogSync(
            $conn,
            'success',
            'Synced ' . count($rows) . ' rows to Google Sheets for Power BI.',
            count($rows)
        );
    } else {
        analyticsLogSync(
            $conn,
            'partial',
            'Database updated. Google Sheets: ' . $sheetResult['error'],
            count($rows)
        );
    }

    return array(
        'summary' => $summary,
        'rows' => $rows,
        'google_sheets' => $sheetResult,
        'config' => array(
            'powerbi_embed_url' => $config['powerbi_embed_url'],
            'google_sheet_url' => $config['google_sheet_url'],
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_sync_status' => $sheetResult['success'] ? 'success' : 'partial',
        ),
    );
}

?>
