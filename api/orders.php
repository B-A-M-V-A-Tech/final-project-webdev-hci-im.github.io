<?php
header('Content-Type: application/json');
include '../db_connect.php';
include '../admin_session.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($method === 'GET' && $action === 'get_orders') {
        // Get all orders (admin only)
        requireAdminLogin();

        $query = "SELECT id, customer_name, customer_email, items, total_amount, status, fulfillment, delivery_location, created_at FROM orders ORDER BY created_at DESC";
        $result = $conn->query($query);

        if (!$result) {
            throw new Exception("Database error: " . $conn->error);
        }

        $orders = array();
        while ($row = $result->fetch_assoc()) {
            $row['items'] = json_decode($row['items'], true);
            $orders[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $orders
        ]);

    } elseif ($method === 'POST' && $action === 'save_order') {
        // Save new order (client side - no auth needed)
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['customer_name'], $data['items'])) {
            throw new Exception('Missing required fields: customer_name and items');
        }

        $customer_name = $data['customer_name'];
        $customer_email = $data['customer_email'] ?? '';
        $items = json_encode($data['items']);
        $total_amount = floatval($data['total_amount']);
        $fulfillment = $data['fulfillment'] ?? 'pickup';
        $delivery_location = $data['delivery_location'] ?? '';

        $query = "INSERT INTO orders (customer_name, customer_email, items, total_amount, fulfillment, delivery_location, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('sssdss', $customer_name, $customer_email, $items, $total_amount, $fulfillment, $delivery_location);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $orderId = $conn->insert_id;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully',
            'order_id' => $orderId
        ]);

    } elseif ($method === 'POST' && $action === 'update_status') {
        // Update order status (admin only)
        requireAdminLogin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'], $data['status'])) {
            throw new Exception('Missing required fields');
        }

        $id = intval($data['id']);
        $status = $data['status'];

        $query = "UPDATE orders SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('si', $status, $id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully'
        ]);

    } else {
        throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>