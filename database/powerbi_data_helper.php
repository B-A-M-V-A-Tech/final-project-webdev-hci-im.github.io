<?php

require_once __DIR__ . '/order_items_helper.php';

function powerbiFetchOrders($conn) {
    if (function_exists('analyticsBuildRowsFromOrders')) {
        @include_once __DIR__ . '/analytics_helper.php';
        return analyticsBuildRowsFromOrders($conn);
    }

    $rows = array();
    $result = $conn->query(
        "SELECT id AS order_id, order_num, created_at AS order_date, customer_name, customer_email,
                total_amount, status, payment_method, fulfillment, order_source, items
         FROM orders ORDER BY created_at DESC"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['item_count'] = orderItemsCount($row['items'] ?? '');
            unset($row['items']);
            $rows[] = $row;
        }
    }
    return $rows;
}

function powerbiFetchOrderLines($conn) {
    $lines = array();
    $lineId = 1;
    $result = $conn->query(
        "SELECT id AS order_id, items, total_amount FROM orders ORDER BY id ASC"
    );
    if (!$result) {
        return $lines;
    }

    while ($row = $result->fetch_assoc()) {
        $orderId = intval($row['order_id']);
        $itemsRaw = trim((string) ($row['items'] ?? ''));
        $total = floatval($row['total_amount'] ?? 0);
        $decoded = json_decode($itemsRaw, true);

        if (is_array($decoded) && count($decoded) > 0) {
            foreach ($decoded as $entry) {
                $name = orderItemsExtractName($entry);
                if ($name === '') {
                    continue;
                }
                $qty = is_array($entry) ? max(1, intval($entry['qty'] ?? $entry['quantity'] ?? 1)) : 1;
                $unit = 0.0;
                if (is_array($entry)) {
                    $unit = floatval($entry['unitPrice'] ?? $entry['price'] ?? $entry['item']['price'] ?? 0);
                }
                $lineTotal = $unit > 0 ? round($unit * $qty, 2) : 0.0;
                $lines[] = array(
                    'line_id' => $lineId++,
                    'order_id' => $orderId,
                    'product_name' => $name,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $lineTotal,
                );
            }
            continue;
        }

        $name = $itemsRaw !== '' ? $itemsRaw : 'Unspecified item';
        $qty = max(1, orderItemsCount($itemsRaw));
        $lines[] = array(
            'line_id' => $lineId++,
            'order_id' => $orderId,
            'product_name' => $name,
            'quantity' => $qty,
            'unit_price' => $qty > 0 ? round($total / $qty, 2) : $total,
            'line_total' => $total,
        );
    }

    return $lines;
}

function powerbiFetchMenu($conn) {
    $rows = array();
    $result = $conn->query(
        "SELECT id AS menu_id, name AS product_name, category, description, price, available, item_type
         FROM menu_items ORDER BY category, name"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['available'] = intval($row['available']) === 1 ? 'Yes' : 'No';
            $rows[] = $row;
        }
    }
    return $rows;
}

function powerbiFetchReviews($conn) {
    $rows = array();
    $result = $conn->query(
        "SELECT id AS review_id, name AS customer_name, rating, comment, admin_reply,
                CASE WHEN admin_reply IS NOT NULL AND TRIM(admin_reply) <> '' THEN 'Yes' ELSE 'No' END AS has_admin_reply,
                created_at AS review_date
         FROM reviews ORDER BY created_at DESC"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rating = intval($row['rating']);
            if ($rating >= 4) {
                $row['rating_band'] = 'Positive (4-5)';
            } elseif ($rating >= 3) {
                $row['rating_band'] = 'Neutral (3)';
            } else {
                $row['rating_band'] = 'Low (1-2)';
            }
            $rows[] = $row;
        }
    }
    return $rows;
}

function powerbiBuildDailySummary($orderRows) {
    $byDay = array();
    foreach ($orderRows as $row) {
        $day = substr((string) ($row['order_date'] ?? ''), 0, 10);
        if ($day === '') {
            continue;
        }
        if (!isset($byDay[$day])) {
            $byDay[$day] = array(
                'sale_date' => $day,
                'order_count' => 0,
                'net_sales' => 0.0,
                'gross_sales' => 0.0,
                'completed_count' => 0,
                'cancelled_count' => 0,
            );
        }
        $byDay[$day]['order_count'] += 1;
        $amount = floatval($row['total_amount'] ?? 0);
        $byDay[$day]['gross_sales'] += $amount;
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status !== 'cancelled') {
            $byDay[$day]['net_sales'] += $amount;
        } else {
            $byDay[$day]['cancelled_count'] += 1;
        }
        if ($status === 'done') {
            $byDay[$day]['completed_count'] += 1;
        }
    }
    krsort($byDay);
    return array_values($byDay);
}

