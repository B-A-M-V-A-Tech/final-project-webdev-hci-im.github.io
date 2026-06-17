<?php
include __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json');
include '../database/db_connect.php';
include '../database/admin_session.php';

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'You must be signed in as an administrator to upload images.'
    ]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        throw new Exception('No image file received.');
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new Exception('Image must be 5 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception('Only JPG, PNG, WebP, or GIF images are allowed.');
    }

    $itemId = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $uploadDir = dirname(__DIR__) . '/uploads/menu';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Could not create upload folder.');
    }

    $baseName = 'menu_' . ($itemId > 0 ? $itemId : 'new') . '_' . time();
    $fileName = $baseName . '.' . $allowed[$mime];
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Could not save uploaded image.');
    }

    $relativePath = 'uploads/menu/' . $fileName;

    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully.',
        'image_url' => $relativePath,
        'path' => $relativePath,
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
