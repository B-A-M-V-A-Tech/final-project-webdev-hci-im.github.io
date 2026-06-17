<?php
/**
 * Restore orders from Google Sheet "Sales Records" export.
 * CLI: php database/import_sheet_orders.php
 */
chdir(dirname(__DIR__));
require __DIR__ . '/db_connect.php';
require __DIR__ . '/analytics_helper.php';

function sheetItemsJson($count, $total) {
    $count = max(1, intval($count));
    $unit = round(floatval($total) / $count, 2);
    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $items[] = ['name' => 'Order item ' . ($i + 1), 'qty' => 1, 'price' => $unit];
    }
    return json_encode($items);
}

function sheetStatus($status) {
    $map = [
        'ready' => 'ready',
        'cancelled' => 'cancelled',
        'preparing' => 'preparing',
        'pending' => 'pending',
        'done' => 'done',
    ];
    $key = strtolower(trim($status));
    return $map[$key] ?? 'ready';
}

function sheetPayment($payment) {
    $p = strtolower(trim((string) $payment));
    if ($p === 'e-wallet' || $p === 'ewallet') {
        return 'E-Wallet';
    }
    if ($p === 'pickup' || $p === 'delivery') {
        return 'Cash';
    }
    return $payment !== '' ? $payment : 'Cash';
}

function sheetFulfillment($value, $fallback = 'pickup') {
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['pickup', 'delivery'], true)) {
        return $v;
    }
    return $fallback;
}

$orders = [
    ['id' => 15, 'order_num' => 'O1011', 'created_at' => '2026-06-17 08:36:03', 'customer_name' => 'Margaret Loraine Malaluan', 'customer_email' => 'margaretlorainemalaluan@gmail.com', 'total_amount' => 255, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'delivery', 'order_source' => 'O', 'items_count' => 1],
    ['id' => 14, 'order_num' => 'O1010', 'created_at' => '2026-06-16 18:43:29', 'customer_name' => 'Marga Malaluan', 'customer_email' => 'margamalaluan20@gmail.com', 'total_amount' => 170, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1],
    ['id' => 13, 'order_num' => 'O1009', 'created_at' => '2026-06-16 18:43:15', 'customer_name' => 'Marga Malaluan', 'customer_email' => 'margamalaluan20@gmail.com', 'total_amount' => 170, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1],
    ['id' => 12, 'order_num' => 'F1003', 'created_at' => '2026-06-16 11:22:18', 'customer_name' => 'Sync Test', 'customer_email' => '', 'total_amount' => 99, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'F', 'items_count' => 1],
    ['id' => 11, 'order_num' => 'O1008', 'created_at' => '2026-06-16 10:45:55', 'customer_name' => 'Marga Malaluan', 'customer_email' => 'margamalaluan20@gmail.com', 'total_amount' => 270, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'delivery', 'order_source' => 'O', 'items_count' => 1],
    ['id' => 10, 'order_num' => 'F1002', 'created_at' => '2026-06-15 21:13:56', 'customer_name' => 'Magi', 'customer_email' => '', 'total_amount' => 175, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'delivery', 'order_source' => 'F', 'items_count' => 1],
    ['id' => 9, 'order_num' => 'O1007', 'created_at' => '2026-06-15 17:17:54', 'customer_name' => 'Amante Andrea karylle', 'customer_email' => 'andreakaryllea@gmail.com', 'total_amount' => 410, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 2],
    ['id' => 8, 'order_num' => 'O1006', 'created_at' => '2026-06-15 17:14:06', 'customer_name' => 'Amante Andrea karylle', 'customer_email' => 'andreakaryllea@gmail.com', 'total_amount' => 240, 'status' => 'Cancelled', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1, 'refund_amount' => 0, 'refund_status' => ''],
    ['id' => 7, 'order_num' => 'O1005', 'created_at' => '2026-06-15 16:58:29', 'customer_name' => 'Amante Andrea karylle', 'customer_email' => 'andreakaryllea@gmail.com', 'total_amount' => 190, 'status' => 'Cancelled', 'payment_method' => 'E-Wallet', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1, 'refund_amount' => 190, 'refund_status' => 'refunded', 'cancel_num' => 'C1001'],
    ['id' => 6, 'order_num' => 'O1001', 'created_at' => '2026-06-15 14:12:38', 'customer_name' => 'Marga Malaluan', 'customer_email' => 'margamalaluan20@gmail.com', 'total_amount' => 225, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1],
    ['id' => 5, 'order_num' => 'F1001', 'created_at' => '2026-06-15 12:47:21', 'customer_name' => 'Walk-in Test', 'customer_email' => '', 'total_amount' => 120, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'F', 'items_count' => 1],
    ['id' => 4, 'order_num' => 'O1002', 'created_at' => '2026-06-15 12:36:26', 'customer_name' => 'Marga Malaluan', 'customer_email' => 'margamalaluan20@gmail.com', 'total_amount' => 225, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1],
    ['id' => 3, 'order_num' => 'O1003', 'created_at' => '2026-06-15 12:28:59', 'customer_name' => 'Test User', 'customer_email' => 'test@test.com', 'total_amount' => 150, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1],
    ['id' => 1, 'order_num' => 'O1004', 'created_at' => '2026-06-15 12:21:26', 'customer_name' => 'Marga Malaluan', 'customer_email' => 'margamalaluan20@gmail.com', 'total_amount' => 225, 'status' => 'Ready', 'payment_method' => 'Cash', 'fulfillment' => 'pickup', 'order_source' => 'O', 'items_count' => 1],
];

