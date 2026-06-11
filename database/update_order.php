<?php
header("Content-Type: application/json");
require_once 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';
$orderId = $conn->real_escape_string($data['orderId'] ?? '');

if ($action === 'complete') {
    $sql = "UPDATE orders SET status = 'Completed' WHERE order_id = '$orderId'";
} elseif ($action === 'delete') {
    $sql = "DELETE FROM orders WHERE order_id = '$orderId'";
} else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
    exit;
}

if ($conn->query($sql) === TRUE) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>