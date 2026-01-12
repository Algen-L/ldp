<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ../index.php");
    exit;
}

// Fetch all users for filtering
$usersStmt = $pdo->query("SELECT id, full_name FROM users WHERE role != 'admin' ORDER BY full_name ASC");
$all_users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Filtering
$filter_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_search = isset($_GET['search']) ? $_GET['search'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$sql = "SELECT ld.*, u.full_name, u.office_station 
        FROM ld_activities ld 
        JOIN users u ON ld.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($filter_user_id > 0) {
    $sql .= " AND ld.user_id = ?";
    $params[] = $filter_user_id;
}

if ($filter_status) {
    if ($filter_status === 'Reviewed') {
        $sql .= " AND ld.reviewed_by_supervisor = 1 AND ld.recommending_asds = 0";
    } elseif ($filter_status === 'Recommending') {
        $sql .= " AND ld.recommending_asds = 1 AND ld.approved_sds = 0";
    } elseif ($filter_status === 'Approved') {
        $sql .= " AND ld.approved_sds = 1";
    } elseif ($filter_status === 'Pending') {
        $sql .= " AND ld.reviewed_by_supervisor = 0";
    }
}

if ($filter_search) {
    $sql .= " AND (ld.title LIKE ? OR ld.competency LIKE ?)";
    $params[] = "%$filter_search%";
    $params[] = "%$filter_search%";
}

if ($start_date) {
    $sql .= " AND ld.date_attended >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $sql .= " AND ld.date_attended <= ?";
    $params[] = $end_date;
}

$sql .= " ORDER BY ld.date_attended DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions Management - Admin</title>
    <?php require 'includes/admin_head.php'; ?>
    <link rel="stylesheet" href="../css/base/tables.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(226, 232, 240, 0.8);
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            --accent-blue: #3b82f6;
            --accent-green: #22c55e;
            --accent-yellow: #f59e0b;
            --accent-orange: #f97316;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
        }

        .passbook-container {
            animation: fadeIn 0.5s ease-out;
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
        }

        /* Filter Section Styling */
        .filter-section {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .filter-section:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .filter-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .filter-input {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            color: #1e293b;
            transition: all 0.2s ease;
            width: 100%;
            outline: none;
        }

        .filter-input:focus {
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .btn-filter {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(249, 115, 22, 0.2);
        }

        .btn-filter:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -2px rgba(249, 115, 22, 0.3);
        }

        /* Table Styling */
        .styled-table {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .styled-table thead th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            padding: 16px 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .styled-table thead th:first-child {
            border-left: 3px solid var(--accent-orange);
        }

        .styled-table tbody tr {
            transition: all 0.2s ease;
        }

        .styled-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .styled-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        /* Progress Dots Refined */
        .progress-mini {
            display: flex;
            gap: 6px;
            justify-content: center;
            background: #f1f5f9;
            padding: 6px 10px;
            border-radius: 20px;
            width: fit-content;
            margin: 0 auto;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #cbd5e1;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dot.active.yellow {
            background: var(--accent-yellow);
            box-shadow: 0 0 8px rgba(245, 158, 11, 0.4);
        }

        .dot.active.blue {
            background: var(--accent-blue);
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.4);
        }

        .dot.active {
            background: var(--accent-green);
            box-shadow: 0 0 8px rgba(34, 197, 94, 0.4);
        }

        /* Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fef3c7;
        }

        .status-reviewed {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #dbeafe;
        }

        .status-recommending {
            background: #fdf2f8;
            color: #9d174d;
            border: 1px solid #fce7f3;
        }

        .status-approved {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #d1fae5;
        }

        /* Action Buttons */
        .action-flex {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.2s ease;
            text-decoration: none;
            border: 1px solid #e2e8f0;
        }

        .btn-view {
            color: #64748b;
            background: white;
        }

        .btn-view:hover {
            background: #f1f5f9;
            color: #1e293b;
            border-color: #cbd5e1;
        }

        .btn-edit {
            color: var(--accent-blue);
            background: #eff6ff;
            border-color: #dbeafe;
        }

        .btn-edit:hover {
            background: #dbeafe;
            transform: scale(1.05);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .user-station {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .activity-title {
            font-weight: 500;
            color: #334155;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .date-cell {
            color: #64748b;
            font-weight: 500;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="passbook-container" style="width: 1000px; max-width: 98%;">
                <div class="header">
                    <h1>Submissions Management</h1>
                    <p>Track and approve all user-submitted L&D activities</p>
                </div>

                <div style="display: flex; gap: 30px; align-items: flex-start; margin-top: 25px;">
                    <!-- Vertical Filter Sidebar -->
                    <div class="filter-section"
                        style="width: 260px; flex-shrink: 0; position: sticky; top: 20px; display: flex; flex-direction: column; gap: 20px;">
                        <div
                            style="font-size: 0.8rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: -10px; display: flex; align-items: center; gap: 8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            Filter Options
                        </div>

                        <form method="GET" class="filter-form"
                            style="display: flex; flex-direction: column; gap: 18px;">
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
                                <label class="filter-label">Status</label>
                                <select name="status" class="filter-input">
                                    <option value="">All Status</option>
                                    <option value="Pending" <?php echo $filter_status == 'Pending' ? 'selected' : ''; ?>>
                                        Pending</option>
                                    <option value="Reviewed" <?php echo $filter_status == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                    <option value="Recommending" <?php echo $filter_status == 'Recommending' ? 'selected' : ''; ?>>Recommending</option>
                                    <option value="Approved" <?php echo $filter_status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Search Keywords</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>"
                                    placeholder="Title or Competency..." class="filter-input">
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Date Range</label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="filter-group">
                                        <span
                                            style="font-size: 0.65rem; color: #94a3b8; font-weight: 600; text-transform: uppercase;">From</span>
                                        <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                                            class="filter-input">
                                    </div>
                                    <div class="filter-group">
                                        <span
                                            style="font-size: 0.65rem; color: #94a3b8; font-weight: 600; text-transform: uppercase;">To</span>
                                        <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                                            class="filter-input">
                                    </div>
                                </div>
                            </div>

                            <div class="filter-actions"
                                style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                                <button type="submit" class="btn-filter"
                                    style="width: 100%; justify-content: center; height: 42px;">Apply Filters</button>
                                <?php if ($filter_user_id > 0 || $filter_status || $filter_search || $start_date || $end_date): ?>
                                    <a href="submissions.php" class="btn-reset" title="Clear All"
                                        style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 42px; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 10px; color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;">
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

                    <!-- Table Column -->
                    <div style="flex-grow: 1; overflow-x: auto;">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th>Date Attended</th>
                                    <th>User Details</th>
                                    <th>Activity Title</th>
                                    <th>Progress Step</th>
                                    <th>Current Status</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activities)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 60px; color: #94a3b8;">
                                            <div style="font-size: 3rem; margin-bottom: 20px; opacity: 0.2;">📂</div>
                                            <p style="font-weight: 500;">No submissions match your current filters.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activities as $act): ?>
                                        <tr>
                                            <td class="date-cell">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5;">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                                    </svg>
                                                    <?php echo date('M d, Y', strtotime($act['date_attended'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <span
                                                        class="user-name"><?php echo htmlspecialchars($act['full_name']); ?></span>
                                                    <span
                                                        class="user-station"><?php echo htmlspecialchars($act['office_station']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="activity-title"><?php echo htmlspecialchars($act['title']); ?></div>
                                            </td>
                                            <td>
                                                <div class="progress-mini" title="Progress: <?php
                                                if ($act['approved_sds'])
                                                    echo 'Final Approved';
                                                elseif ($act['recommending_asds'])
                                                    echo 'Recommending Stage';
                                                elseif ($act['reviewed_by_supervisor'])
                                                    echo 'Reviewed Stage';
                                                else
                                                    echo 'Submission Pending';
                                                ?>">
                                                    <div
                                                        class="dot <?php echo $act['reviewed_by_supervisor'] ? 'active yellow' : ''; ?>">
                                                    </div>
                                                    <div
                                                        class="dot <?php echo $act['recommending_asds'] ? 'active blue' : ''; ?>">
                                                    </div>
                                                    <div class="dot <?php echo $act['approved_sds'] ? 'active' : ''; ?>"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $pill_class = 'status-pending';
                                                $label = 'Pending';
                                                if ($act['approved_sds']) {
                                                    $pill_class = 'status-approved';
                                                    $label = 'Approved';
                                                } elseif ($act['recommending_asds']) {
                                                    $pill_class = 'status-recommending';
                                                    $label = 'Recommending';
                                                } elseif ($act['reviewed_by_supervisor']) {
                                                    $pill_class = 'status-reviewed';
                                                    $label = 'Reviewed';
                                                }
                                                ?>
                                                <span class="status-pill <?php echo $pill_class; ?>">
                                                    <?php echo $label; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-flex" style="justify-content: flex-end;">
                                                    <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>"
                                                        class="btn-action btn-view" title="View Details">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                            <circle cx="12" cy="12" r="3"></circle>
                                                        </svg>
                                                    </a>
                                                    <a href="../pages/edit_activity.php?id=<?php echo $act['id']; ?>"
                                                        class="btn-action btn-edit" title="Edit Entry">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path
                                                                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                            </path>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                            </path>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>