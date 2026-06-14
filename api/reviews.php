<?php
header('Content-Type: application/json');
include '../db_connect.php';
include '../admin_session.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($method === 'GET' && $action === 'get_reviews') {
        // Get all reviews (public)
        $query = "SELECT id, name, rating, comment, avatar, admin_reply, created_at FROM reviews ORDER BY created_at DESC";
        $result = $conn->query($query);

        if (!$result) {
            throw new Exception("Database error: " . $conn->error);
        }

        $reviews = array();
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $reviews
        ]);

    } elseif ($method === 'POST' && $action === 'save_review') {
        // Save new review (client side)
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['name'], $data['rating'], $data['comment'])) {
            throw new Exception('Missing required fields');
        }

        $name = $data['name'];
        $rating = intval($data['rating']);
        $comment = $data['comment'];
        $avatar = $data['avatar'] ?? '';

        if ($rating < 1 || $rating > 5) {
            throw new Exception('Invalid rating');
        }

        $query = "INSERT INTO reviews (name, rating, comment, avatar) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('siss', $name, $rating, $comment, $avatar);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $reviewId = $conn->insert_id;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review_id' => $reviewId
        ]);

    } elseif ($method === 'POST' && $action === 'admin_reply') {
        // Add admin reply to review (admin only)
        requireAdminLogin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['review_id'], $data['reply'])) {
            throw new Exception('Missing required fields');
        }

        $review_id = intval($data['review_id']);
        $reply = $data['reply'];

        $query = "UPDATE reviews SET admin_reply = ? WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('si', $reply, $review_id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Reply added successfully'
        ]);

    } elseif ($method === 'POST' && $action === 'delete_review') {
        // Delete review (admin only)
        requireAdminLogin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'])) {
            throw new Exception('Review ID required');
        }

        $id = intval($data['id']);

        $query = "DELETE FROM reviews WHERE id = ?";
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
            'message' => 'Review deleted successfully'
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