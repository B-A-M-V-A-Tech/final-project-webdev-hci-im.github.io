<?php
header("Content-Type: application/json");
require_once 'db_connect.php'; 

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received"]);
    exit;
}

$orderId = $conn->real_escape_string($data['orderId']);
$total    = floatval($data['total']);
$status   = $conn->real_escape_string($data['status']);

$items    = $conn->real_escape_string(json_encode($data['items']));

$sql = "INSERT INTO orders (order_id, items, total, status) VALUES ('$orderId', '$items', '$total', '$status')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>