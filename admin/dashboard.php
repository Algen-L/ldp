<?php
session_start();
require '../includes/init_repos.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head' && $_SESSION['role'] !== 'head_hr')) {
    header("Location: ../index.php");
    exit;
}

// Filter Logic
$filter = $_GET['filter'] ?? 'month'; // Default to month
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$filters = [
    'filter_type' => $filter,
    'start_date' => $date_from,
    'end_date' => $date_to
];

// If dates are provided, force filter to 'custom' (implicit in getAllActivities filter logic if I update it)
// Let's refine getAllActivities logic to handle these shortcuts or just implement the logic here.
// Actually, I'll update ActivityRepository->getAllActivities to handle common filters.

$activities = $activityRepo->getAllActivities($filters);

// Calculate Statistics
$totalSubmissions = count($activities);

// Fetch all offices from DB for categorization
$office_map = []; // [OfficeName => Category]
try {
    $stmt_all_offices = $pdo->query("SELECT name, category FROM offices");
    while ($row = $stmt_all_offices->fetch(PDO::FETCH_ASSOC)) {
        $office_map[strtoupper($row['name'])] = $row['category'];
    }
} catch (PDOException $e) { /* Fallback or empty */
}

// Fetch Users Count
$totalUsers = $userRepo->getTotalUserCount();

// Count per status
$pendingCount = 0;
$approvedCount = 0;
foreach ($activities as $act) {
    if ($act['status'] === 'Pending')
        $pendingCount++;
    if ($act['status'] === 'Approved')
        $approvedCount++;
}

// Analytics: Submissions by General Office
$osdsCount = 0;
$cidCount = 0;
$sgodCount = 0;
$frequencyData = []; // To store [date => count]

foreach ($activities as $act) {
    // Categorize using DB map
    $office = strtoupper($act['office_station'] ?? '');
    $category = $office_map[$office] ?? '';

    if ($category === 'OSDS') {
        $osdsCount++;
    } elseif ($category === 'CID') {
        $cidCount++;
    } elseif ($category === 'SGOD') {
        $sgodCount++;
    }

    // Group for frequency chart
    $actDate = $act['activity_created_at'] ?? $act['created_at'];
    if (isset($actDate)) {
        $dateKey = date('Y-m-d', strtotime($actDate));
        $frequencyData[$dateKey] = ($frequencyData[$dateKey] ?? 0) + 1;
    }
}

