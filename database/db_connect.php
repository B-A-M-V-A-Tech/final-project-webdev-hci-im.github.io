<?php
$host = "localhost";
$username = "root";
$password = ""; 
$database = "sip_and_pulse_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function dbNewConnection() {
    global $host, $username, $password, $database;
    $next = new mysqli($host, $username, $password, $database);
    if ($next->connect_error) {
        throw new Exception('Connection failed: ' . $next->connect_error);
    }
    return $next;
}

function ensureColumn($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function ensureMenuItemsAutoIncrement($conn) {
    $tableResult = $conn->query("SHOW TABLES LIKE 'menu_items'");
    if (!$tableResult || $tableResult->num_rows === 0) {
        return;
    }

    $colResult = $conn->query("SHOW COLUMNS FROM menu_items WHERE Field = 'id'");
    if (!$colResult || !($col = $colResult->fetch_assoc())) {
        return;
    }

    $extra = strtolower((string) ($col['Extra'] ?? ''));
    if (strpos($extra, 'auto_increment') !== false) {
        return;
    }

    $zeroResult = $conn->query('SELECT id FROM menu_items WHERE id = 0 LIMIT 1');
    if ($zeroResult && $zeroResult->num_rows > 0) {
        $maxResult = $conn->query('SELECT COALESCE(MAX(id), 0) AS mx FROM menu_items');
        $maxRow = $maxResult ? $maxResult->fetch_assoc() : array('mx' => 0);
        $newId = intval($maxRow['mx']) + 1;
        $conn->query('UPDATE menu_items SET id = ' . $newId . ' WHERE id = 0 LIMIT 1');
    }

    $conn->query('ALTER TABLE menu_items MODIFY id INT NOT NULL AUTO_INCREMENT');

    $maxResult = $conn->query('SELECT COALESCE(MAX(id), 0) AS mx FROM menu_items');
    $maxRow = $maxResult ? $maxResult->fetch_assoc() : array('mx' => 0);
    $next = intval($maxRow['mx']) + 1;
    $conn->query('ALTER TABLE menu_items AUTO_INCREMENT = ' . $next);
}

function ensureAnalyticsSheetConfig($conn) {
    $canonical = array(
        'id' => '1FKCvoXqNIJd7uYAOfTC39lepsegswNsYujECatuSbj8',
        'url' => 'https://docs.google.com/spreadsheets/d/1FKCvoXqNIJd7uYAOfTC39lepsegswNsYujECatuSbj8/edit?usp=sharing',
        'name' => 'Sales Records',
    );

    $conn->query(
        "UPDATE analytics_config SET
            google_spreadsheet_id = '" . $conn->real_escape_string($canonical['id']) . "',
            google_sheet_url = '" . $conn->real_escape_string($canonical['url']) . "',
            google_sheet_name = '" . $conn->real_escape_string($canonical['name']) . "'
         WHERE id >= 1"
    );
}

function ensureAnalyticsSeed($conn) {
    $countResult = $conn->query('SELECT COUNT(*) AS c FROM analytics_config');
    $countRow = $countResult ? $countResult->fetch_assoc() : array('c' => 0);
    if (intval($countRow['c']) > 0) {
        return;
    }

    $powerbiUrl = 'https://app.powerbi.com/view?r=eyJrIjoiZjE2OGYxODYtNzZhNC00OGYxLWI2NjQtMWQ4ZjllOTFlYWM1IiwidCI6IjRkYTk4NTcxLWRjZWEtNDgzOS04ZmIxLTBiZGQ1ZGM5NjlmOSIsImMiOjEwfQ%3D%3D';
    $sheetId = '1FKCvoXqNIJd7uYAOfTC39lepsegswNsYujECatuSbj8';
    $sheetUrl = 'https://docs.google.com/spreadsheets/d/1FKCvoXqNIJd7uYAOfTC39lepsegswNsYujECatuSbj8/edit?usp=sharing';
    $sheetName = 'Sales Records';
    $serviceEmail = '';
    $privateKey = '';

    $stmt = $conn->prepare(
        "INSERT INTO analytics_config
        (powerbi_title, powerbi_embed_url, google_spreadsheet_id, google_sheet_name, google_sheet_url,
         service_account_email, private_key_pem, last_sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    if ($stmt) {
        $title = 'SipAndPulseSalesPerformance';
        $stmt->bind_param('sssssss', $title, $powerbiUrl, $sheetId, $sheetName, $sheetUrl, $serviceEmail, $privateKey);
        $stmt->execute();
        $stmt->close();
    }
}

function ensureDeviceAccessSeed($conn) {
    $platforms = [
        ['mobile', 'Mobile (Android & phones)', 0, 767, 'Client Side/index.html', 'Admin Side/admin.html', 'touch', 48, 1, 1, 'Phones and small screens — touch navigation, pinch-zoom friendly.'],
        ['ios', 'iOS (iPhone & iPad)', 0, 1024, 'Client Side/index.html', 'Admin Side/admin.html', 'touch', 44, 1, 2, 'Safari, Add to Home Screen, safe-area insets.'],
        ['chrome_extension', 'Chrome Extension / PWA', 0, 99999, 'Client Side/index.html', 'Admin Side/admin.html', 'pointer', 44, 1, 3, 'Browser install or standalone window.'],
        ['desktop', 'Desktop (Windows, macOS, Linux)', 1024, 1919, 'Client Side/index.html', 'Admin Side/admin.html', 'pointer-keyboard', 40, 1, 4, 'Mouse, trackpad, and keyboard navigation.'],
        ['tv', 'TV & large displays', 1920, 99999, 'Client Side/index.html', 'Admin Side/admin.html', 'remote-dpad', 56, 1, 5, 'Remote / D-pad focus, 10-foot UI spacing.'],
        ['limited_input', 'Limited input devices', 0, 99999, 'Client Side/index.html', 'Admin Side/admin.html', 'keyboard-only', 48, 1, 6, 'Keyboard, switch access, and screen readers.'],
    ];

    $stmt = $conn->prepare(
        "INSERT IGNORE INTO device_access_platforms
        (platform_key, platform_name, min_width, max_width, client_path, admin_path, navigation_mode, touch_target_min, is_enabled, sort_order, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        foreach ($platforms as $p) {
            $stmt->bind_param('ssiisssiiis', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10]);
            $stmt->execute();
        }
        $stmt->close();
    }

    $breakpoints = [
        ['xs', 'Mobile portrait', 0, 479, 'bp-xs'],
        ['sm', 'Mobile landscape', 480, 767, 'bp-sm'],
        ['md', 'Tablet', 768, 1023, 'bp-md'],
        ['lg', 'Desktop', 1024, 1439, 'bp-lg'],
        ['xl', 'Large desktop', 1440, 1919, 'bp-xl'],
        ['tv', 'TV / ultra-wide', 1920, 99999, 'bp-tv'],
    ];

    $bpStmt = $conn->prepare(
        "INSERT IGNORE INTO screen_breakpoints
        (breakpoint_key, label, min_width, max_width, css_class)
        VALUES (?, ?, ?, ?, ?)"
    );
    if ($bpStmt) {
        foreach ($breakpoints as $b) {
            $bpStmt->bind_param('ssiis', $b[0], $b[1], $b[2], $b[3], $b[4]);
            $bpStmt->execute();
        }
        $bpStmt->close();
    }

    $accessUrls = [
        ['local_client', 'Client — Local (XAMPP)', 'http://localhost/final-project-webdev-hci-im.github.io/Client%20Side/index.html', 'client'],
        ['local_admin', 'Admin — Local (XAMPP)', 'http://localhost/final-project-webdev-hci-im.github.io/Admin%20Side/admin.html', 'admin'],
        ['live_client', 'Client — Live (GitHub Pages)', 'https://final-project-webdev-hci-im.github.io/Client%20Side/index.html', 'client'],
        ['live_admin', 'Admin — Live (GitHub Pages)', 'https://final-project-webdev-hci-im.github.io/Admin%20Side/admin.html', 'admin'],
    ];

    $urlStmt = $conn->prepare(
        "INSERT IGNORE INTO site_access_urls (url_key, label, url_value, view_target) VALUES (?, ?, ?, ?)"
    );
    if ($urlStmt) {
        foreach ($accessUrls as $u) {
            $urlStmt->bind_param('ssss', $u[0], $u[1], $u[2], $u[3]);
            $urlStmt->execute();
        }
        $urlStmt->close();
    }
}

function ensureDatabaseSchema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_email (email)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_admin_username (username)
    )");

    ensureColumn($conn, 'admin_users', 'email', "VARCHAR(255) DEFAULT NULL");
    ensureColumn($conn, 'admin_users', 'role', "VARCHAR(50) DEFAULT 'admin'");

    $conn->query("UPDATE admin_users SET email = 'admin@sipandpulse.com' WHERE username = 'admin' AND (email IS NULL OR email = '')");
    $conn->query("UPDATE admin_users SET email = LOWER(TRIM(username)) WHERE (email IS NULL OR email = '') AND username LIKE '%@%'");
    $conn->query("UPDATE admin_users SET email = LOWER(TRIM(email)) WHERE email IS NOT NULL AND email != ''");

    $conn->query("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(500) DEFAULT '',
        available TINYINT(1) DEFAULT 1,
        item_type VARCHAR(20) DEFAULT 'food',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    ensureColumn($conn, 'menu_items', 'available', "TINYINT(1) DEFAULT 1");
    ensureColumn($conn, 'menu_items', 'item_type', "VARCHAR(20) DEFAULT 'food'");
    ensureMenuItemsAutoIncrement($conn);

    $imageUrlCol = $conn->query("SHOW COLUMNS FROM menu_items WHERE Field = 'image_url'");
    if ($imageUrlCol && ($imageRow = $imageUrlCol->fetch_assoc())) {
        $type = strtolower((string) ($imageRow['Type'] ?? ''));
        if (strpos($type, 'varchar(255)') !== false) {
            $conn->query('ALTER TABLE menu_items MODIFY image_url VARCHAR(700) DEFAULT ""');
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT NOT NULL,
        avatar VARCHAR(500) DEFAULT '',
        admin_reply TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    ensureColumn($conn, 'reviews', 'avatar', "VARCHAR(500) DEFAULT ''");
    ensureColumn($conn, 'reviews', 'admin_reply', "TEXT");

    $conn->query("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) DEFAULT '',
        items TEXT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        fulfillment VARCHAR(50) DEFAULT 'pickup',
        delivery_location VARCHAR(255) DEFAULT '',
        order_num VARCHAR(20) DEFAULT '',
        order_source VARCHAR(5) DEFAULT 'O',
        payment_method VARCHAR(80) DEFAULT '',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    ensureColumn($conn, 'orders', 'order_num', "VARCHAR(20) DEFAULT ''");
    ensureColumn($conn, 'orders', 'order_source', "VARCHAR(5) DEFAULT 'O'");
    ensureColumn($conn, 'orders', 'payment_method', "VARCHAR(80) DEFAULT ''");
    ensureColumn($conn, 'orders', 'notes', "TEXT");
    ensureColumn($conn, 'orders', 'cancel_num', "VARCHAR(20) DEFAULT ''");
    ensureColumn($conn, 'orders', 'refund_amount', "DECIMAL(10,2) DEFAULT 0");
    ensureColumn($conn, 'orders', 'refund_status', "VARCHAR(50) DEFAULT ''");
    ensureColumn($conn, 'orders', 'cancelled_at', "TIMESTAMP NULL DEFAULT NULL");

    $conn->query("CREATE TABLE IF NOT EXISTS device_access_platforms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        platform_key VARCHAR(40) NOT NULL,
        platform_name VARCHAR(120) NOT NULL,
        min_width INT DEFAULT 0,
        max_width INT DEFAULT 99999,
        client_path VARCHAR(255) NOT NULL,
        admin_path VARCHAR(255) NOT NULL,
        navigation_mode VARCHAR(40) DEFAULT 'standard',
        touch_target_min INT DEFAULT 44,
        is_enabled TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        notes TEXT,
        UNIQUE KEY uniq_platform_key (platform_key)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS screen_breakpoints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        breakpoint_key VARCHAR(20) NOT NULL,
        label VARCHAR(80) NOT NULL,
        min_width INT NOT NULL,
        max_width INT NOT NULL,
        css_class VARCHAR(40) DEFAULT '',
        UNIQUE KEY uniq_breakpoint_key (breakpoint_key)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS site_access_urls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        url_key VARCHAR(40) NOT NULL,
        label VARCHAR(120) NOT NULL,
        url_value VARCHAR(500) NOT NULL,
        view_target VARCHAR(20) DEFAULT 'both',
        UNIQUE KEY uniq_url_key (url_key)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS analytics_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        powerbi_title VARCHAR(120) NOT NULL,
        powerbi_embed_url VARCHAR(700) NOT NULL,
        google_spreadsheet_id VARCHAR(120) NOT NULL,
        google_sheet_name VARCHAR(80) DEFAULT 'Sales Records',
        google_sheet_url VARCHAR(500) NOT NULL,
        service_account_email VARCHAR(255) DEFAULT '',
        private_key_pem TEXT,
        last_sync_at TIMESTAMP NULL DEFAULT NULL,
        last_sync_status VARCHAR(40) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS sales_analytics_rows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        order_num VARCHAR(20) DEFAULT '',
        order_date DATETIME DEFAULT NULL,
        customer_name VARCHAR(255) DEFAULT '',
        customer_email VARCHAR(255) DEFAULT '',
        total_amount DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT '',
        payment_method VARCHAR(80) DEFAULT '',
        fulfillment VARCHAR(50) DEFAULT '',
        order_source VARCHAR(5) DEFAULT 'O',
        item_count INT DEFAULT 0,
        refund_amount DECIMAL(10,2) DEFAULT 0,
        refund_status VARCHAR(50) DEFAULT '',
        is_anomaly TINYINT(1) DEFAULT 0,
        anomaly_reason VARCHAR(255) DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_analytics_order (order_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS analytics_sync_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sync_status VARCHAR(40) DEFAULT '',
        message TEXT,
        row_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    ensureColumn($conn, 'analytics_config', 'google_apps_script_url', "VARCHAR(700) DEFAULT ''");
    ensureColumn($conn, 'analytics_config', 'powerbi_tenant_id', "VARCHAR(80) DEFAULT ''");
    ensureColumn($conn, 'analytics_config', 'powerbi_report_id', "VARCHAR(80) DEFAULT ''");
    ensureColumn($conn, 'analytics_config', 'powerbi_group_id', "VARCHAR(80) DEFAULT ''");
    ensureColumn($conn, 'analytics_config', 'powerbi_dataset_id', "VARCHAR(80) DEFAULT ''");
    ensureColumn($conn, 'analytics_config', 'powerbi_client_id', "VARCHAR(80) DEFAULT ''");
    ensureColumn($conn, 'analytics_config', 'powerbi_client_secret', "VARCHAR(255) DEFAULT ''");
    ensureColumn($conn, 'analytics_config', 'powerbi_last_refresh_at', "TIMESTAMP NULL DEFAULT NULL");
    ensureColumn($conn, 'analytics_config', 'powerbi_last_refresh_status', "VARCHAR(40) DEFAULT 'pending'");

    ensureAnalyticsSeed($conn);
    ensureAnalyticsSheetConfig($conn);
    ensureDeviceAccessSeed($conn);

    require_once __DIR__ . '/demo_seed.php';
    ensureDemoSeed($conn);
}

ensureDatabaseSchema($conn);

/*
Manual setup (optional — schema above runs automatically on each connection):

CREATE DATABASE IF NOT EXISTS sip_and_pulse_db;
USE sip_and_pulse_db;

Default admin after first login: admin@sipandpulse.com / admin123
*/
?>