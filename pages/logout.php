<?php
session_start();

// Log Logout Activity
if (isset($_SESSION['user_id'])) {
    require '../includes/db.php';
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'Logged Out', ?)");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
}

session_destroy();
header("Location: ../index.php");
exit;
?>