// Sort frequency by date and prepare for JS
ksort($frequencyData);
$freqLabels = array_keys($frequencyData);
$freqValues = array_values($frequencyData);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LDP</title>
    <?php require '../includes/admin_head.php'; ?>
    <link rel="stylesheet" href="../css/admin/dashboard.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="app-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Dashboard Overview</h1>
                    </div>
                </div>
                <div class="top-bar-right top-bar-right-actions">
                    <div class="current-date-box">
                        <div class="time-section">
                            <span id="real-time-clock"><?php echo date('h:i:s A'); ?></span>
                        </div>
                        <div class="date-section">
                            <i class="bi bi-calendar3"></i>
                            <span><?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>
                    <form method="GET" id="filterForm" class="filter-form">
                        <div class="custom-dropdown" id="filterDropdown">
                            <input type="hidden" name="filter" id="filterInput"
                                value="<?php echo htmlspecialchars($filter); ?>">
                            <div class="dropdown-trigger" id="dropdownTrigger">
                                <div>
                                    <i class="bi bi-funnel funnel-icon"></i>
                                    <span id="selectedFilterText">
                                        <?php
                                        switch ($filter) {
                                            case 'today':
                                                echo 'Today';
                                                break;
                                            case 'week':
                                                echo 'This Week';
                                                break;
                                            case 'month':
                                                echo 'This Month';
                                                break;
                                            case 'all':
                                                echo 'All Time';
                                                break;
                                            case 'custom':
                                                echo 'Custom Range';
                                                break;
                                            default:
                                                echo 'Sort By';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <i class="bi bi-chevron-down chevron-down"></i>
                            </div>

                            <div class="dropdown-menu-custom" id="dropdownMenu">
                                <div class="dropdown-item-custom <?php echo ($filter === 'today') ? 'active' : ''; ?>"
                                    data-value="today">
                                    <i class="bi bi-calendar-event"></i> Today
                                </div>
                                <div class="dropdown-item-custom <?php echo ($filter === 'week') ? 'active' : ''; ?>"
                                    data-value="week">
                                    <i class="bi bi-calendar-range"></i> This Week
                                </div>
                                <div class="dropdown-item-custom <?php echo ($filter === 'month') ? 'active' : ''; ?>"
                                    data-value="month">
                                    <i class="bi bi-calendar-month"></i> This Month
                                </div>
                                <div class="dropdown-item-custom <?php echo ($filter === 'all') ? 'active' : ''; ?>"
                                    data-value="all">
                                    <i class="bi bi-infinity"></i> All Time
                                </div>
                                <div class="dropdown-item-custom <?php echo ($filter === 'custom') ? 'active' : ''; ?>"
                                    data-value="custom">
                                    <i class="bi bi-calendar-plus"></i> Custom Range
                                </div>
                            </div>

                            <div id="customDateInputs" class="custom-range-inputs"
                                style="display: <?php echo ($filter === 'custom') ? 'flex' : 'none'; ?>;">
                                <div class="date-input-wrapper">
                                    <input type="date" name="date_from"
                                        value="<?php echo htmlspecialchars($date_from); ?>"
                                        class="form-control form-control-sm custom-date-input" />
                                </div>
                                <span class="date-range-to">to</span>
                                <div class="date-input-wrapper">
                                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                                        class="form-control form-control-sm custom-date-input" />
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm apply-btn">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </header>

            <main class="content-wrapper">
                <div class="stats-row">
                    <div class="stat-card" style="--accent-color: var(--vibrant-blue);">
                        <div class="stat-icon" style="background: rgba(14, 165, 233, 0.1); color: var(--vibrant-blue);">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Submissions</span>
                            <span class="stat-value"><?php echo number_format($totalSubmissions); ?></span>
                        </div>
                    </div>

                    <div class="stat-card" style="--accent-color: var(--vibrant-orange);">
                        <div class="stat-icon"
                            style="background: rgba(249, 115, 22, 0.1); color: var(--vibrant-orange);">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Total Users</span>
                            <span class="stat-value"><?php echo number_format($totalUsers); ?></span>
                        </div>
                    </div>

                    <div class="stat-card" style="--accent-color: #6366f1;">
                        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Pending</span>
                            <span class="stat-value"><?php echo number_format($pendingCount); ?></span>
                        </div>
                    </div>

                    <div class="stat-card" style="--accent-color: #10b981;">
                        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Approved</span>
                            <span class="stat-value"><?php echo number_format($approvedCount); ?></span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-row-middle">
                    <div class="dashboard-card hover-elevate">
                        <div class="card-header card-header-gradient">
                            <h2 class="card-title-white"><i class="bi bi-bar-chart-line card-title-icon-white"></i>
                                Submission Frequency</h2>
                        </div>
                        <div class="card-body p-2 h-180">
                            <canvas id="frequencyChart"></canvas>
                        </div>
                    </div>

                    <div class="dashboard-card hover-elevate">
                        <div class="card-header card-header-standard">
                            <h2 class="card-title-standard"><i class="bi bi-building text-gradient"></i> Office
                                Activity Distribution</h2>
                        </div>
                        <div class="card-body office-distribution-body">
                            <div class="doughnut-wrapper">
                                <canvas id="officeChart"></canvas>
                            </div>
                            <div class="office-legend">
                                <div class="legend-item">
                                    <div class="legend-label-box">
                                        <span class="legend-color-dot bg-vibrant-orange shadow-vibrant-orange"></span>
                                        <span class="legend-text">OSDS</span>
                                    </div>
                                    <span class="legend-value"><?php echo number_format($osdsCount); ?></span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-label-box">
                                        <span class="legend-color-dot bg-warning"></span>
                                        <span class="legend-text">CID</span>
                                    </div>
                                    <span class="legend-value"><?php echo number_format($cidCount); ?></span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-label-box">
                                        <span class="legend-color-dot bg-vibrant-blue shadow-vibrant-blue"></span>
                                        <span class="legend-text">SGOD</span>
                                    </div>
                                    <span class="legend-value"><?php echo number_format($sgodCount); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-row-bottom <?php echo ($_SESSION['role'] === 'head_hr') ? 'full-view' : ''; ?>">
                    <?php if ($_SESSION['role'] !== 'head_hr'): ?>
                        <div class="dashboard-card hover-elevate">
                            <div class="card-header" style="padding: 12px 20px;">
                                <h2 style="font-size: 0.9rem;"><i class="bi bi-megaphone text-gradient"></i> Recent
                                    Activity
                                    Submitted</h2>
                            </div>
                            <div class="card-body" style="padding: 0; max-height: 350px; overflow-y: auto;">
                                <div class="activity-feed">
                                    <?php if (empty($activities)): ?>
                                        <div class="text-center py-4 text-muted" style="font-size: 0.85rem;">No recent
                                            activities.</div>
                                    <?php else: ?>
                                        <?php foreach (array_slice($activities, 0, 10) as $i => $act):
                                            $f_office = strtoupper($act['office_station'] ?? '');
                                            $f_cat = $office_map[$f_office] ?? '';
                                            $feed_class = '';
                                            if ($f_cat === 'OSDS')
                                                $feed_class = 'osds';
                                            elseif ($f_cat === 'CID')
                                                $feed_class = 'cid';
                                            elseif ($f_cat === 'SGOD')
                                                $feed_class = 'sgod';
                                            ?>
                                            <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>"
                                                class="feed-item <?php echo $feed_class; ?>" style="text-decoration: none;">
                                                <?php if ($act['profile_picture']): ?>
                                                    <img src="../<?php echo htmlspecialchars($act['profile_picture']); ?>"
                                                        class="feed-avatar">
                                                <?php else: ?>
                                                    <div class="feed-avatar-placeholder">
                                                        <?php echo strtoupper(substr($act['full_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="feed-info">
                                                    <span
                                                        class="feed-user"><?php echo htmlspecialchars($act['full_name']); ?></span>
                                                    <span class="feed-activity"
                                                        title="<?php echo htmlspecialchars($act['title']); ?>">
                                                        <?php echo htmlspecialchars($act['title']); ?>
                                                    </span>
                                                </div>
                                                <div style="display: flex; align-items: center;">
                                                    <?php if ($i < 3): ?>
                                                        <span class="pulse-indicator"></span>
                                                    <?php endif; ?>
                                                    <span class="feed-time"
                                                        title="<?php echo date('M d, Y h:i A', strtotime($act['activity_created_at'] ?? $act['created_at'])); ?>">
                                                        <?php echo time_elapsed_string($act['activity_created_at'] ?? $act['created_at']); ?>
                                                    </span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-card hover-elevate">
                            <div class="card-header card-header-gradient">
                                <h2 class="card-title-white"><i class="bi bi-journal-text card-title-icon-white"></i> Recent
                                    Activity Logs</h2>
                                <a href="submissions.php" class="btn btn-sm view-all-btn">
                                    View All <i class="bi bi-arrow-right" style="margin-left: 4px;"></i>
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive max-h-350 overflow-y-auto">
                                    <table class="data-table">
                                        <thead class="sticky-table-header">
                                            <tr>
                                                <th>Submission Date</th>
                                                <th>User / Personnel</th>
                                                <th>Activity Description</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($activities)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-5">
                                                        <div class="empty-state">
                                                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                                            <p class="mt-3">No activity logs recorded yet.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach (array_slice($activities, 0, 20) as $act):
                                                    $row_class = '';
                                                    $office = strtoupper($act['office_station'] ?? '');
                                                    $cat = $office_map[$office] ?? '';
                                                    if ($cat === 'OSDS')
                                                        $row_class = 'row-osds';
                                                    elseif ($cat === 'CID')
                                                        $row_class = 'row-cid';
                                                    elseif ($cat === 'SGOD')
                                                        $row_class = 'row-sgod';
                                                    ?>
                                                    <tr class="<?php echo $row_class; ?>">
                                                        <td>
                                                            <span
                                                                class="cell-primary"><?php echo date('M d, Y', strtotime($act['activity_created_at'] ?? $act['created_at'])); ?></span>
                                                            <span
                                                                class="cell-secondary"><?php echo date('h:i A', strtotime($act['activity_created_at'] ?? $act['created_at'])); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="cell-primary">
                                                                <?php echo htmlspecialchars($act['full_name']); ?>
                                                            </div>
                                                            <div class="cell-secondary" style="font-size: 0.65rem;">
                                                                <?php echo htmlspecialchars($act['office_station']); ?>
                                                            </div>
                                                        </td>
                                                        <td style="max-width: 200px;">
                                                            <span class="cell-primary text-truncate" style="display: block;"
                                                                title="<?php echo htmlspecialchars($act['title']); ?>">
                                                                <?php echo htmlspecialchars($act['title']); ?>
                                                            </span>
                                                            <span class="cell-secondary">
                                                                <?php echo htmlspecialchars($act['type_ld'] ?? 'Training'); ?>
                                                            </span>
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
                                                            <span class="status-badge <?php echo $status_class; ?>"
                                                                style="padding: 4px 10px; font-size: 0.65rem;">
                                                                <?php echo $label; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Head HR Placeholder -->
                        <div class="dashboard-card hover-elevate"
                            style="background: rgba(15, 76, 117, 0.02); border-style: dashed; display: flex; align-items: center; justify-content: center; padding: 60px;">
                            <div class="text-center">
                                <i class="bi bi-shield-lock"
                                    style="font-size: 3rem; color: var(--text-muted); opacity: 0.2; display: block; margin-bottom: 16px;"></i>
                                <span style="font-weight: 700; color: var(--text-muted); opacity: 0.6;">Management Overview
                                    Focused</span>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px; opacity: 0.5;">Use
                                    the sidebar to access Activity Logs and User Status.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by
                        Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
            </footer>
        </div>
    </div>
    <script>
        // Pass PHP data to JavaScript
        window.dashboardData = {
            freqLabels: <?php echo json_encode($freqLabels); ?>,
            freqValues: <?php echo json_encode($freqValues); ?>,
            osdsCount: <?php echo $osdsCount; ?>,
            cidCount: <?php echo $cidCount; ?>,
            sgodCount: <?php echo $sgodCount; ?>
        };
    </script>
    <script src="../js/admin/dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        // Inline script removed and moved to js/admin/dashboard.js
    </script>
</body>

</html>