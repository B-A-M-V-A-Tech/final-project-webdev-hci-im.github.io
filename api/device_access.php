<?php
header('Content-Type: application/json');
include __DIR__ . '/cors.php';
allowApiCors();
include '../database/db_connect.php';

function buildAccessUrl($base, $path) {
    $segments = explode('/', str_replace('\\', '/', $path));
    $encoded = array_map('rawurlencode', $segments);
    return rtrim($base, '/') . '/' . implode('/', $encoded);
}

try {
    $platforms = [];
    $platformResult = $conn->query(
        "SELECT platform_key, platform_name, min_width, max_width, client_path, admin_path,
                navigation_mode, touch_target_min, is_enabled, sort_order, notes
         FROM device_access_platforms
         WHERE is_enabled = 1
         ORDER BY sort_order, platform_name"
    );

    if (!$platformResult) {
        throw new Exception('Database error: ' . $conn->error);
    }

    while ($row = $platformResult->fetch_assoc()) {
        $platforms[] = $row;
    }

    $breakpoints = [];
    $breakpointResult = $conn->query(
        "SELECT breakpoint_key, label, min_width, max_width, css_class
         FROM screen_breakpoints
         ORDER BY min_width"
    );

    if (!$breakpointResult) {
        throw new Exception('Database error: ' . $conn->error);
    }

    while ($row = $breakpointResult->fetch_assoc()) {
        $breakpoints[] = $row;
    }

    $accessLinks = [];
    $linkResult = $conn->query(
        "SELECT url_key, label, url_value, view_target
         FROM site_access_urls
         ORDER BY id"
    );

    if (!$linkResult) {
        throw new Exception('Database error: ' . $conn->error);
    }

    while ($row = $linkResult->fetch_assoc()) {
        $accessLinks[] = $row;
    }

    $origin = isset($_GET['origin']) ? trim($_GET['origin']) : '';
    if ($origin === '' && isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
    }
    if ($origin === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $origin = $scheme . '://' . $host . '/final-project-webdev-hci-im.github.io';
    }

    $origin = rtrim($origin, '/');
    $urls = [
        'client' => buildAccessUrl($origin, 'Client Side/index.html'),
        'admin' => buildAccessUrl($origin, 'Admin Side/admin.html'),
    ];

    foreach ($platforms as &$platform) {
        $platform['client_url'] = buildAccessUrl($origin, $platform['client_path']);
        $platform['admin_url'] = buildAccessUrl($origin, $platform['admin_path']);
    }
    unset($platform);

    echo json_encode([
        'success' => true,
        'data' => [
            'platforms' => $platforms,
            'breakpoints' => $breakpoints,
            'urls' => $urls,
            'access_links' => $accessLinks,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
} finally {
    $conn->close();
}
?>
