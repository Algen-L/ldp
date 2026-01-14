<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head')) {
    header("Location: ../index.php");
    exit;
}

// Fetch all users
$usersStmt = $pdo->query("SELECT id, username, full_name, office_station, role, created_at FROM users ORDER BY created_at DESC");
$all_users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Log Filtering
$filter_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$filter_action = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$log_sql = "SELECT l.*, u.full_name as user_name 
            FROM activity_logs l 
            JOIN users u ON l.user_id = u.id 
            WHERE 1=1";
$log_params = [];

if ($filter_user_id > 0) {
    $log_sql .= " AND l.user_id = ?";
    $log_params[] = $filter_user_id;
}

if ($filter_action) {
    if ($filter_action === 'Viewed Specific') {
        $log_sql .= " AND l.action LIKE 'Viewed Activity Details%'";
    } elseif ($filter_action === 'Viewed') {
        $log_sql .= " AND (l.action LIKE 'Viewed%' AND l.action NOT LIKE 'Viewed Activity Details%')";
    } else {
        $log_sql .= " AND l.action LIKE ?";
        $log_params[] = "%$filter_action%";
    }
}

if ($start_date) {
    $log_sql .= " AND DATE(l.created_at) >= ?";
    $log_params[] = $start_date;
}

if ($end_date) {
    $log_sql .= " AND DATE(l.created_at) <= ?";
    $log_params[] = $end_date;
}

$log_sql .= " ORDER BY l.created_at DESC LIMIT 100";
$logStmt = $pdo->prepare($log_sql);
$logStmt->execute($log_params);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Dashboard</title>
    <?php require 'includes/admin_head.php'; ?>
</head>

<body>
    <?php
    function getLogIcon($action)
    {
        if (strpos($action, 'Logged In') !== false) {
            return '<i class="bi bi-box-arrow-in-right"></i>';
        } elseif (strpos($action, 'Logged Out') !== false) {
            return '<i class="bi bi-box-arrow-right"></i>';
        } elseif (strpos($action, 'Submitted') !== false) {
            return '<i class="bi bi-file-earmark-plus"></i>';
        } elseif (strpos($action, 'Viewed') !== false) {
            return '<i class="bi bi-eye"></i>';
        } elseif (strpos($action, 'Profile') !== false) {
            return '<i class="bi bi-person-bounding-box"></i>';
        } else {
            return '<i class="bi bi-journal-text"></i>';
        }
    }

    function getLogClass($action)
    {
        if (strpos($action, 'Logged In') !== false)
            return 'log-type-login';
        if (strpos($action, 'Logged Out') !== false)
            return 'log-type-logout';
        if (strpos($action, 'Submitted') !== false)
            return 'log-type-submission';
        if (strpos($action, 'Viewed') !== false)
            return 'log-type-view';
        return '';
    }
    ?>

    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Activity Logs</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <i class="bi bi-clock"></i>
                        <span><?php echo date('F d, Y • h:i A'); ?></span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <!-- Filter Section -->
                <div class="filter-bar">
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label>System User</label>
                            <select name="user_id" class="filter-select">
                                <option value="0">All System Users</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo $filter_user_id == $u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Log Type</label>
                            <select name="action_type" class="filter-select">
                                <option value="">Every Action</option>
                                <option value="Logged In" <?php echo $filter_action == 'Logged In' ? 'selected' : ''; ?>>
                                    Success Logins</option>
                                <option value="Logged Out" <?php echo $filter_action == 'Logged Out' ? 'selected' : ''; ?>>Success Logouts</option>
                                <option value="Submitted" <?php echo $filter_action == 'Submitted' ? 'selected' : ''; ?>>
                                    Submissions</option>
                                <option value="Viewed Specific" <?php echo $filter_action == 'Viewed Specific' ? 'selected' : ''; ?>>Detailed Views</option>
                                <option value="Viewed" <?php echo $filter_action == 'Viewed' ? 'selected' : ''; ?>>List
                                    Views</option>
                                <option value="Profile" <?php echo $filter_action == 'Profile' ? 'selected' : ''; ?>>
                                    Profile Changes</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Date Threshold (From-To)</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                                    class="filter-input">
                                <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                                    class="filter-input">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i> Apply Filter
                            </button>
                            <?php if ($filter_user_id > 0 || $filter_action || $start_date || $end_date): ?>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Logs List Section -->
                <div class="dashboard-card hover-elevate">
                    <div class="card-header">
                        <h2><i class="bi bi-clock-history text-gradient"></i> Detailed System Events</h2>
                        <span class="result-count">Found <?php echo count($logs); ?> recent events</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="activity-list">
                            <?php if (empty($logs)): ?>
                                <div class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="bi bi-shield-slash text-muted"
                                            style="font-size: 3.5rem; opacity: 0.3;"></i>
                                        <p class="mt-3 text-muted">No system events matched your current filters.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <div class="activity-item <?php echo getLogClass($log['action']); ?>">
                                        <div class="activity-icon">
                                            <?php echo getLogIcon($log['action']); ?>
                                        </div>
                                        <div class="activity-content">
                                            <div
                                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                                <div>
                                                    <span
                                                        class="activity-user"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                                    <span class="mx-2 text-muted">•</span>
                                                    <span
                                                        class="activity-time"><?php echo date('M d, Y • h:i A', strtotime($log['created_at'])); ?></span>
                                                </div>
                                                <div class="activity-time"
                                                    style="font-size: 0.7rem; background: var(--bg-primary); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                                                    <i class="bi bi-pc-display me-1"></i>
                                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                                </div>
                                            </div>
                                            <div class="activity-desc">
                                                <strong
                                                    style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($log['action']); ?></strong>
                                                <?php if ($log['details']): ?>
                                                    <div class="activity-details-box">
                                                        <?php echo htmlspecialchars($log['details']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Advanced System
                        Monitoring & Audit.</span></p>
            </footer>
        </div>
    </div>
</body>

</html>