<?php
session_start();

// Log Logout Activity
if (isset($_SESSION['user_id'])) {
    require '../includes/init_repos.php';
    $logRepo->logAction($_SESSION['user_id'], 'Logged Out');
}

session_destroy();
header("Location: ../index.php");
exit;
?>