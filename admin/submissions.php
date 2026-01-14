<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head')) {
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

$sql .= " ORDER BY ld.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define Office Lists for Highlighting
$osdsOffices = ['ADMINISTRATIVE (PERSONEL)', 'ADMINISTRATIVE (PROPERTY AND SUPPLY)', 'ADMINISTRATIVE (RECORDS)', 'ADMINISTRATIVE (CASH)', 'ADMINISTRATIVE (GENERAL SERVICES)', 'FINANCE (ACCOUNTING)', 'FINANCE (BUDGET)', 'LEGAL', 'ICT'];
$sgodOffices = ['SCHOOL MANAGEMENT MONITORING & EVALUATION', 'HUMAN RESOURCES DEVELOPMENT', 'DISASTER RISK REDUCTION AND MANAGEMENT', 'EDUCATION FACILITIES', 'SCHOOL HEALTH AND NUTRITION', 'SCHOOL HEALTH AND NUTRITION (DENTAL)', 'SCHOOL HEALTH AND NUTRITION (MEDICAL)'];
$cidOffices = ['CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)', 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions Management - Admin</title>
    <?php require 'includes/admin_head.php'; ?>
    <style>
        .highlight-pending-approval {
            background-color: #fff7ed !important;
            /* Light Orange background */
            border-left: 4px solid #f97316 !important;
            /* Orange accent border */
            position: relative;
        }

        .highlight-pending-approval::after {
            content: "Needs Approval";
            position: absolute;
            top: 4px;
            right: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            background: #f97316;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Submission Management</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <i class="bi bi-calendar3"></i>
                        <span><?php echo date('l, F d, Y'); ?></span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <!-- Specialized Minimal Filter Bar -->
                <div class="filter-bar">
                    <form method="GET" class="filter-form">
                        <div class="filter-group" style="flex: 2; min-width: 300px;">
                            <div style="position: relative; width: 100%;">
                                <i class="bi bi-search"
                                    style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem;"></i>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>"
                                    placeholder="Search by Title, Personnel or Competency..." class="filter-input"
                                    style="padding-left: 42px; width: 100%; height: 44px; border-radius: 12px; border-color: var(--border-color);">
                            </div>
                        </div>

                        <div class="filter-group">
                            <select name="user_id" class="filter-select" style="height: 44px; border-radius: 12px;">
                                <option value="0">All Personnel</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo $filter_user_id == $u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <select name="status" class="filter-select" style="height: 44px; border-radius: 12px;">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php echo $filter_status == 'Pending' ? 'selected' : ''; ?>>
                                    Pending Approval</option>
                                <option value="Reviewed" <?php echo $filter_status == 'Reviewed' ? 'selected' : ''; ?>>
                                    Reviewed</option>
                                <option value="Recommending" <?php echo $filter_status == 'Recommending' ? 'selected' : ''; ?>>Recommending</option>
                                <option value="Approved" <?php echo $filter_status == 'Approved' ? 'selected' : ''; ?>>
                                    Approved</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <div
                                style="display: flex; align-items: center; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 12px; height: 44px; padding: 0 12px; gap: 8px;">
                                <i class="bi bi-calendar3 text-muted" style="font-size: 0.85rem;"></i>
                                <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                                    class="filter-input"
                                    style="border: none; background: transparent; padding: 0; min-width: 120px; font-size: 0.85rem; height: auto;">
                                <span class="text-muted" style="font-size: 0.8rem;">to</span>
                                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="filter-input"
                                    style="border: none; background: transparent; padding: 0; min-width: 120px; font-size: 0.85rem; height: auto;">
                            </div>
                        </div>

                        <div class="filter-actions" style="margin-left: 12px;">
                            <button type="submit" class="btn btn-primary"
                                style="height: 44px; border-radius: 12px; min-width: 110px;">
                                <i class="bi bi-funnel"></i> Apply
                            </button>
                            <?php if ($filter_user_id > 0 || $filter_status || $filter_search || $start_date || $end_date): ?>
                                <a href="submissions.php" class="btn btn-secondary"
                                    style="height: 44px; border-radius: 12px; width: 44px; padding: 0;"
                                    title="Reset Filters">
                                    <i class="bi bi-arrow-counterclockwise" style="margin: 0;"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Submissions Table Section -->
                <div class="dashboard-card hover-elevate">
                    <div class="card-header">
                        <h2><i class="bi bi-file-earmark-text text-gradient"></i> Activity Submissions</h2>
                        <span class="result-count">Showing <?php echo count($activities); ?> total records</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Attendance Date</th>
                                        <th>Personnel Info</th>
                                        <th>Activity Details</th>
                                        <th style="text-align: center;">Approvals</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Options</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activities)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="bi bi-folder2-open text-muted"
                                                        style="font-size: 3.5rem; opacity: 0.4;"></i>
                                                    <p class="mt-3 text-muted">No submissions found matching your filters.
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($activities as $act):
                                            $row_class = '';
                                            $office = strtoupper($act['office_station'] ?? '');
                                            if (in_array($office, $osdsOffices))
                                                $row_class = 'row-osds';
                                            elseif (in_array($office, $cidOffices))
                                                $row_class = 'row-cid';
                                            elseif (in_array($office, $sgodOffices))
                                                $row_class = 'row-sgod';

                                            // Highlight Logic for Immediate Head
                                            if ($_SESSION['role'] === 'immediate_head' && $act['reviewed_by_supervisor'] && $act['recommending_asds'] && !$act['approved_sds']) {
                                                $row_class .= ' highlight-pending-approval';
                                            }
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td>
                                                    <span class="cell-primary">
                                                        <i class="bi bi-calendar-event text-muted me-2"></i>
                                                        <?php
                                                        $dates = explode(', ', $act['date_attended']);
                                                        echo date('M d, Y', strtotime($dates[0]));
                                                        if (count($dates) > 1)
                                                            echo ' (+' . (count($dates) - 1) . '...)';
                                                        ?>
                                                    </span>
                                                    <span class="cell-secondary">Logged
                                                        <?php echo date('M d, Y', strtotime($act['created_at'])); ?></span>
                                                </td>
                                                <td>
                                                    <div class="cell-primary"><?php echo htmlspecialchars($act['full_name']); ?>
                                                    </div>
                                                    <div class="cell-secondary">
                                                        <?php echo htmlspecialchars($act['office_station']); ?>
                                                    </div>
                                                </td>
                                                <td style="max-width: 320px;">
                                                    <div class="cell-primary text-truncate"
                                                        title="<?php echo htmlspecialchars($act['title']); ?>">
                                                        <?php echo htmlspecialchars($act['title']); ?>
                                                    </div>
                                                    <div class="cell-secondary text-truncate">
                                                        <?php echo htmlspecialchars($act['competency']); ?>
                                                    </div>
                                                </td>
                                                <td style="text-align: center;">
                                                    <div class="approval-indicators"
                                                        style="display: flex; gap: 8px; justify-content: center;">
                                                        <span title="Supervisor Reviewed">
                                                            <i class="bi bi-check-circle-fill <?php echo $act['reviewed_by_supervisor'] ? 'text-success' : 'text-muted'; ?>"
                                                                style="font-size: 1.1rem; opacity: <?php echo $act['reviewed_by_supervisor'] ? '1' : '0.4'; ?>;"></i>
                                                        </span>
                                                        <span title="ASDS Recommended">
                                                            <i class="bi bi-check-circle-fill <?php echo $act['recommending_asds'] ? 'text-primary' : 'text-muted'; ?>"
                                                                style="font-size: 1.1rem; opacity: <?php echo $act['recommending_asds'] ? '1' : '0.4'; ?>;"></i>
                                                        </span>
                                                        <span title="SDS Approved">
                                                            <i class="bi bi-check-circle-fill <?php echo $act['approved_sds'] ? 'text-success' : 'text-muted'; ?>"
                                                                style="font-size: 1.1rem; opacity: <?php echo $act['approved_sds'] ? '1' : '0.4'; ?>;"></i>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = 'status-pending';
                                                    $label = 'Pending';
                                                    if ($act['approved_sds']) {
                                                        $status_class = 'status-resolved';
                                                        $label = 'Approved';
                                                    } elseif ($act['recommending_asds']) {
                                                        $status_class = 'status-in_progress';
                                                        $label = 'Recommended';
                                                    } elseif ($act['reviewed_by_supervisor']) {
                                                        $status_class = 'status-accepted';
                                                        $label = 'Reviewed';
                                                    }
                                                    ?>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo $label; ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div class="action-buttons"
                                                        style="display: flex; gap: 8px; justify-content: flex-end;">
                                                        <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>"
                                                            class="btn btn-secondary btn-icon" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="../pages/edit_activity.php?id=<?php echo $act['id']; ?>"
                                                            class="btn btn-outline btn-icon" title="Edit Entry">
                                                            <i class="bi bi-pencil" style="color: var(--primary);"></i>
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
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Empowering
                        Personnel Professional Growth.</span></p>
            </footer>
        </div>
    </div>
</body>

</html>