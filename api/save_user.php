<?php
include 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['name']) && isset($data['email'])) {
    $name = $data['name'];
    $email = $data['email'];

    // I-check kung nasa database na ang email, kung wala, i-insert
    $query = "INSERT INTO users (name, email) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sss', $name, $email, $name);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}
?>