<?php
/**
 * Repository Initializer
 * Includes all repository classes and instantiates them using the global $pdo connection.
 */
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/repositories/ActivityRepository.php';
require_once __DIR__ . '/repositories/ILDNRepository.php';
require_once __DIR__ . '/repositories/ActivityLogRepository.php';

// Include global utility functions
require_once __DIR__ . '/functions/user-functions.php';
require_once __DIR__ . '/functions/activity-functions.php';

if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

$userRepo = new UserRepository($pdo);
$activityRepo = new ActivityRepository($pdo);
$ildnRepo = new ILDNRepository($pdo);
$logRepo = new ActivityLogRepository($pdo);
