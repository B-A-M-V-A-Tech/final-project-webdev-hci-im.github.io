<?php
header('Content-Type: application/json');
include '../database/db_connect.php';

try {
    // Get all menu items from database
    $query = "SELECT id, name, category, description, price, image_url, available, item_type FROM menu_items ORDER BY category, name";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Database error: " . $conn->error);
    }

    $menuItems = array();
    while ($row = $result->fetch_assoc()) {
        $menuItems[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $menuItems
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>