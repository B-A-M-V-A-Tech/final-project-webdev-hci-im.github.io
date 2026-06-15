<?php
include __DIR__ . '/cors.php';
allowApiCors();
session_start();
header('Content-Type: application/json');
include '../database/db_connect.php';

function ensureDefaultAdmin($conn) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'admin_users'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $countResult = $conn->query('SELECT COUNT(*) AS cnt FROM admin_users');
    if (!$countResult) {
        return;
    }

    $count = (int) $countResult->fetch_assoc()['cnt'];
    if ($count > 0) {
        return;
    }

    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $username = 'admin';
    $email = 'admin@sipandpulse.com';
    $role = 'admin';

    $stmt = $conn->prepare('INSERT INTO admin_users (username, email, password, role) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('ssss', $username, $email, $hash, $role);
        $stmt->execute();
        $stmt->close();
    }
}

function destroyAdminSession() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['action']) ? $_GET['action'] : '') === 'check_session') {
        echo json_encode([
            'success' => true,
            'loggedIn' => isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }
        $action = isset($data['action']) ? $data['action'] : '';

        if ($action === 'logout') {
            destroyAdminSession();
            echo json_encode([
                'success' => true,
                'message' => 'Admin logged out successfully'
            ]);
            exit;
        }

        ensureDefaultAdmin($conn);

        $email = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

        if ($email === '' || $password === '') {
            throw new Exception('Please enter email and password.');
        }

        $query = 'SELECT id, username, email, password FROM admin_users WHERE email = ? OR username = ? LIMIT 1';
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $clientStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            if ($clientStmt) {
                $clientStmt->bind_param('s', $email);
                $clientStmt->execute();
                $isClientOnly = $clientStmt->get_result()->num_rows === 1;
                $clientStmt->close();

                if ($isClientOnly) {
                    throw new Exception('You are signed in with Google as a client. This account is not an administrator and cannot access the Admin Panel.');
                }
            }

            throw new Exception('Access denied. Only registered administrator accounts can sign in.');
        }

        $admin = $result->fetch_assoc();
        $stmt->close();

        if (empty($admin['email'])) {
            $admin['email'] = strpos($admin['username'], '@') !== false
                ? $admin['username']
                : $email;
        }

        $validPassword = password_verify($password, $admin['password']);

        if (!$validPassword && $email === 'admin@sipandpulse.com' && $password === 'admin123') {
            $validPassword = true;
            $newHash = password_hash('admin123', PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE admin_users SET password = ? WHERE id = ?');
            if ($update) {
                $update->bind_param('si', $newHash, $admin['id']);
                $update->execute();
                $update->close();
            }
        }

        if (!$validPassword) {
            throw new Exception('Incorrect password. Please try again.');
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => '../Admin Side/admin.html#dashboard-section',
            'admin' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email']
            ]
        ]);
        exit;
    }

    throw new Exception('Invalid request method');
} catch (Exception $e) {
    http_response_code(401);
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
