<?php
$sessionBootstrap = dirname(__DIR__) . '/api/session_bootstrap.php';
if (is_file($sessionBootstrap)) {
    require_once $sessionBootstrap;
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function getAdminUsername() {
    return isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : '';
}

function getAdminId() {
    return isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
}

function logoutAdmin() {
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit();
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Admin authentication required']);
            exit();
        }
        header('Location: admin_login.php');
        exit();
    }
}
