<?php
chdir(dirname(__DIR__));
require __DIR__ . '/db_connect.php';
require __DIR__ . '/analytics_helper.php';

function tableCount($conn, $table) {
    $safe = preg_replace('/[^a-z0-9_]/i', '', $table);
    $result = $conn->query("SELECT COUNT(*) AS c FROM `$safe`");
    if (!$result) {
        return -1;
    }
    $row = $result->fetch_assoc();
    return intval($row['c']);
}

echo "=== Sip & Pulse admin dashboard repair ===\n";

$orders = tableCount($conn, 'orders');
$menu = tableCount($conn, 'menu_items');
$reviews = tableCount($conn, 'reviews');
$analyticsRows = tableCount($conn, 'sales_analytics_rows');
$config = analyticsGetConfig($conn);

echo "orders: $orders\n";
echo "menu_items: $menu\n";
echo "reviews: $reviews\n";
echo "sales_analytics_rows (before): $analyticsRows\n";
echo "analytics_config: " . ($config ? 'ok' : 'missing') . "\n";

if ($config) {
    echo "powerbi_embed_url: " . ($config['powerbi_embed_url'] ?: 'missing') . "\n";
}

$rows = analyticsBuildRowsFromOrders($conn);
analyticsPersistRows($conn, $rows);
$summary = analyticsBuildSummary($rows);

echo "sales_analytics_rows (after): " . count($rows) . "\n";
echo "summary orders: " . intval($summary['order_count'] ?? 0) . "\n";
echo "summary net sales: " . number_format(floatval($summary['net_sales'] ?? 0), 2) . "\n";

if (!$config || trim((string) ($config['powerbi_embed_url'] ?? '')) === '') {
    echo "WARNING: Power BI embed URL missing in analytics_config.\n";
} else {
    echo "Power BI embed: ready\n";
}

echo "Done. Refresh Admin dashboard (XAMPP Apache + MySQL must be running).\n";

$conn->close();
