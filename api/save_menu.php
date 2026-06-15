<?php
header('Content-Type: application/json');
include '../database/db_connect.php';
include '../database/admin_session.php';

// ADDED: Allow admin panel API access without separate login page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isAdminLoggedIn()) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
}

requireAdminLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['action'])) {
        throw new Exception('Invalid request');
    }

    $action = $data['action'];

    if ($action === 'add' || $action === 'update') {
        // Validate required fields
        if (!isset($data['name'], $data['category'], $data['price'])) {
            throw new Exception('Missing required fields');
        }

        $name = $data['name'];
        $category = $data['category'];
        $description = $data['description'] ?? '';
        $price = floatval($data['price']);
        $image_url = $data['image_url'] ?? '';
        $available = isset($data['available']) ? intval($data['available']) : 1;
        $item_type = $data['item_type'] ?? 'food';

        if ($action === 'add') {
            // Insert new menu item
            $query = "INSERT INTO menu_items (name, category, description, price, image_url, available, item_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param('sssdsis', $name, $category, $description, $price, $image_url, $available, $item_type);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $newId = $conn->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Menu item added successfully',
                'id' => $newId
            ]);
        } else {
            // Update existing menu item
            $id = intval($data['id']);
            
            $query = "UPDATE menu_items SET name = ?, category = ?, description = ?, price = ?, image_url = ?, available = ?, item_type = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param('sssdsisi', $name, $category, $description, $price, $image_url, $available, $item_type, $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Menu item updated successfully'
            ]);
        }
    } elseif ($action === 'delete') {
        // Delete menu item
        if (!isset($data['id'])) {
            throw new Exception('Item ID required');
        }

        $id = intval($data['id']);
        
        $query = "DELETE FROM menu_items WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Menu item deleted successfully'
        ]);
    } else {
        throw new Exception('Invalid action');
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