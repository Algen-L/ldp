<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
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
    $log_sql .= " AND l.action LIKE ?";
    $log_params[] = "%$filter_action%";
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
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/passbook.css">
    <link rel="stylesheet" href="../css/tables.css">
    <style>
        .users-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        @media (max-width: 1000px) {
            .users-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Enhanced Log Styles */
        .logs-container {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #eef2f7;
            border-radius: 12px;
            background: #fff;
            padding: 5px;
        }

        .log-entry {
            padding: 15px;
            border-bottom: 1px solid #f1f4f8;
            transition: all 0.2s ease;
            display: flex;
            gap: 15px;
            position: relative;
        }

        .log-entry:hover {
            background-color: #f8fbff;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .log-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .log-content {
            flex-grow: 1;
        }

        .log-time {
            color: #94a3b8;
            font-size: 0.75rem;
            display: block;
            margin-bottom: 2px;
        }

        .log-user {
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .log-action {
            font-weight: 600;
            font-size: 0.85rem;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 5px;
        }

        /* Action Specific Colors */
        .log-type-login .log-icon-box {
            background: #ecfdf5;
            color: #10b981;
        }

        .log-type-login .log-action {
            background: #ecfdf5;
            color: #10b981;
        }

        .log-type-submission .log-icon-box {
            background: #eff6ff;
            color: #3b82f6;
        }

        .log-type-submission .log-action {
            background: #eff6ff;
            color: #3b82f6;
        }

        .log-type-profile .log-icon-box {
            background: #fff7ed;
            color: #f59e0b;
        }

        .log-type-profile .log-action {
            background: #fff7ed;
            color: #f59e0b;
        }

        .log-type-admin .log-icon-box {
            background: #fdf2f8;
            color: #ec4899;
        }

        .log-type-admin .log-action {
            background: #fdf2f8;
            color: #ec4899;
        }

        .log-type-logout .log-icon-box {
            background: #fef2f2;
            color: #ef4444;
        }

        .log-type-logout .log-action {
            background: #fef2f2;
            color: #ef4444;
        }

        .log-type-view .log-icon-box {
            background: #f0fdfa;
            color: #0d9488;
        }

        .log-type-view .log-action {
            background: #f0fdfa;
            color: #0d9488;
        }

        .log-details {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 4px;
            background: #f8fafc;
            padding: 6px 10px;
            border-radius: 6px;
            border-left: 3px solid #e2e8f0;
        }

        .log-meta {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-section {
            margin-bottom: 25px;
            background: #fff;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .filter-form {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .filter-input {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.2s ease;
            outline: none;
            min-width: 140px;
            height: 38px;
        }

        .filter-input:focus {
            background: #fff;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .date-range-group {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #f8fafc;
            padding: 0 8px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            height: 38px;
        }

        .date-range-group .filter-input {
            border: none;
            background: transparent;
            padding: 0;
            min-width: 110px;
            height: 100%;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            padding-left: 15px;
            border-left: 1px solid #e2e8f0;
            height: 38px;
            align-items: center;
        }

        .btn-filter {
            padding: 8px 18px;
            background: var(--primary-blue);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }

        .btn-filter:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
        }

        .btn-reset {
            padding: 8px 18px;
            background: #fff;
            color: #64748b;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
        }

        .btn-reset:hover {
            background: #f8fafc;
            color: #1e293b;
            border-color: #cbd5e1;
        }

        @media (max-width: 1200px) {
            .filter-actions {
                border-left: none;
                margin-left: 0;
                padding-left: 0;
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>

<body>
    <?php
    function getLogIcon($action)
    {
        if (strpos($action, 'Logged In') !== false) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>';
        } elseif (strpos($action, 'Logged Out') !== false) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';
        } elseif (strpos($action, 'Submitted') !== false) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>';
        } elseif (strpos($action, 'Viewed') !== false) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        } else {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
        }
    }

    function getLogClass($action)
    {
        if (strpos($action, 'Logged In') !== false)
            return 'log-type-login';
        if (strpos($action, 'Logged Out') !== false)
            return 'log-type-logout';
        if (strpos($action, 'Submitted Activity') !== false)
            return 'log-type-submission';
        if (strpos($action, 'Viewed') !== false)
            return 'log-type-view';
        if (strpos($action, 'Updated Admin Profile') !== false)
            return 'log-type-admin';
        if (strpos($action, 'Updated Profile') !== false)
            return 'log-type-profile';
        if (strpos($action, 'User') !== false)
            return 'log-type-admin';
        return '';
    }
    ?>

    <div class="dashboard-container">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="passbook-container">
                <div class="header">
                    <h1>Activity Logs & Monitoring</h1>
                    <p>Chronological record of system-wide user actions</p>
                </div>

                <div class="users-grid" style="grid-template-columns: 1fr;">
                    <!-- Activity Logs Section -->
                    <div class="card">
                        <div class="section-title">System Activity Logs</div>

                        <div class="filter-section">
                            <form method="GET" class="filter-form">
                                <div class="filter-group">
                                    <label class="filter-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        User
                                    </label>
                                    <select name="user_id" class="filter-input">
                                        <option value="0">All Users</option>
                                        <?php foreach ($all_users as $u): ?>
                                            <option value="<?php echo $u['id']; ?>" <?php echo $filter_user_id == $u['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($u['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label class="filter-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path
                                                d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z">
                                            </path>
                                        </svg>
                                        Action
                                    </label>
                                    <select name="action_type" class="filter-input">
                                        <option value="">All Actions</option>
                                        <option value="Logged In" <?php echo $filter_action == 'Logged In' ? 'selected' : ''; ?>>Logins</option>
                                        <option value="Logged Out" <?php echo $filter_action == 'Logged Out' ? 'selected' : ''; ?>>Logouts</option>
                                        <option value="Submitted" <?php echo $filter_action == 'Submitted' ? 'selected' : ''; ?>>Submissions</option>
                                        <option value="Viewed Specific" <?php echo $filter_action == 'Viewed Specific' ? 'selected' : ''; ?>>Views (Details)</option>
                                        <option value="Viewed" <?php echo $filter_action == 'Viewed' ? 'selected' : ''; ?>>Views (Lists)</option>
                                        <option value="Profile" <?php echo $filter_action == 'Profile' ? 'selected' : ''; ?>>Profile Updates</option>
                                        <option value="User" <?php echo $filter_action == 'User' ? 'selected' : ''; ?>>
                                            User Management</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label class="filter-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        Date Range
                                    </label>
                                    <div class="date-range-group">
                                        <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                                            class="filter-input" title="Start Date">
                                        <span style="color: #94a3b8; font-weight: 700;">-</span>
                                        <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                                            class="filter-input" title="End Date">
                                    </div>
                                </div>

                                <div class="filter-actions">
                                    <button type="submit" class="btn-filter">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                        Filter
                                    </button>
                                    <?php if ($filter_user_id > 0 || $filter_action || $start_date || $end_date): ?>
                                        <a href="users.php" class="btn-reset">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="logs-container">
                            <?php if (empty($logs)): ?>
                                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"
                                        stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.5;">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="9" y1="13" x2="15" y2="13"></line>
                                        <line x1="9" y1="17" x2="15" y2="17"></line>
                                        <polyline points="9 9 10 9 11 9"></polyline>
                                    </svg>
                                    <p>No activity logs found yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <div class="log-entry <?php echo getLogClass($log['action']); ?>">
                                        <div class="log-icon-box">
                                            <?php echo getLogIcon($log['action']); ?>
                                        </div>
                                        <div class="log-content">
                                            <span
                                                class="log-time"><?php echo date('M d, Y • h:i A', strtotime($log['created_at'])); ?></span>
                                            <div>
                                                <span class="log-user"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                                <span class="log-action"><?php echo htmlspecialchars($log['action']); ?></span>
                                            </div>
                                            <?php if ($log['details']): ?>
                                                <div class="log-details">
                                                    <?php echo htmlspecialchars($log['details']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="log-meta">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                                    <path
                                                        d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z">
                                                    </path>
                                                </svg>
                                                <?php echo htmlspecialchars($log['ip_address']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>

</body>

</html>