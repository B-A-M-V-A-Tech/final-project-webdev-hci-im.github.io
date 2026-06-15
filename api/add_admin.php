<?php
session_start();
header('Content-Type: application/json');
include '../database/db_connect.php';
include '../database/admin_session.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isAdminLoggedIn()) {
        http_response_code(403);
        throw new Exception('You must be signed in as an administrator to add new admins.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $role = isset($data['role']) ? trim($data['role']) : 'admin';

    if ($name === '' || $email === '' || $password === '') {
        throw new Exception('Please fill in all required fields.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }

    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters.');
    }

    $username = strtolower(preg_replace('/\s+/', '.', $name));
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare('SELECT id FROM admin_users WHERE email = ? OR username = ? LIMIT 1');
    $check->bind_param('ss', $email, $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        throw new Exception('An administrator with this email already exists.');
    }
    $check->close();

    $stmt = $conn->prepare('INSERT INTO admin_users (username, email, password, role) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('Database error. Please try again.');
    }

    $stmt->bind_param('ssss', $username, $email, $hashedPassword, $role);

    if (!$stmt->execute()) {
        throw new Exception('Could not create admin account. Please try again.');
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Administrator account created successfully.',
        'admin' => [
            'id' => $conn->insert_id,
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]
    ]);
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
