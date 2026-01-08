<?php
require 'db.php';

$admins = [
    [
        'username' => 'admin1',
        'password' => 'admin123',
        'full_name' => 'Administrator One',
        'role' => 'admin'
    ],
    [
        'username' => 'admin2',
        'password' => 'admin223',
        'full_name' => 'Administrator Two',
        'role' => 'admin'
    ]
];

foreach ($admins as $admin) {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$admin['username']]);

    if (!$stmt->fetch()) {
        $hashed_password = password_hash($admin['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$admin['username'], $hashed_password, $admin['full_name'], $admin['role']])) {
            echo "Created admin: " . $admin['username'] . "\n";
        } else {
            echo "Failed to create admin: " . $admin['username'] . "\n";
        }
    } else {
        echo "Admin already exists: " . $admin['username'] . "\n";
    }
}
?>