function powerbiBuildCalendar($orderRows) {
    $dates = array();
    foreach ($orderRows as $row) {
        $day = substr((string) ($row['order_date'] ?? ''), 0, 10);
        if ($day !== '') {
            $dates[$day] = true;
        }
    }
    if (empty($dates)) {
        $dates[gmdate('Y-m-d')] = true;
    }

    $min = min(array_keys($dates));
    $max = max(array_keys($dates));
    $start = new DateTime($min);
    $end = new DateTime($max);
    $end->modify('+30 days');

    $rows = array();
    for ($cursor = clone $start; $cursor <= $end; $cursor->modify('+1 day')) {
        $iso = $cursor->format('Y-m-d');
        $rows[] = array(
            'date' => $iso,
            'year' => intval($cursor->format('Y')),
            'month' => intval($cursor->format('n')),
            'month_name' => $cursor->format('F'),
            'day' => intval($cursor->format('j')),
            'weekday' => $cursor->format('l'),
            'is_weekend' => in_array((int) $cursor->format('N'), array(6, 7), true) ? 'Yes' : 'No',
        );
    }
    return $rows;
}

function powerbiDatasetColumns($resource) {
    $map = array(
        'orders' => array(
            'order_id', 'order_num', 'order_date', 'customer_name', 'customer_email',
            'total_amount', 'status', 'payment_method', 'fulfillment', 'order_source',
            'item_count', 'refund_amount', 'refund_status', 'is_anomaly', 'anomaly_reason',
        ),
        'order_lines' => array('line_id', 'order_id', 'product_name', 'quantity', 'unit_price', 'line_total'),
        'menu' => array('menu_id', 'product_name', 'category', 'description', 'price', 'available', 'item_type'),
        'reviews' => array('review_id', 'customer_name', 'rating', 'rating_band', 'comment', 'has_admin_reply', 'review_date'),
        'daily' => array('sale_date', 'order_count', 'net_sales', 'gross_sales', 'completed_count', 'cancelled_count'),
        'calendar' => array('date', 'year', 'month', 'month_name', 'day', 'weekday', 'is_weekend'),
    );
    return $map[$resource] ?? array();
}

function powerbiDatasetRows($conn, $resource) {
    $orders = powerbiFetchOrders($conn);

    switch ($resource) {
        case 'orders':
            return $orders;
        case 'order_lines':
            return powerbiFetchOrderLines($conn);
        case 'menu':
            return powerbiFetchMenu($conn);
        case 'reviews':
            return powerbiFetchReviews($conn);
        case 'daily':
            return powerbiBuildDailySummary($orders);
        case 'calendar':
            return powerbiBuildCalendar($orders);
        case 'summary':
            if (function_exists('analyticsBuildSummary')) {
                @include_once __DIR__ . '/analytics_helper.php';
                return array(analyticsBuildSummary($orders));
            }
            return array(array('order_count' => count($orders)));
        default:
            throw new Exception('Unknown resource. Use: orders, order_lines, menu, reviews, daily, calendar');
    }
}

function powerbiOutputCsv($rows, $columns) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: inline; filename="sip_and_pulse_powerbi.csv"');
    $out = fopen('php://output', 'w');
    if (!$out) {
        throw new Exception('Could not open CSV output.');
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $columns);
    foreach ($rows as $row) {
        $line = array();
        foreach ($columns as $col) {
            $line[] = $row[$col] ?? '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
}

function powerbiFeedCatalog($baseUrl) {
    $base = rtrim($baseUrl, '/');
    $resources = array('orders', 'order_lines', 'menu', 'reviews', 'daily', 'calendar');
    $feeds = array();
    foreach ($resources as $resource) {
        $feeds[$resource] = array(
            'csv' => $base . '/api/powerbi_feed.php?resource=' . $resource . '&format=csv',
            'json' => $base . '/api/powerbi_feed.php?resource=' . $resource . '&format=flat',
        );
    }
    return $feeds;
}
