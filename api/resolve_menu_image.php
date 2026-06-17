<?php
include __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json');
include '../database/db_connect.php';
include '../database/admin_session.php';
include '../database/menu_image_resolver.php';

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'You must be signed in as an administrator.'
    ]);
    exit;
}

try {
    $url = isset($_GET['url']) ? trim((string) $_GET['url']) : '';
    if ($url === '') {
        throw new Exception('Image URL is required.');
    }

    $resolved = resolveMenuImageUrl($url);

    echo json_encode(array(
        'success' => true,
        'image_url' => $resolved,
        'resolved' => $resolved !== $url,
    ));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
    ));
}