echo "Clearing demo orders...\n";
$conn->query('SET FOREIGN_KEY_CHECKS=0');
$conn->query('DELETE FROM sales_analytics_rows');
$conn->query('DELETE FROM orders');
$conn->query('SET FOREIGN_KEY_CHECKS=1');

$stmt = $conn->prepare(
    'INSERT INTO orders
    (id, customer_name, customer_email, items, total_amount, status, fulfillment,
     delivery_location, order_num, order_source, payment_method, notes,
     cancel_num, refund_amount, refund_status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    fwrite(STDERR, 'Prepare failed: ' . $conn->error . PHP_EOL);
    exit(1);
}

foreach ($orders as $order) {
    $id = intval($order['id']);
    $items = sheetItemsJson($order['items_count'], $order['total_amount']);
    $status = sheetStatus($order['status']);
    $payment = sheetPayment($order['payment_method']);
    $fulfillment = sheetFulfillment($order['fulfillment']);
    $delivery = $fulfillment === 'delivery' ? 'Customer address' : '';
    $notes = '';
    $cancelNum = (string) ($order['cancel_num'] ?? '');
    $refundAmount = floatval($order['refund_amount'] ?? 0);
    $refundStatus = (string) ($order['refund_status'] ?? '');
    $createdAt = $order['created_at'];

    $stmt->bind_param(
        'isssdssssssssdss',
        $id,
        $order['customer_name'],
        $order['customer_email'],
        $items,
        $order['total_amount'],
        $status,
        $fulfillment,
        $delivery,
        $order['order_num'],
        $order['order_source'],
        $payment,
        $notes,
        $cancelNum,
        $refundAmount,
        $refundStatus,
        $createdAt
    );
    if (!$stmt->execute()) {
        fwrite(STDERR, 'Insert failed for ' . $order['order_num'] . ': ' . $stmt->error . PHP_EOL);
        exit(1);
    }
    echo 'Imported ' . $order['order_num'] . ' (#' . $id . ')' . PHP_EOL;
}
$stmt->close();

$conn->query('ALTER TABLE orders AUTO_INCREMENT = 16');

$rows = analyticsBuildRowsFromOrders($conn);
analyticsPersistRows($conn, $rows);

$active = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status NOT IN ('done','cancelled')")->fetch_assoc()['c'];
$reviewCount = $conn->query('SELECT COUNT(*) AS c FROM reviews')->fetch_assoc()['c'];

echo PHP_EOL;
echo 'Orders imported: ' . count($orders) . PHP_EOL;
echo 'Analytics rows: ' . count($rows) . PHP_EOL;
echo 'Live queue orders (active): ' . $active . PHP_EOL;
echo 'Reviews in DB: ' . $reviewCount . PHP_EOL;
echo 'Done. Refresh admin dashboard and phpMyAdmin.' . PHP_EOL;

$conn->close();
