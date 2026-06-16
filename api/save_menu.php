<?php
include __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json');
include '../database/db_connect.php';
include '../database/admin_session.php';

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'You must be signed in as an administrator.'
    ]);
    exit;
}

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
            if (isset($data['id']) && intval($data['id']) > 0) {
                throw new Exception('New menu items must not include an ID.');
            }

            $query = "INSERT INTO menu_items (name, category, description, price, image_url, available, item_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param('sssdsis', $name, $category, $description, $price, $image_url, $available, $item_type);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $newId = intval($conn->insert_id);
            $stmt->close();

            if ($newId <= 0) {
                throw new Exception('Could not allocate a new menu item ID. Reload admin and try again.');
            }

            $isAvailableCol = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'is_available'");
            if ($isAvailableCol && $isAvailableCol->num_rows > 0) {
                $syncStmt = $conn->prepare('UPDATE menu_items SET is_available = ? WHERE id = ?');
                if ($syncStmt) {
                    $syncStmt->bind_param('ii', $available, $newId);
                    $syncStmt->execute();
                    $syncStmt->close();
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Menu item added successfully',
                'id' => $newId,
                'image_url' => $image_url
            ]);
        } else {
            // Update existing menu item
            $id = intval($data['id']);
            if ($id <= 0) {
                throw new Exception('Invalid menu item ID for update.');
            }
            
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

            $isAvailableCol = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'is_available'");
            if ($isAvailableCol && $isAvailableCol->num_rows > 0) {
                $syncStmt = $conn->prepare('UPDATE menu_items SET is_available = ? WHERE id = ?');
                if ($syncStmt) {
                    $syncStmt->bind_param('ii', $available, $id);
                    $syncStmt->execute();
                    $syncStmt->close();
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Menu item updated successfully',
                'id' => $id,
                'image_url' => $image_url
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