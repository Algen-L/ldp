<?php
require 'includes/db.php';

try {
    // Add created_by column if it doesn't exist
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS created_by INT NULL");
    echo "Column check/add complete.\n";

    // Set a default for existing users if needed (e.g., the first super_admin)
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'super_admin' LIMIT 1");
    $super_admin_id = $stmt->fetchColumn();

    if ($super_admin_id) {
        $pdo->prepare("UPDATE users SET created_by = ? WHERE created_by IS NULL")->execute([$super_admin_id]);
        echo "Populated existing users with default creator ID: $super_admin_id\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>