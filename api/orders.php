<?php
ob_start();
header('Content-Type: application/json');

$sessionFile = __DIR__ . '/../database/admin_session.php';
if (!file_exists($sessionFile)) {
    $sessionFile = __DIR__ . '/admin_session.php';
}

include __DIR__ . '/../database/db_connect.php';
include $sessionFile;

function triggerOrdersAnalyticsSync() {
    @include_once __DIR__ . '/../database/analytics_helper.php';
    if (function_exists('analyticsTriggerAfterOrderChangeDeferred')) {
        analyticsTriggerAfterOrderChangeDeferred();
    }
}

function uiStatusToDb($status) {
    $status = strtolower(trim((string) $status));
    if ($status === 'new') {
        return 'pending';
    }
    if (in_array($status, ['pending', 'preparing', 'ready', 'done', 'cancelled'], true)) {
        return $status;
    }
    return 'pending';
}

function dbStatusToUi($status) {
    $status = strtolower(trim((string) $status));
    if ($status === 'pending') {
        return 'new';
    }
    return $status;
}

function isRefundableEwalletPayment($paymentMethod) {
    $m = strtolower(trim((string) $paymentMethod));
    if ($m === '') {
        return false;
    }
    return (
        strpos($m, 'gcash') !== false ||
        strpos($m, 'maya') !== false ||
        strpos($m, 'e-wallet') !== false ||
        strpos($m, 'ewallet') !== false ||
        strpos($m, 'g-cash') !== false
    );
}

