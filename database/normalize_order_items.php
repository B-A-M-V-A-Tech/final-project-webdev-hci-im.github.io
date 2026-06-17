<?php
/**
 * One-time script: convert existing orders.items JSON blobs to plain item names.
 * Run: php database/normalize_order_items.php
 */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/order_items_helper.php';

$result = $conn->query('SELECT id, items FROM orders');
if (!$result) {
    fwrite(STDERR, "Query failed: {$conn->error}\n");
    exit(1);
}

$update = $conn->prepare('UPDATE orders SET items = ? WHERE id = ?');
if (!$update) {
    fwrite(STDERR, "Prepare failed: {$conn->error}\n");
    exit(1);
}

$updated = 0;
while ($row = $result->fetch_assoc()) {
    $normalized = orderItemsNormalizeForStorage($row['items']);
    if ($normalized === trim((string) $row['items'])) {
        continue;
    }

    $id = intval($row['id']);
    $update->bind_param('si', $normalized, $id);
    if ($update->execute()) {
        $updated++;
        echo "#{$id}: {$normalized}\n";
    }
}

$update->close();
$conn->close();

echo "Updated {$updated} order(s).\n";
