<?php

function demoSeedJsArrayToPhp($jsArrayLiteral) {
    $json = preg_replace('/([,{]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', $jsArrayLiteral);
    $json = preg_replace('/,\s*([\]}])/', '$1', $json);
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function demoSeedLoadFromAdminHtml() {
    $path = dirname(__DIR__) . '/Admin Side/admin.html';
    if (!is_file($path)) {
        return ['menu' => [], 'reviews' => []];
    }

    $html = file_get_contents($path);
    $menu = [];
    $reviews = [];

    if (preg_match('/const DEMO_MENU\s*=\s*(\[[\s\S]*?\]);/m', $html, $m)) {
        $menu = demoSeedJsArrayToPhp($m[1]);
    }
    if (preg_match('/const DEMO_REVIEWS\s*=\s*(\[[\s\S]*?\]);/m', $html, $r)) {
        $reviews = demoSeedJsArrayToPhp($r[1]);
    }

    return ['menu' => $menu, 'reviews' => $reviews];
}

function ensureDefaultAdminSeed($conn) {
    $countResult = $conn->query('SELECT COUNT(*) AS c FROM admin_users');
    $countRow = $countResult ? $countResult->fetch_assoc() : ['c' => 0];
    if (intval($countRow['c']) > 0) {
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

function ensureMenuSeed($conn) {
    $countResult = $conn->query('SELECT COUNT(*) AS c FROM menu_items');
    $countRow = $countResult ? $countResult->fetch_assoc() : ['c' => 0];
    if (intval($countRow['c']) > 0) {
        return;
    }

    $data = demoSeedLoadFromAdminHtml();
    $menu = $data['menu'];
    if (!$menu) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO menu_items (id, name, category, description, price, image_url, available, item_type)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }

    foreach ($menu as $item) {
        $id = intval($item['id'] ?? 0);
        $name = (string) ($item['name'] ?? '');
        $category = (string) ($item['cat'] ?? $item['category'] ?? 'espresso');
        $description = (string) ($item['desc'] ?? $item['description'] ?? '');
        $price = floatval($item['price'] ?? 0);
        $imageUrl = (string) ($item['img'] ?? $item['image_url'] ?? '');
        $available = !empty($item['available']) ? 1 : 0;
        $itemType = (string) ($item['type'] ?? $item['item_type'] ?? 'food');
        $stmt->bind_param('isssdsis', $id, $name, $category, $description, $price, $imageUrl, $available, $itemType);
        $stmt->execute();
    }
    $stmt->close();

    ensureMenuItemsAutoIncrement($conn);
}

function ensureReviewsSeed($conn) {
    $countResult = $conn->query('SELECT COUNT(*) AS c FROM reviews');
    $countRow = $countResult ? $countResult->fetch_assoc() : ['c' => 0];
    if (intval($countRow['c']) > 0) {
        return;
    }

    $reviews = [
        ['name' => 'Test Guest', 'rating' => 5, 'comment' => 'Great coffee!', 'admin_reply' => 'Salamat sa review! Testing Sip and Pulse!'],
        ['name' => 'Client Test', 'rating' => 5, 'comment' => 'Great service!', 'admin_reply' => ''],
        ['name' => 'Anonymous', 'rating' => 5, 'comment' => 'Fastest Order', 'admin_reply' => ''],
        ['name' => 'Continue Test', 'rating' => 5, 'comment' => 'Working now!', 'admin_reply' => ''],
        ['name' => 'I MISS YOU', 'rating' => 5, 'comment' => 'I miss you 2 review!', 'admin_reply' => 'Thank you.'],
        ['name' => 'Maria Santos', 'rating' => 5, 'comment' => "The Caramel Macchiato is hands-down the best I've had in Batangas. Cozy ambiance and friendly staff!", 'admin_reply' => "Thank you so much, Maria! We're so happy to hear that. We'll pass your kind words to our baristas!"],
        ['name' => 'Jake Reyes', 'rating' => 4, 'comment' => 'Solid burgers and great cold brew. Love the vibe here. Definitely coming back!', 'admin_reply' => ''],
        ['name' => 'Lia Cruz', 'rating' => 5, 'comment' => 'The Belgian Waffle is insanely good. Perfect study spot with fast Wi-Fi and great music!', 'admin_reply' => 'Lia, that means the world to us! Come back soon — we just added new waffle toppings!'],
    ];

    $stmt = $conn->prepare(
        'INSERT INTO reviews (name, rating, comment, avatar, admin_reply, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }

    $daysAgo = count($reviews) * 3;
    foreach ($reviews as $i => $review) {
        $createdAt = date('Y-m-d H:i:s', strtotime('-' . ($daysAgo - ($i * 3)) . ' days'));
        $avatar = '';
        $stmt->bind_param('sissss', $review['name'], $review['rating'], $review['comment'], $avatar, $review['admin_reply'], $createdAt);
        $stmt->execute();
    }
    $stmt->close();
}

function ensureOrdersSeed($conn) {
    $countResult = $conn->query('SELECT COUNT(*) AS c FROM orders');
    $countRow = $countResult ? $countResult->fetch_assoc() : ['c' => 0];
    if (intval($countRow['c']) > 0) {
        return;
    }

    $samples = [
        [
            'customer_name' => 'Maria Santos',
            'customer_email' => 'maria.santos@email.com',
            'items' => 'Caramel Macchiato x2',
            'total_amount' => 300.00,
            'status' => 'done',
            'fulfillment' => 'pickup',
            'order_num' => 'O1001',
            'order_source' => 'O',
            'payment_method' => 'GCash',
            'notes' => 'Less ice please',
        ],
        [
            'customer_name' => 'Jake Reyes',
            'customer_email' => 'jake.reyes@email.com',
            'items' => 'Classic Burger, Cold Brew',
            'total_amount' => 320.00,
            'status' => 'ready',
            'fulfillment' => 'pickup',
            'order_num' => 'O1002',
            'order_source' => 'O',
            'payment_method' => 'Cash',
            'notes' => '',
        ],
        [
            'customer_name' => 'Lia Cruz',
            'customer_email' => 'lia.cruz@email.com',
            'items' => 'Belgian Waffle',
            'total_amount' => 160.00,
            'status' => 'preparing',
            'fulfillment' => 'delivery',
            'delivery_location' => 'Poblacion, Batangas City',
            'order_num' => 'O1003',
            'order_source' => 'O',
            'payment_method' => 'Maya',
            'notes' => 'Ring doorbell',
        ],
    ];

    $stmt = $conn->prepare(
        'INSERT INTO orders
        (customer_name, customer_email, items, total_amount, status, fulfillment, delivery_location, order_num, order_source, payment_method, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }

    foreach ($samples as $order) {
        $delivery = (string) ($order['delivery_location'] ?? '');
        $stmt->bind_param(
            'sssdsssssss',
            $order['customer_name'],
            $order['customer_email'],
            $order['items'],
            $order['total_amount'],
            $order['status'],
            $order['fulfillment'],
            $delivery,
            $order['order_num'],
            $order['order_source'],
            $order['payment_method'],
            $order['notes']
        );
        $stmt->execute();
    }
    $stmt->close();
}

function ensureDemoSeed($conn) {
    ensureDefaultAdminSeed($conn);
    ensureMenuSeed($conn);
    ensureReviewsSeed($conn);
    ensureOrdersSeed($conn);
}