function nextCancelOrderNumber($conn) {
    $like = 'C%';
    $stmt = $conn->prepare(
        "SELECT cancel_num FROM orders
         WHERE cancel_num LIKE ?
         ORDER BY CAST(SUBSTRING(cancel_num, 2) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $next = 1001;
    if ($row = $result->fetch_assoc()) {
        $num = $row['cancel_num'];
        if (preg_match('/^C(\d+)$/', $num, $matches)) {
            $next = intval($matches[1]) + 1;
        }
    }
    $stmt->close();
    return 'C' . $next;
}

function nextOrderNumber($conn, $source) {
    $prefix = ($source === 'F') ? 'F' : 'O';
    $like = $prefix . '%';
    $stmt = $conn->prepare(
        "SELECT order_num FROM orders
         WHERE order_source = ? AND order_num LIKE ?
         ORDER BY CAST(SUBSTRING(order_num, 2) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->bind_param('ss', $source, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $next = 1001;
    if ($row = $result->fetch_assoc()) {
        $num = $row['order_num'];
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $num, $matches)) {
            $next = intval($matches[1]) + 1;
        }
    }
    $stmt->close();
    return $prefix . $next;
}

function normalizeOrderSource($source) {
    $source = strtoupper(substr(trim((string) $source), 0, 1));
    return ($source === 'F') ? 'F' : 'O';
}

function encodeItems($items) {
    if (is_string($items)) {
        return $items;
    }
    return json_encode($items);
}

function fetchOrderById($conn, $id) {
    $stmt = $conn->prepare(
        "SELECT id, customer_name, customer_email, items, total_amount, status,
                fulfillment, delivery_location, order_num, order_source,
                payment_method, notes, cancel_num, refund_amount, refund_status,
                cancelled_at, created_at
         FROM orders WHERE id = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if ($row) {
        $rawItems = $row['items'];
        $decoded = json_decode($rawItems, true);
        $row['items'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $rawItems;
        $row['status'] = dbStatusToUi($row['status']);
    }
    return $row;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($method === 'GET' && $action === 'get_orders') {
        $emailFilter = isset($_GET['email']) ? trim((string) $_GET['email']) : '';
        $idsFilter = isset($_GET['ids']) ? trim((string) $_GET['ids']) : '';

        if ($idsFilter !== '') {
            $rawIds = array_filter(array_map('intval', explode(',', $idsFilter)));
            $ids = array_values(array_unique(array_filter($rawIds, function ($id) {
                return $id > 0;
            })));

            if (empty($ids)) {
                $orders = array();
                ob_end_clean();
                echo json_encode(['success' => true, 'data' => $orders]);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query = "SELECT id, customer_name, customer_email, items, total_amount, status,
                             fulfillment, delivery_location, order_num, order_source,
                             payment_method, notes, cancel_num, refund_amount, refund_status,
                             cancelled_at, created_at
                      FROM orders
                      WHERE id IN ($placeholders)
                      ORDER BY created_at DESC";
            $stmt = $conn->prepare($query);
            $types = str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
        } elseif ($emailFilter !== '') {
            $query = "SELECT id, customer_name, customer_email, items, total_amount, status,
                             fulfillment, delivery_location, order_num, order_source,
                             payment_method, notes, cancel_num, refund_amount, refund_status,
                             cancelled_at, created_at
                      FROM orders
                      WHERE customer_email = ?
                      ORDER BY created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $emailFilter);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $query = "SELECT id, customer_name, customer_email, items, total_amount, status,
                             fulfillment, delivery_location, order_num, order_source,
                             payment_method, notes, cancel_num, refund_amount, refund_status,
                             cancelled_at, created_at
                      FROM orders
                      ORDER BY created_at DESC";
            $result = $conn->query($query);
        }

        if (!$result) {
            throw new Exception("Database error: " . $conn->error);
        }

        $orders = array();
        while ($row = $result->fetch_assoc()) {
            if (empty($row['order_num'])) {
                $source = normalizeOrderSource($row['order_source'] ?? 'O');
                $row['order_num'] = nextOrderNumber($conn, $source);
                $updateStmt = $conn->prepare("UPDATE orders SET order_num = ?, order_source = ? WHERE id = ?");
                $updateStmt->bind_param('ssi', $row['order_num'], $source, $row['id']);
                $updateStmt->execute();
                $updateStmt->close();
                $row['order_source'] = $source;
            }

            $decodedItems = json_decode($row['items'], true);
            $row['items'] = ($decodedItems === null) ? $row['items'] : $decodedItems;
            $row['status'] = dbStatusToUi($row['status']);
            $orders[] = $row;
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'data' => $orders
        ]);
        exit;

    } elseif ($method === 'POST' && $action === 'save_order') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['customer_name'], $data['items'])) {
            throw new Exception('Missing required fields: customer_name and items');
        }

        $customer_name = trim((string) $data['customer_name']);
        $customer_email = trim((string) ($data['customer_email'] ?? ''));
        $items = encodeItems($data['items']);
        $total_amount = floatval($data['total_amount'] ?? 0);
        $fulfillment = $data['fulfillment'] ?? 'pickup';
        $delivery_location = trim((string) ($data['delivery_location'] ?? ''));
        $order_source = normalizeOrderSource($data['order_source'] ?? 'O');
        $order_num = trim((string) ($data['order_num'] ?? ''));
        $payment_method = trim((string) ($data['payment_method'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $status = uiStatusToDb($data['status'] ?? 'new');

        if ($order_num === '') {
            $order_num = nextOrderNumber($conn, $order_source);
        }

        $query = "INSERT INTO orders
                  (customer_name, customer_email, items, total_amount, fulfillment,
                   delivery_location, status, order_num, order_source, payment_method, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            'sssdsssssss',
            $customer_name,
            $customer_email,
            $items,
            $total_amount,
            $fulfillment,
            $delivery_location,
            $status,
            $order_num,
            $order_source,
            $payment_method,
            $notes
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $orderId = $conn->insert_id;
        $stmt->close();

        triggerOrdersAnalyticsSync();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully',
            'order_id' => $orderId,
            'order_num' => $order_num
        ]);
        exit;

    } elseif ($method === 'POST' && $action === 'update_order') {
        // requireAdminLogin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            throw new Exception('Missing required field: id');
        }

        $id = intval($data['id']);
        $fields = array();
        $types = '';
        $values = array();

        $allowed = array(
            'customer_name' => 's',
            'customer_email' => 's',
            'items' => 's',
            'total_amount' => 'd',
            'fulfillment' => 's',
            'delivery_location' => 's',
            'payment_method' => 's',
            'notes' => 's',
            'status' => 's'
        );

        foreach ($allowed as $field => $type) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            if ($field === 'items') {
                $value = encodeItems($value);
            } elseif ($field === 'status') {
                $value = uiStatusToDb($value);
            } elseif ($field === 'total_amount') {
                $value = floatval($value);
            } else {
                $value = trim((string) $value);
            }

            $fields[] = "$field = ?";
            $types .= $type;
            $values[] = $value;
        }

        if (empty($fields)) {
            throw new Exception('No fields to update');
        }

        $types .= 'i';
        $values[] = $id;

        $query = "UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        $order = fetchOrderById($conn, $id);

        triggerOrdersAnalyticsSync();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order
        ]);
        exit;

    } elseif ($method === 'POST' && $action === 'delete_order') {
        // requireAdminLogin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            throw new Exception('Missing required field: id');
        }

        $id = intval($data['id']);
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        triggerOrdersAnalyticsSync();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
        exit;

    } elseif ($method === 'POST' && $action === 'update_status') {
        // requireAdminLogin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'], $data['status'])) {
            throw new Exception('Missing required fields');
        }

        $id = intval($data['id']);
        $status = uiStatusToDb($data['status']);

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

        triggerOrdersAnalyticsSync();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'status' => dbStatusToUi($status)
        ]);
        exit;

    } elseif ($method === 'POST' && $action === 'cancel_order') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            throw new Exception('Missing required field: id');
        }

        $id = intval($data['id']);
        $order = fetchOrderById($conn, $id);

        if (!$order) {
            throw new Exception('Order not found');
        }

        $currentStatus = strtolower(trim((string) ($order['status'] === 'new' ? 'pending' : $order['status'])));
        if ($currentStatus === 'cancelled' || uiStatusToDb($order['status']) === 'cancelled') {
            throw new Exception('Order is already cancelled');
        }

        $cancelNum = nextCancelOrderNumber($conn);
        $paymentMethod = $order['payment_method'] ?? '';
        $totalAmount = floatval($order['total_amount'] ?? 0);
        $refundAmount = 0.0;
        $refundStatus = 'not_applicable';

        if (isRefundableEwalletPayment($paymentMethod)) {
            $refundAmount = $totalAmount;
            $refundStatus = 'refunded';
        }

        $stmt = $conn->prepare(
            "UPDATE orders
             SET status = 'cancelled',
                 cancel_num = ?,
                 refund_amount = ?,
                 refund_status = ?,
                 cancelled_at = NOW()
             WHERE id = ?"
        );

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('sdsi', $cancelNum, $refundAmount, $refundStatus, $id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        $updated = fetchOrderById($conn, $id);

        triggerOrdersAnalyticsSync();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => $refundStatus === 'refunded'
                ? 'Order cancelled. GCash/Maya refund processed.'
                : 'Order cancelled successfully',
            'data' => $updated,
            'cancel_num' => $cancelNum,
            'refund_amount' => $refundAmount,
            'refund_status' => $refundStatus
        ]);
        exit;

    } else {
        throw new Exception('Invalid action: ' . $action);
    }

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
} finally {
    $conn->close();
}
?>
