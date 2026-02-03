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

// Initialize HR variables to prevent undefined warnings
$popOSDS = 0;
$popCID = 0;
$popSGOD = 0;

// --- HR SPECIFIC ANALYTICS ---
$hrStats = [];
$auditTrail = [];
$activePersonnel = [];
$registrationGrowth = [];

if ($_SESSION['role'] === 'head_hr') {
    try {
        // 1. Today's Logins
        $stmt_logins = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'Logged In' AND DATE(created_at) = CURRENT_DATE");
        $stmt_logins->execute();
        $hrStats['today_logins'] = $stmt_logins->fetchColumn();

        // 2. New Registrations (Selected Period)
        $date_filter_sql = "";
        $date_params = [];
        if ($filter === 'today') {
            $date_filter_sql = "AND DATE(created_at) = CURRENT_DATE";
        } elseif ($filter === 'week') {
            $date_filter_sql = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($filter === 'month') {
            $date_filter_sql = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif ($filter === 'custom' && $date_from && $date_to) {
            $date_filter_sql = "AND DATE(created_at) BETWEEN ? AND ?";
            $date_params = [$date_from, $date_to];
        }

        $stmt_new_users = $pdo->prepare("SELECT COUNT(*) FROM users WHERE 1=1 $date_filter_sql");
        $stmt_new_users->execute($date_params);
        $hrStats['new_registrations'] = $stmt_new_users->fetchColumn();

        // 3. Active Personnel Today
        $stmt_active_today = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE DATE(created_at) = CURRENT_DATE");
        $stmt_active_today->execute();
        $hrStats['active_today'] = $stmt_active_today->fetchColumn();

        // 4. Registration Growth (Chart Data)
        $stmt_growth = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        $stmt_growth->execute();
        $growthData = $stmt_growth->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fill gaps in growth data for last 30 days
        $begin = new DateTime('30 days ago');
        $end = new DateTime('tomorrow');
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        foreach ($daterange as $date) {
            $key = $date->format("Y-m-d");
            $registrationGrowth[$key] = $growthData[$key] ?? 0;
        }

        // 5. System Audit Trail (Administrative actions)
        $auditTrail = $logRepo->getAllLogs([
            'limit' => 20,
            'action_type' => '' // Get everything but we'll prioritize management actions in PHP or filter specifically
        ]);

        // 6. Recently Active Personnel
        $stmt_recent_users = $pdo->prepare("
            SELECT u.id, u.full_name, u.profile_picture, u.office_station, MAX(l.created_at) as last_seen 
            FROM users u 
            JOIN activity_logs l ON u.id = l.user_id 
            GROUP BY u.id 
            ORDER BY last_seen DESC 
            LIMIT 10
        ");
        $stmt_recent_users->execute();
        $activePersonnel = $stmt_recent_users->fetchAll(PDO::FETCH_ASSOC);

        // 7. Population by Office
        $popOSDS = 0;
        $popCID = 0;
        $popSGOD = 0;
        $stmt_pop = $pdo->query("SELECT office_station FROM users WHERE is_active = 1");
        while ($u = $stmt_pop->fetch(PDO::FETCH_ASSOC)) {
            $cat = $office_map[strtoupper($u['office_station'])] ?? '';
            if ($cat === 'OSDS')
                $popOSDS++;
            elseif ($cat === 'CID')
                $popCID++;
            elseif ($cat === 'SGOD')
                $popSGOD++;
        }

    } catch (Exception $e) { /* Handle error */
    }
}
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
                    <?php if ($_SESSION['role'] === 'head_hr'): ?>
                        <!-- HR Specific Stats -->
                        <div class="stat-card" style="--accent-color: var(--vibrant-blue);">
                            <div class="stat-icon" style="background: rgba(14, 165, 233, 0.1); color: var(--vibrant-blue);">
                                <i class="bi bi-box-arrow-in-right"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Today's Logins</span>
                                <span class="stat-value"><?php echo number_format($hrStats['today_logins']); ?></span>
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
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">New Registrations</span>
                                <span class="stat-value"><?php echo number_format($hrStats['new_registrations']); ?></span>
                            </div>
                        </div>

                        <div class="stat-card" style="--accent-color: #10b981;">
                            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                <i class="bi bi-person-check-fill"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Active Today</span>
                                <span class="stat-value"><?php echo number_format($hrStats['active_today']); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Standard Admin Stats -->
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
                    <?php endif; ?>
                </div>

                <div class="dashboard-row-middle">
                    <div class="dashboard-card hover-elevate">
                        <div class="card-header card-header-gradient">
                            <h2 class="card-title-white"><i class="bi bi-bar-chart-line card-title-icon-white"></i>
                                <?php echo ($_SESSION['role'] === 'head_hr') ? 'User Registration Growth' : 'Submission Frequency'; ?>
                            </h2>
                        </div>
                        <div class="card-body p-2 h-180">
                            <canvas id="frequencyChart"></canvas>
                        </div>
                    </div>

                    <div class="dashboard-card hover-elevate">
                        <div class="card-header card-header-standard">
                            <h2 class="card-title-standard"><i class="bi bi-building text-gradient"></i>
                                <?php echo ($_SESSION['role'] === 'head_hr') ? 'User Population by Office' : 'Office Activity Distribution'; ?>
                            </h2>
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
                                    <span class="legend-value" id="legendOSDS"><?php echo number_format(($_SESSION['role'] === 'head_hr') ? $popOSDS : $osdsCount); ?></span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-label-box">
                                        <span class="legend-color-dot bg-warning"></span>
                                        <span class="legend-text">CID</span>
                                    </div>
                                    <span class="legend-value" id="legendCID"><?php echo number_format(($_SESSION['role'] === 'head_hr') ? $popCID : $cidCount); ?></span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-label-box">
                                        <span class="legend-color-dot bg-vibrant-blue shadow-vibrant-blue"></span>
                                        <span class="legend-text">SGOD</span>
                                    </div>
                                    <span class="legend-value" id="legendSGOD"><?php echo number_format(($_SESSION['role'] === 'head_hr') ? $popSGOD : $sgodCount); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-row-bottom">
                    <?php if ($_SESSION['role'] === 'head_hr'): ?>
                            <!-- Recently Active Personnel -->
                            <div class="dashboard-card hover-elevate">
                                <div class="card-header card-header-standard">
                                    <h2 class="card-title-standard"><i class="bi bi-people-fill text-gradient"></i> Recently Active Personnel</h2>
                                </div>
                                <div class="card-body p-0 max-h-350 overflow-y-auto">
                                    <div class="activity-feed">
                                        <?php if (empty($activePersonnel)): ?>
                                                <div class="feed-empty-state">No active users recorded.</div>
                                        <?php else: ?>
                                                <?php foreach ($activePersonnel as $person): ?>
                                                        <div class="feed-item">
                                                            <?php if (!empty($person['profile_picture'])): ?>
                                                                    <img src="../<?php echo htmlspecialchars($person['profile_picture']); ?>" class="feed-avatar" alt="">
                                                            <?php else: ?>
                                                                    <div class="feed-avatar-placeholder"><?php echo strtoupper(substr($person['full_name'], 0, 1)); ?></div>
                                                            <?php endif; ?>
                                                            <div class="feed-info">
                                                                <span class="feed-user"><?php echo htmlspecialchars($person['full_name']); ?></span>
                                                                <span class="feed-activity text-muted"><?php echo htmlspecialchars($person['office_station']); ?></span>
                                                            </div>
                                                            <div class="feed-time">
                                                                <?php
                                                                $time = strtotime($person['last_seen']);
                                                                if (date('Y-m-d', $time) === date('Y-m-d'))
                                                                    echo 'Today, ' . date('h:i A', $time);
                                                                else
                                                                    echo date('M d, h:i A', $time);
                                                                ?>
                                                            </div>
                                                        </div>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- System Audit Trail -->
                            <div class="dashboard-card hover-elevate">
                                <div class="card-header card-header-gradient">
                                    <span class="d-flex align-items-center">
                                        <h2 class="card-title-white mb-0"><i class="bi bi-shield-lock card-title-icon-white"></i> System Audit Trail</h2>
                                    </span>
                                    <a href="users.php" class="view-all-btn">Detailed Logs</a>
                                </div>
                                <div class="card-body p-0 max-h-350 overflow-y-auto">
                                    <style>
                                        .audit-item { padding: 12px 16px; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; gap: 12px; transition: background 0.2s; }
                                        .audit-item:hover { background: rgba(15, 76, 117, 0.02); }
                                        .audit-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem; }
                                        .audit-content { flex: 1; min-width: 0; }
                                        .audit-title { font-weight: 700; font-size: 0.82rem; color: var(--text-primary); display: block; }
                                        .audit-meta { font-size: 0.72rem; color: var(--text-muted); display: flex; gap: 8px; margin-top: 2px; }
                                    
                                        .lvl-info { background: #e0f2fe; color: #0369a1; }
                                        .lvl-warn { background: #fef3c7; color: #92400e; }
                                        .lvl-success { background: #dcfce7; color: #166534; }
                                    </style>
                                    <div class="audit-trail">
                                        <?php if (empty($auditTrail)): ?>
                                                <div class="feed-empty-state">No recent system events.</div>
                                        <?php else: ?>
                                                <?php foreach (array_slice($auditTrail, 0, 15) as $log):
                                                    $icon = 'bi-info-circle';
                                                    $lvl = 'lvl-info';
                                                    if (strpos($log['action'], 'Approved') !== false) {
                                                        $icon = 'bi-check-all';
                                                        $lvl = 'lvl-success';
                                                    } elseif (strpos($log['action'], 'Updated') !== false) {
                                                        $icon = 'bi-pencil-square';
                                                        $lvl = 'lvl-warn';
                                                    } elseif (strpos($log['action'], 'Logged') !== false) {
                                                        $icon = 'bi-person-badge';
                                                        $lvl = 'lvl-info';
                                                    } elseif (strpos($log['action'], 'User') !== false) {
                                                        $icon = 'bi-person-gear';
                                                        $lvl = 'lvl-warn';
                                                    }
                                                    ?>
                                                        <div class="audit-item">
                                                            <div class="audit-icon <?php echo $lvl; ?>">
                                                                <i class="bi <?php echo $icon; ?>"></i>
                                                            </div>
                                                            <div class="audit-content">
                                                                <span class="audit-title"><?php echo htmlspecialchars($log['user_name']); ?>: <?php echo htmlspecialchars($log['action']); ?></span>
                                                                <div class="audit-meta">
                                                                    <span><i class="bi bi-clock"></i> <?php echo date('h:i A', strtotime($log['created_at'])); ?></span>
                                                                    <span>•</span>
                                                                    <span><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($log['ip_address']); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                    <?php else: ?>
                            <!-- Existing side-by-side feeds for Admin / Super Admin -->
                            <div class="dashboard-card hover-elevate">
                                <div class="card-header" style="padding: 12px 20px;">
                                    <h2 style="font-size: 0.9rem;"><i class="bi bi-megaphone text-gradient"></i> Recent Activity Submitted</h2>
                                </div>
                                <div class="card-body" style="padding: 0; max-height: 350px; overflow-y: auto;">
                                    <div class="activity-feed">
                                        <?php if (empty($activities)): ?>
                                                <div class="text-center py-4 text-muted" style="font-size: 0.85rem;">No recent activities.</div>
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
                                                        <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>" class="feed-item <?php echo $feed_class; ?>" style="text-decoration: none;">
                                                            <?php if ($act['profile_picture']): ?>
                                                                    <img src="../<?php echo htmlspecialchars($act['profile_picture']); ?>" class="feed-avatar">
                                                            <?php else: ?>
                                                                    <div class="feed-avatar-placeholder">
                                                                        <?php echo strtoupper(substr($act['full_name'], 0, 1)); ?>
                                                                    </div>
                                                            <?php endif; ?>
                                                            <div class="feed-info">
                                                                <span class="feed-user"><?php echo htmlspecialchars($act['full_name']); ?></span>
                                                                <span class="feed-activity text-truncate" style="display: block; max-width: 150px;"><?php echo htmlspecialchars($act['title']); ?></span>
                                                            </div>
                                                            <div class="feed-time">
                                                                <?php echo time_elapsed_string($act['activity_created_at'] ?? $act['created_at']); ?>
                                                            </div>
                                                        </a>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="dashboard-card hover-elevate">
                                <div class="card-header card-header-gradient">
                                    <h2 class="card-title-white"><i class="bi bi-journal-text card-title-icon-white"></i> Recent Activity Logs</h2>
                                    <a href="submissions.php" class="view-all-btn">View All</a>
                                </div>
                                <div class="card-body p-0 max-h-350 overflow-y-auto">
                                    <div class="table-responsive">
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
                                                        <tr><td colspan="4" class="text-center py-4">No recent activities.</td></tr>
                                                <?php else: ?>
                                                        <?php foreach (array_slice($activities, 0, 15) as $act):
                                                            $s_class = 'status-pending';
                                                            $s_label = 'Pending';
                                                            if ($act['approved_sds']) {
                                                                $s_class = 'status-resolved';
                                                                $s_label = 'Approved';
                                                            } elseif ($act['recommending_asds']) {
                                                                $s_class = 'status-in_progress';
                                                                $s_label = 'Recommended';
                                                            } elseif ($act['reviewed_by_supervisor']) {
                                                                $s_class = 'status-accepted';
                                                                $s_label = 'Reviewed';
                                                            }
                                                            ?>
                                                                <tr>
                                                                    <td><?php echo date('M d, Y', strtotime($act['activity_created_at'] ?? $act['created_at'])); ?></td>
                                                                    <td><strong><?php echo htmlspecialchars($act['full_name']); ?></strong><br><small><?php echo htmlspecialchars($act['office_station']); ?></small></td>
                                                                    <td><span class="text-truncate d-block" style="max-width: 180px;"><?php echo htmlspecialchars($act['title']); ?></span></td>
                                                                    <td><span class="status-badge <?php echo $s_class; ?>"><?php echo $s_label; ?></span></td>
                                                                </tr>
                                                        <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
            freqLabels: <?php echo json_encode($_SESSION['role'] === 'head_hr' ? array_keys($registrationGrowth) : $freqLabels); ?>,
            freqValues: <?php echo json_encode($_SESSION['role'] === 'head_hr' ? array_values($registrationGrowth) : $freqValues); ?>,
            osdsCount: <?php echo ($_SESSION['role'] === 'head_hr') ? $popOSDS : $osdsCount; ?>,
            cidCount: <?php echo ($_SESSION['role'] === 'head_hr') ? $popCID : $cidCount; ?>,
            sgodCount: <?php echo ($_SESSION['role'] === 'head_hr') ? $popSGOD : $sgodCount; ?>,
            isHR: <?php echo ($_SESSION['role'] === 'head_hr') ? 'true' : 'false'; ?>
        };
    </script>
    <script src="../js/admin/dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        // Inline script removed and moved to js/admin/dashboard.js
    </script>
</body>

</html>