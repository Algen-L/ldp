<?php
require 'db.php';

// Get the first user (likely created during testing) or first admin
$stmt = $pdo->query("SELECT id FROM users LIMIT 1");
$user = $stmt->fetch();

if ($user) {
    $user_id = $user['id'];
    $events = [
        ['Python Workshop', '2023-10-15', 'Attended'],
        ['Agile Leadership', '2023-11-05', 'Attended'],
        ['Cybersecurity Basics', '2023-12-12', 'Completed'],
        ['Data Science Intro', '2024-01-20', 'Registered']
    ];

    foreach ($events as $event) {
        $sql = "INSERT INTO events (user_id, event_name, event_date, status) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $event[0], $event[1], $event[2]]);
    }
    echo "Events seeded for user ID: $user_id";
} else {
    echo "No users found. Please register a user first.";
}
?>