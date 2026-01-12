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
    <?php require 'includes/admin_head.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(226, 232, 240, 0.8);
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            --accent-blue: #3b82f6;
            --accent-orange: #f97316;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
        }

        .passbook-container {
            animation: fadeIn 0.5s ease-out;
            width: 1400px;
            max-width: 98%;
            margin: 0 auto;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #1e293b;
            border-left: 5px solid var(--accent-orange);
            padding-left: 15px;
        }

        .header p {
            color: #64748b;
            font-weight: 400;
            padding-left: 20px;
        }

        /* Card & Section Styling Submissions Reference */
        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .section-title {
            padding: 20px 24px;
            background: #f1f5f9;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.01em;
            text-transform: uppercase;
            font-size: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .filter-section {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .filter-header {
            font-size: 0.85rem;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 8px;
            display: block;
            text-transform: none;
            letter-spacing: normal;
        }

        .filter-group {
            margin-bottom: 20px;
        }

        .filter-input {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            padding: 12px 16px;
            border-radius: 12px;
            width: 100%;
            font-size: 0.9rem;
            color: #1e293b;
            transition: all 0.2s ease;
            outline: none;
            box-sizing: border-box;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
        }

        .filter-input[type="date"] {
            background-image: none;
        }

        .filter-input:focus {
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
        }

        .btn-filter {
            background: #ff5722;
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(255, 87, 34, 0.2);
        }

        .btn-filter:hover {
            background: #f4511e;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(255, 87, 34, 0.3);
        }

        /* Log Entry Refinements */
        .log-entry {
            border-bottom: 1px solid #f1f5f9;
            padding: 16px 24px;
            transition: all 0.2s ease;
            display: flex;
            gap: 16px;
            border-left: 0 solid var(--accent-orange);
        }

        .log-entry:hover {
            background-color: #f8fafc;
            border-left-width: 4px;
        }

        .log-icon-box {
            border-radius: 12px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .log-time {
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .log-user {
            color: #1e293b;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .log-action {
            font-weight: 600;
            font-size: 0.8rem;
            margin-left: 8px;
        }

        .log-details {
            border-radius: 10px;
            border-left: 4px solid #e2e8f0;
            background: #f8fafc;
            padding: 12px 15px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #475569;
        }

        .log-meta {
            font-weight: 600;
            color: #94a3b8;
            font-size: 0.7rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
    </style>
    <!-- Page Styles -->
    <link rel="stylesheet" href="../css/pages/user.css">
    <link rel="stylesheet" href="../css/base/tables.css">
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

                <div style="display: flex; gap: 30px; align-items: flex-start; margin-top: 25px;">
                    <!-- Vertical Filter Sidebar -->
                    <div class="filter-section" style="width: 280px; flex-shrink: 0; position: sticky; top: 20px;">
                        <div class="filter-header">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            Filter Options
                        </div>

                        <form method="GET" class="filter-form">
                            <div class="filter-group">
                                <label class="filter-label">User</label>
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
                                <label class="filter-label">Action Type</label>
                                <select name="action_type" class="filter-input">
                                    <option value="">All Actions</option>
                                    <option value="Logged In" <?php echo $filter_action == 'Logged In' ? 'selected' : ''; ?>>Logins</option>
                                    <option value="Logged Out" <?php echo $filter_action == 'Logged Out' ? 'selected' : ''; ?>>Logouts</option>
                                    <option value="Submitted" <?php echo $filter_action == 'Submitted' ? 'selected' : ''; ?>>Submissions</option>
                                    <option value="Viewed Specific" <?php echo $filter_action == 'Viewed Specific' ? 'selected' : ''; ?>>Views (Details)</option>
                                    <option value="Viewed" <?php echo $filter_action == 'Viewed' ? 'selected' : ''; ?>>
                                        Views (Lists)</option>
                                    <option value="Profile" <?php echo $filter_action == 'Profile' ? 'selected' : ''; ?>>
                                        Profile Updates</option>
                                    <option value="User" <?php echo $filter_action == 'User' ? 'selected' : ''; ?>>
                                        User Management</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label"
                                    style="text-transform: uppercase; font-size: 0.65rem; color: #94a3b8; font-weight: 700; margin-bottom: 12px; display: block;">Date
                                    Range</label>

                                <div class="filter-group" style="margin-bottom: 15px;">
                                    <span
                                        style="font-size: 0.65rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; display: block;">From</span>
                                    <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                                        class="filter-input">
                                </div>

                                <div class="filter-group" style="margin-bottom: 0;">
                                    <span
                                        style="font-size: 0.65rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; display: block;">To</span>
                                    <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                                        class="filter-input">
                                </div>
                            </div>

                            <div class="filter-actions"
                                style="margin-top: 25px; display: flex; flex-direction: column; gap: 12px;">
                                <button type="submit" class="btn-filter">Apply Filters</button>

                                <?php if ($filter_user_id > 0 || $filter_action || $start_date || $end_date): ?>
                                    <a href="users.php" class="btn-reset"
                                        style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px; background: transparent; border: 1.5px solid #e2e8f0; border-radius: 12px; color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                                            <polyline points="3 3 3 8 8 8"></polyline>
                                        </svg>
                                        Clear All
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Logs Column -->
                    <div style="flex-grow: 1;">
                        <div class="card"
                            style="margin-top: 0; padding: 0; border: 1px solid #edf2f7; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                            <div class="section-title"
                                style="padding: 20px 24px; border-bottom: 1px solid #edf2f7; margin-bottom: 0;">
                                System Activity Logs</div>

                            <div class="logs-container">
                                <?php if (empty($logs)): ?>
                                    <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"
                                            stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.5;">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z">
                                            </path>
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
                                                    <span
                                                        class="log-user"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                                    <span
                                                        class="log-action"><?php echo htmlspecialchars($log['action']); ?></span>
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
                            </div> <!-- logs-container -->
                        </div> <!-- card -->
                    </div> <!-- logs-column -->
                </div> <!-- flex-row -->
            </div> <!-- passbook-container -->
        </div> <!-- main-content -->
    </div> <!-- dashboard-container -->
</body>

</html>