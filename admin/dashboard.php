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

// Define Office Lists
$osdsOffices = [
    'ADMINISTRATIVE (PERSONEL)',
    'ADMINISTRATIVE (PROPERTY AND SUPPLY)',
    'ADMINISTRATIVE (RECORDS)',
    'ADMINISTRATIVE (CASH)',
    'ADMINISTRATIVE (GENERAL SERVICES)',
    'FINANCE (ACCOUNTING)',
    'FINANCE (BUDGET)',
    'LEGAL',
    'ICT'
];
$sgodOffices = [
    'SCHOOL MANAGEMENT MONITORING & EVALUATION',
    'HUMAN RESOURCES DEVELOPMENT',
    'DISASTER RISK REDUCTION
AND MANAGEMENT',
    'EDUCATION FACILITIES',
    'SCHOOL HEALTH AND NUTRITION',
    'SCHOOL HEALTH AND NUTRITION (DENTAL)',
    'SCHOOL HEALTH AND NUTRITION (MEDICAL)'
];
$cidOffices = [
    'CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)',
    'CURRICULUM IMPLEMENTATION DIVISION
(LEARNING RESOURCES MANAGEMENT)',
    'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)',
    'CURRICULUM
IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)'
];

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
    $office = strtoupper($act['office_station'] ?? '');
    if (in_array($office, $osdsOffices)) {
        $osdsCount++;
    } elseif (in_array($office, $cidOffices)) {
        $cidCount++;
    } elseif (in_array($office, $sgodOffices)) {
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* New Layout Grid System */
        :root {
            --vibrant-blue: #0ea5e9;
            --vibrant-orange: #f97316;
            --vibrant-blue-gradient: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            --vibrant-orange-gradient: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }

        .stat-card {
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            border-top: 3px solid var(--accent-color);
            background: white;
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.3);
            /* Smaller, darker shadow */
        }

        .stat-card:hover {
            border-color: var(--accent-color);
            transform: translateY(-5px);
            box-shadow: 0 6px 18px -2px rgba(0, 0, 0, 0.4);
            /* Darker shadow on hover */
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .dashboard-row-middle {
            display: grid;
            grid-template-columns: 1.8fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .dashboard-row-bottom {
            display: grid;
            grid-template-columns: 1fr 1.8fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .dashboard-row-bottom.full-view {
            grid-template-columns: 1fr;
        }

        @media (max-width: 1200px) {

            .dashboard-row-middle,
            .dashboard-row-bottom {
                grid-template-columns: 1fr;
            }
        }

        /* Generic Dashboard Card with Dark Shadow */
        .dashboard-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.3);
            /* Smaller, darker shadow */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px -2px rgba(0, 0, 0, 0.4);
        }

        /* Recent Activity Submitted Feed */
        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px;
        }

        .feed-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: var(--bg-primary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            border-left: 4px solid #cbd5e1;
            transition: all 0.2s ease;
        }

        .feed-item.osds {
            border-left-color: var(--vibrant-orange);
        }

        .feed-item.sgod {
            border-left-color: var(--vibrant-blue);
        }

        .feed-item.cid {
            border-left-color: #eab308;
        }



        .feed-item:hover {
            transform: translateX(4px);
            border-color: var(--primary-light);
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .feed-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .feed-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .feed-info {
            flex: 1;
            min-width: 0;
        }

        .feed-user {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-primary);
            display: block;
        }

        .feed-activity {
            font-size: 0.75rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .feed-time {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            flex-shrink: 0;
        }

        .pulse-indicator {
            width: 6px;
            height: 6px;
            background: var(--success);
            border-radius: 50%;
            margin-right: 6px;
            box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        /* Custom Modern Dropdown Component */
        .custom-dropdown {
            position: relative;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 0;
        }

        .dropdown-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(15, 76, 117, 0.05);
            padding: 4px 14px;
            border-radius: 99px;
            border: 1px solid rgba(15, 76, 117, 0.1);
            color: var(--primary);
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 150px;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .dropdown-trigger:hover {
            background: rgba(15, 76, 117, 0.08);
            border-color: rgba(15, 76, 117, 0.2);
        }

        .dropdown-trigger.active {
            background: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .dropdown-menu-custom {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: white;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            min-width: 180px;
            overflow: hidden;
            display: none;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dropdown-menu-custom.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .dropdown-item-custom {
            padding: 10px 16px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-item-custom:hover {
            background: rgba(15, 76, 117, 0.05);
            color: var(--primary);
            padding-left: 20px;
        }

        .dropdown-item-custom.active {
            background: var(--primary);
            color: white;
        }

        .dropdown-item-custom i {
            font-size: 1rem;
            opacity: 0.7;
        }
    </style>
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
                <div class="top-bar-right" style="display: flex; gap: 16px; align-items: center;">
                    <div class="current-date-box">
                        <div class="time-section">
                            <span id="real-time-clock"><?php echo date('h:i:s A'); ?></span>
                        </div>
                        <div class="date-section">
                            <i class="bi bi-calendar3"></i>
                            <span><?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>
                    <form method="GET" id="filterForm" style="margin: 0; display: flex; gap: 8px; align-items: center;">
                        <div class="custom-dropdown" id="filterDropdown">
                            <input type="hidden" name="filter" id="filterInput"
                                value="<?php echo htmlspecialchars($filter); ?>">
                            <div class="dropdown-trigger" id="dropdownTrigger">
                                <div>
                                    <i class="bi bi-funnel"
                                        style="color: var(--primary); font-size: 0.9rem; margin-right: 8px;"></i>
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
                                <i class="bi bi-chevron-down" style="font-size: 0.8rem; margin-left: 10px;"></i>
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

                            <div id="customDateInputs"
                                style="display: <?php echo ($filter === 'custom') ? 'flex' : 'none'; ?>; gap: 8px; align-items: center; padding-left: 16px; margin-left: 16px; border-left: 1px solid rgba(15, 76, 117, 0.15);">
                                <div
                                    style="display: flex; align-items: center; gap: 6px; background: white; padding: 0 8px; border-radius: 99px; border: 1px solid rgba(15, 76, 117, 0.12); box-shadow: var(--shadow-sm);">
                                    <input type="date" name="date_from"
                                        value="<?php echo htmlspecialchars($dateFrom); ?>"
                                        class="form-control form-control-sm"
                                        style="border: none; background: transparent; padding: 0; font-size: 0.8rem; height: 22px; color: var(--primary); font-weight: 600; outline: none; width: 110px;" />
                                </div>
                                <span
                                    style="color: var(--primary); font-size: 0.75rem; font-weight: 700; opacity: 0.5;">to</span>
                                <div
                                    style="display: flex; align-items: center; gap: 6px; background: white; padding: 0 8px; border-radius: 99px; border: 1px solid rgba(15, 76, 117, 0.12); box-shadow: var(--shadow-sm);">
                                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                                        class="form-control form-control-sm"
                                        style="border: none; background: transparent; padding: 0; font-size: 0.8rem; height: 22px; color: var(--primary); font-weight: 600; outline: none; width: 110px;" />
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"
                                    style="height: 26px; padding: 0 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; background: var(--primary); border: none; box-shadow: 0 4px 10px rgba(15, 76, 117, 0.2); margin-left: 4px; transition: all 0.2s ease;">
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
                        <div class="card-header"
                            style="padding: 12px 20px; background: linear-gradient(135deg, #0f4c75 0%, #3282b8 100%); border-bottom: none;">
                            <h2 style="font-size: 0.9rem; color: white;"><i class="bi bi-bar-chart-line"
                                    style="color: rgba(255,255,255,0.9); margin-right: 8px;"></i>
                                Submission Frequency</h2>
                        </div>
                        <div class="card-body" style="padding: 12px; height: 180px;">
                            <canvas id="frequencyChart"></canvas>
                        </div>
                    </div>

                    <div class="dashboard-card hover-elevate">
                        <div class="card-header" style="padding: 12px 20px;">
                            <h2 style="font-size: 0.9rem;"><i class="bi bi-building text-gradient"></i> Office
                                Activity Distribution</h2>
                        </div>
                        <div class="card-body"
                            style="padding: 16px 20px; display: flex; align-items: center; gap: 20px;">
                            <div style="width: 120px; height: 120px;">
                                <canvas id="officeChart"></canvas>
                            </div>
                            <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span
                                            style="width: 10px; height: 10px; border-radius: 3px; background: var(--vibrant-orange); box-shadow: 0 2px 4px rgba(249, 115, 22, 0.3);"></span>
                                        <span style="font-size: 0.9rem; color: #334155; font-weight: 700;">OSDS</span>
                                    </div>
                                    <span
                                        style="font-weight: 700; color: #1e293b; font-size: 1rem;"><?php echo number_format($osdsCount); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span
                                            style="width: 8px; height: 8px; border-radius: 50%; background: #eab308;"></span>
                                        <span style="font-size: 0.9rem; color: #334155; font-weight: 600;">CID</span>
                                    </div>
                                    <span
                                        style="font-weight: 700; color: #1e293b; font-size: 1rem;"><?php echo number_format($cidCount); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span
                                            style="width: 10px; height: 10px; border-radius: 3px; background: var(--vibrant-blue); box-shadow: 0 2px 4px rgba(14, 165, 233, 0.3);"></span>
                                        <span style="font-size: 0.9rem; color: #334155; font-weight: 700;">SGOD</span>
                                    </div>
                                    <span
                                        style="font-weight: 700; color: #1e293b; font-size: 1rem;"><?php echo number_format($sgodCount); ?></span>
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
                                            $feed_class = '';
                                            if (in_array($f_office, $osdsOffices))
                                                $feed_class = 'osds';
                                            elseif (in_array($f_office, $cidOffices))
                                                $feed_class = 'cid';
                                            elseif (in_array($f_office, $sgodOffices))
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
                            <div class="card-header"
                                style="padding: 12px 20px; background: linear-gradient(135deg, #0f4c75 0%, #3282b8 100%); border-bottom: none;">
                                <h2 style="font-size: 0.9rem; color: white;"><i class="bi bi-journal-text"
                                        style="color: rgba(255,255,255,0.9); margin-right: 8px;"></i> Recent
                                    Activity Logs</h2>
                                <a href="submissions.php" class="btn btn-sm"
                                    style="padding: 4px 12px; font-size: 0.75rem; background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; font-weight: 600; text-decoration: none;">
                                    View All <i class="bi bi-arrow-right" style="margin-left: 4px;"></i>
                                </a>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                    <table class="data-table">
                                        <thead
                                            style="position: sticky; top: 0; z-index: 20; background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
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
                                                    if (in_array($office, $osdsOffices))
                                                        $row_class = 'row-osds';
                                                    elseif (in_array($office, $cidOffices))
                                                        $row_class = 'row-cid';
                                                    elseif (in_array($office, $sgodOffices))
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
                        <div class="dashboard-card hover-elevate" style="background: rgba(15, 76, 117, 0.02); border-style: dashed; display: flex; align-items: center; justify-content: center; padding: 60px;">
                            <div class="text-center">
                                <i class="bi bi-shield-lock" style="font-size: 3rem; color: var(--text-muted); opacity: 0.2; display: block; margin-bottom: 16px;"></i>
                                <span style="font-weight: 700; color: var(--text-muted); opacity: 0.6;">Management Overview Focused</span>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px; opacity: 0.5;">Use the sidebar to access Activity Logs and User Status.</p>
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
        document.addEventListener('DOMContentLoaded', function () {
            // Custom Dropdown Logic
            const dropdown = document.getElementById('filterDropdown');
            const trigger = document.getElementById('dropdownTrigger');
            const menu = document.getElementById('dropdownMenu');
            const input = document.getElementById('filterInput');
            const text = document.getElementById('selectedFilterText');
            const items = document.querySelectorAll('.dropdown-item-custom');
            const customDateInputs = document.getElementById('customDateInputs');
            const filterForm = document.getElementById('filterForm');

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('show');
                trigger.classList.toggle('active');
            });

            items.forEach(item => {
                item.addEventListener('click', () => {
                    const value = item.getAttribute('data-value');

                    // Update value and text
                    input.value = value;
                    text.textContent = item.textContent.trim();

                    // Update active state
                    items.forEach(i => i.classList.remove('active'));
                    item.classList.add('active');

                    // Handle Custom Range
                    if (value === 'custom') {
                        customDateInputs.style.display = 'flex';
                        menu.classList.remove('show');
                        trigger.classList.remove('active');
                    } else {
                        customDateInputs.style.display = 'none';
                        // Clear date inputs when switching away from custom
                        const dateFromInput = filterForm.querySelector('input[name="date_from"]');
                        const dateToInput = filterForm.querySelector('input[name="date_to"]');
                        if (dateFromInput) dateFromInput.value = '';
                        if (dateToInput) dateToInput.value = '';

                        // Submit form automatically
                        filterForm.submit();
                    }
                });
            });

            // Close when clicking outside
            document.addEventListener('click', (e) => {
                if (!dropdown.contains(e.target)) {
                    menu.classList.remove('show');
                    trigger.classList.remove('active');
                }
            });

            // Submission Frequency Chart (Line)
            const freqCtx = document.getElementById('frequencyChart').getContext('2d');
            new Chart(freqCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($freqLabels); ?>,
                    datasets: [{
                        label: 'Submissions',
                        data: <?php echo json_encode($freqValues); ?>,
                        borderColor: '#3282b8',
                        background: 'rgba(50, 130, 184, 0.1)',
                        backgroundColor: (context) => {
                            const chart = context.chart;
                            const { ctx, chartArea } = chart;
                            if (!chartArea) return null;
                            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                            gradient.addColorStop(0, 'rgba(50, 130, 184, 0)');
                            gradient.addColorStop(1, 'rgba(50, 130, 184, 0.15)');
                            return gradient;
                        },
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'white',
                        pointBorderColor: '#3282b8',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            padding: 10,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#64748b' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            ticks: { color: '#64748b', font: { size: 10 } },
                            grid: { display: false }
                        }
                    }
                }
            });

            // Office Distribution Chart (Doughnut)
            const officeCtx = document.getElementById('officeChart').getContext('2d');
            new Chart(officeCtx, {
                type: 'doughnut',
                data: {
                    labels: ['OSDS', 'CID', 'SGOD'],
                    datasets: [{
                        data: [<?php echo $osdsCount; ?>, <?php echo $cidCount; ?>, <?php echo $sgodCount; ?>],
                        backgroundColor: ['#f97316', '#eab308', '#0ea5e9'],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            padding: 10,
                            displayColors: true
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>