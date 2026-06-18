<?php

function ensurePowerBiFeedKey($conn) {
    ensureColumn($conn, 'analytics_config', 'powerbi_feed_key', "VARCHAR(64) DEFAULT ''");

    $result = $conn->query('SELECT id, powerbi_feed_key FROM analytics_config ORDER BY id ASC LIMIT 1');
    if (!$result || !($row = $result->fetch_assoc())) {
        return '';
    }

    $key = trim((string) ($row['powerbi_feed_key'] ?? ''));
    if ($key !== '') {
        return $key;
    }

    try {
        $key = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $key = sha1(uniqid('sip_pulse_', true));
    }

    $stmt = $conn->prepare('UPDATE analytics_config SET powerbi_feed_key = ? WHERE id = ?');
    if ($stmt) {
        $id = intval($row['id']);
        $stmt->bind_param('si', $key, $id);
        $stmt->execute();
        $stmt->close();
    }

    return $key;
}

function powerBiFeedValidateKey($conn, $providedKey) {
    $expected = ensurePowerBiFeedKey($conn);
    $providedKey = trim((string) $providedKey);
    if ($expected === '' || $providedKey === '') {
        return false;
    }
    return hash_equals($expected, $providedKey);
}

function ensurePowerBiViews($conn) {
    $conn->query('DROP VIEW IF EXISTS vw_powerbi_daily_sales');
    $conn->query('DROP VIEW IF EXISTS vw_powerbi_orders');

    $conn->query("CREATE VIEW vw_powerbi_orders AS
        SELECT
            o.id AS order_id,
            COALESCE(NULLIF(o.order_num, ''), CONCAT('ORD-', o.id)) AS order_num,
            o.created_at AS order_date,
            DATE(o.created_at) AS order_day,
            o.customer_name,
            COALESCE(o.customer_email, '') AS customer_email,
            o.total_amount,
            o.status,
            o.payment_method,
            o.fulfillment,
            COALESCE(o.order_source, 'O') AS order_source,
            o.refund_amount,
            o.refund_status,
            o.cancelled_at,
            o.notes
        FROM orders o");

    $conn->query("CREATE VIEW vw_powerbi_daily_sales AS
        SELECT
            DATE(o.created_at) AS sales_date,
            COUNT(*) AS order_count,
            SUM(CASE WHEN LOWER(COALESCE(o.status, '')) != 'cancelled' THEN o.total_amount ELSE 0 END) AS net_sales,
            SUM(CASE WHEN LOWER(COALESCE(o.status, '')) = 'done' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN LOWER(COALESCE(o.status, '')) = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            SUM(CASE WHEN DATE(o.created_at) = CURDATE() AND LOWER(COALESCE(o.status, '')) != 'cancelled' THEN o.total_amount ELSE 0 END) AS sales_today_component
        FROM orders o
        GROUP BY DATE(o.created_at)");
}

function powerBiFeedBuildOrders($conn) {
    if (!function_exists('analyticsBuildRowsFromOrders')) {
        require_once __DIR__ . '/analytics_helper.php';
    }

    $rows = analyticsBuildRowsFromOrders($conn);
    analyticsPersistRows($conn, $rows);

    $value = array();
    foreach ($rows as $row) {
        $value[] = array(
            'order_id' => intval($row['order_id']),
            'order_num' => $row['order_num'],
            'order_date' => $row['order_date'],
            'order_day' => substr((string) $row['order_date'], 0, 10),
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'total_amount' => floatval($row['total_amount']),
            'status' => analyticsFormatStatusForSheet($row['status']),
            'payment_method' => analyticsFormatPaymentForSheet($row['payment_method']),
            'fulfillment' => analyticsFormatFulfillmentForSheet($row['fulfillment']),
            'order_source' => $row['order_source'],
            'item_count' => intval($row['item_count']),
            'refund_amount' => floatval($row['refund_amount']),
            'refund_status' => analyticsFormatRefundStatusForSheet($row['refund_status'], $row['status'], $row['refund_amount']),
            'is_anomaly' => intval($row['is_anomaly']) === 1 ? 'YES' : 'NO',
            'anomaly_reason' => $row['anomaly_reason'],
        );
    }

    return $value;
}

function powerBiFeedBuildDaily($conn) {
    $value = array();
    $result = $conn->query('SELECT * FROM vw_powerbi_daily_sales ORDER BY sales_date DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $value[] = array(
                'sales_date' => $row['sales_date'],
                'order_count' => intval($row['order_count']),
                'net_sales' => floatval($row['net_sales']),
                'completed_orders' => intval($row['completed_orders']),
                'cancelled_orders' => intval($row['cancelled_orders']),
            );
        }
    }
    return $value;
}

function powerBiFeedBuildSummary($conn) {
    if (!function_exists('analyticsBuildSummary')) {
        require_once __DIR__ . '/analytics_helper.php';
    }
    $rows = analyticsBuildRowsFromOrders($conn);
    return analyticsBuildSummary($rows);
}

function powerBiFeedBaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/api/powerbi_feed.php');
    $apiDir = dirname($script);
    $projectBase = dirname($apiDir);
    if ($projectBase === '/' || $projectBase === '\\' || $projectBase === '.') {
        $projectBase = '';
    }
    return rtrim($scheme . '://' . $host . $projectBase, '/');
}

function powerBiFeedUrls($conn) {
    $key = ensurePowerBiFeedKey($conn);
    $base = powerBiFeedBaseUrl() . '/api/powerbi_feed.php';
    $query = 'key=' . rawurlencode($key);
    return array(
        'feed_key' => $key,
        'orders_url' => $base . '?resource=orders&' . $query,
        'daily_url' => $base . '?resource=daily&' . $query,
        'summary_url' => $base . '?resource=summary&' . $query,
        'all_url' => $base . '?resource=all&' . $query,
    );
}
