<?php
chdir(dirname(__DIR__));
require __DIR__ . '/db_connect.php';

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

$conn->query('DELETE FROM reviews');

$stmt = $conn->prepare(
    'INSERT INTO reviews (name, rating, comment, avatar, admin_reply, created_at) VALUES (?, ?, ?, ?, ?, ?)'
);

$daysAgo = count($reviews) * 3;
foreach ($reviews as $i => $review) {
    $createdAt = date('Y-m-d H:i:s', strtotime('-' . ($daysAgo - ($i * 3)) . ' days'));
    $avatar = '';
    $stmt->bind_param('sissss', $review['name'], $review['rating'], $review['comment'], $avatar, $review['admin_reply'], $createdAt);
    $stmt->execute();
}
$stmt->close();

$count = $conn->query('SELECT COUNT(*) AS c FROM reviews')->fetch_assoc()['c'];
echo "Restored $count reviews.\n";
$conn->close();
