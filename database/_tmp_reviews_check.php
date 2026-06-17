<?php
include __DIR__ . '/db_connect.php';
$r = $conn->query('SELECT COUNT(*) AS c FROM reviews');
$row = $r ? $r->fetch_assoc() : ['c' => 'error'];
echo 'count=' . $row['c'] . PHP_EOL;
$q = $conn->query('SELECT id, name, rating, LEFT(comment,40) AS comment, admin_reply IS NOT NULL AND admin_reply != "" AS has_reply FROM reviews ORDER BY id DESC LIMIT 10');
if ($q) {
    while ($rev = $q->fetch_assoc()) {
        echo json_encode($rev) . PHP_EOL;
    }
}
$conn->close();
