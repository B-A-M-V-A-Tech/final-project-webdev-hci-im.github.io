<?php
$host = "localhost";
$username = "root";
$password = ""; 
$database = "sip_and_pulse_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function ensureColumn($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
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
}

ensureDatabaseSchema($conn);

/*
Manual setup (optional — schema above runs automatically on each connection):

CREATE DATABASE IF NOT EXISTS sip_and_pulse_db;
USE sip_and_pulse_db;

Default admin after first login: admin@sipandpulse.com / admin123
*/
?>