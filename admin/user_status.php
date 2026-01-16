<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head')) {
    header("Location: ../index.php");
    exit;
}

// 1. Fetch Users with Expanded Metrics
$sql_users = "SELECT 
                u.id, u.username, u.full_name, u.office_station, u.role, u.position, u.profile_picture, u.created_at as joined_at,
                (SELECT id FROM ld_activities WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_activity_id,
                (SELECT title FROM ld_activities WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_activity_title,
                (SELECT MAX(created_at) FROM ld_activities WHERE user_id = u.id) as latest_submission,
                (SELECT created_at FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_action_time
              FROM users u
              WHERE u.role != 'admin' AND u.role != 'super_admin' 
              ORDER BY latest_submission DESC";
$stmt_users = $pdo->query($sql_users);
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Submission Statistics
$sql_stats = "SELECT 
                user_id,
                COUNT(*) as total,
                SUM(CASE WHEN approved_sds = 1 THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN reviewed_by_supervisor = 0 THEN 1 ELSE 0 END) as pending
              FROM ld_activities
              GROUP BY user_id";
$stmt_stats = $pdo->query($sql_stats);
$stats = [];
while ($row = $stmt_stats->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['user_id']] = $row;
}

// Helper to format relative time
function time_elapsed_string($datetime, $full = false)
{
    if (!$datetime)
        return 'Never';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'min',
        's' => 'sec',
    );
    foreach ($string as $k => &$v) {
        if ($k === 'd') {
            if ($weeks) {
                $v = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ($days ? ', ' . $days . ' day' . ($days > 1 ? 's' : '') : '');
            } elseif ($diff->d) {
                $v = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        } elseif ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full)
        $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'Just now';
}

function getStatusColor($last_action)
{
    if (!$last_action)
        return 'gray';
    $diff = time() - strtotime($last_action);
    if ($diff < 300)
        return 'green'; // 5 mins
    if ($diff < 3600)
        return 'orange'; // 1 hour
    if ($diff < 86400)
        return 'blue'; // 24 hours
    return 'gray';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Status Monitor - Admin</title>
    <?php require '../includes/admin_head.php'; ?>
    <style>
        .user-card.interactive-card {
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .user-card.interactive-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        /* Detail Modal Styles */
        .details-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .details-modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        /* Condensed Main UI */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 12px;
        }

        .user-card {
            padding: 12px 16px;
        }

        .user-card .card-header {
            padding-bottom: 8px;
            margin-bottom: 8px;
            gap: 10px;
        }

        .user-card .avatar, 
        .user-card .avatar-placeholder {
            width: 44px;
            height: 44px;
            font-size: 1.1rem;
        }

        .user-card .name {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .user-card .position {
            font-size: 0.72rem;
        }

        .stats-row {
            padding: 8px 0;
            margin-bottom: 8px;
            gap: 8px;
        }

        .stat-item .stat-val {
            font-size: 1.1rem;
        }

        .stat-item .stat-label {
            font-size: 0.6rem;
        }

        .progress-section {
            padding: 8px 0;
        }

        .progress-label {
            font-size: 0.7rem;
            margin-bottom: 4px;
        }

        .progress-bar-bg {
            height: 6px;
        }

        .meta-info {
            margin-top: 8px;
            padding-top: 8px;
            font-size: 0.7rem;
        }

        .user-card .last-seen-box {
            padding: 4px 8px;
        }

        .details-modal {
            background: #f1f5f9;
            width: 95%;
            max-width: 850px;
            max-height: 90vh;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 40px 80px -15px rgba(15, 23, 42, 0.3);
            transform: scale(0.95) translateY(20px);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .details-modal-overlay.active .details-modal {
            transform: scale(1) translateY(0);
        }

        .modal-header-premium {
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            padding: 16px 24px;
            color: white;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-close-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }

        .modal-close-btn:hover {
            background: #ef4444;
            border-color: #ef4444;
            transform: rotate(90deg);
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .header-avatar {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .header-text h2 {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0 0 1px;
            letter-spacing: -0.5px;
            color: #ffffff;
        }

        .header-text p {
            opacity: 0.8;
            margin: 0;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .modal-scroll-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f1f5f9;
        }

        .detail-section-title {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-left: 4px;
        }

        .detail-section-title i {
            color: var(--primary);
            font-size: 1rem;
        }

        .timeline-selector {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
            gap: 4px;
        }

        .timeline-btn {
            border: none;
            background: none;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }

        .timeline-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .timeline-btn:hover:not(.active) {
            background: #e2e8f0;
        }

        .detail-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .detail-stat-card {
            background: white;
            padding: 16px 12px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .detail-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .detail-stat-val {
            display: block;
            font-size: 1.35rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .detail-stat-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .activity-frequency-hub {
            background: white;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .frequency-chart {
            position: relative;
            height: 140px;
            width: 100%;
            margin-bottom: 12px;
        }

        .chart-svg {
            width: 100%;
            height: 120px;
        }

        .chart-path-line {
            fill: none;
            stroke: var(--primary);
            stroke-width: 3.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            filter: drop-shadow(0 4px 6px rgba(15, 76, 117, 0.2));
        }

        .chart-path-area {
            fill: url(#areaGradient);
            stroke: none;
        }

        .chart-labels-container {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }

        .frequency-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            text-align: center;
            flex: 1;
        }

        .chart-dot {
            fill: white;
            stroke: var(--primary);
            stroke-width: 2.5;
            r: 5;
            transition: r 0.2s;
        }

        .chart-dot:hover {
            r: 7;
            cursor: pointer;
        }

        .cert-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 24px;
            max-height: 240px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .cert-list::-webkit-scrollbar,
        .submission-grid-modal::-webkit-scrollbar {
            width: 5px;
        }

        .cert-list::-webkit-scrollbar-thumb,
        .submission-grid-modal::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .cert-list::-webkit-scrollbar-track,
        .submission-grid-modal::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .cert-card-mini {
            background: white;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }

        .cert-card-mini:hover {
            border-color: var(--primary);
            background: #f0f9ff;
            transform: translateX(4px);
        }

        .cert-icon {
            width: 40px;
            height: 40px;
            background: #fff1f2;
            color: #ef4444;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .cert-info-mini h4 {
            font-size: 0.85rem;
            font-weight: 700;
            margin: 0;
            color: #1e293b;
        }

        .cert-info-mini p {
            font-size: 0.7rem;
            color: #64748b;
            margin: 0;
        }

        /* Submission List Modal Styles */
        .submission-grid-modal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 24px;
            max-height: 320px;
            overflow-y: auto;
            padding-right: 8px;
        }

        @media (max-width: 640px) {
            .submission-grid-modal {
                grid-template-columns: 1fr;
            }
        }

        .submission-card-mini {
            background: white;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }

        .submission-card-mini:hover {
            border-color: var(--primary);
            background: #f0f9ff;
            transform: translateX(4px);
        }

        .submission-icon {
            width: 40px;
            height: 40px;
            background: #f0f9ff;
            color: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .submission-info-mini {
            flex: 1;
            min-width: 0;
        }

        .submission-info-mini h4 {
            font-size: 0.85rem;
            font-weight: 700;
            margin: 0;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .submission-info-mini p {
            font-size: 0.7rem;
            color: #64748b;
            margin: 0;
        }

        .submission-status-tag {
            font-size: 0.6rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 4px;
            text-transform: uppercase;
            margin-top: 3px;
            display: inline-block;
        }

        .status-tag-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-tag-reviewed {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-tag-recommending {
            background: #e0f2fe;
            color: #0284c7;
        }

        .status-tag-approved {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .log-timeline {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .log-entry {
            display: flex;
            gap: 16px;
            position: relative;
        }

        .log-entry::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 20px;
            width: 2px;
            height: calc(100% + 16px);
            background: #e2e8f0;
        }

        .log-entry:last-child::before {
            display: none;
        }

        .log-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #94a3b8;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e2e8f0;
            z-index: 1;
        }

        .log-content h5 {
            font-size: 0.85rem;
            font-weight: 700;
            margin: 0;
        }

        .log-content span {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .no-data-msg {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-size: 0.9rem;
            font-style: italic;
        }

        /* High-Contrast Search Bar Redesign */
        .search-container-premium {
            background: white;
            padding: 8px 12px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .search-label-premium {
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #94a3b8;
            text-transform: uppercase;
            white-space: nowrap;
            padding-left: 8px;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            background: var(--primary);
            padding: 4px;
            border-radius: 50px;
            display: flex;
            align-items: center;
        }

        .search-icon-premium {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 0.95rem;
            pointer-events: none;
            z-index: 2;
        }

        .search-input-premium {
            width: 100%;
            padding: 8px 16px 8px 42px;
            border: none;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #1e293b;
            background: white;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-input-premium:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }

        /* Extreme UI Condensation */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
        }

        .user-card {
            padding: 10px 12px;
        }

        .user-card .avatar, 
        .user-card .avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            font-size: 1rem;
        }

        .user-card .name {
            font-size: 0.85rem;
            font-weight: 700;
        }

        .user-card .position {
            font-size: 0.65rem;
            color: #64748b;
        }

        .user-card .last-seen-box {
            padding: 4px 6px;
            border-radius: 6px;
        }

        .user-card .latest-entry-label {
            font-size: 0.55rem;
        }

        .user-card .latest-entry-title {
            font-size: 0.75rem;
        }

        .user-card .time-ago {
            font-size: 0.7rem;
        }

        .stats-row {
            margin-bottom: 6px;
            gap: 4px;
        }

        .stat-item .stat-val {
            font-size: 1rem;
        }

        .stat-item .stat-label {
            font-size: 0.55rem;
        }

        .progress-label {
            font-size: 0.65rem;
        }

        .meta-info {
            font-size: 0.65rem;
            margin-top: 6px;
            padding-top: 6px;
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
                        <h1 class="page-title">User Network Status</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <i class="bi bi-calendar-check"></i>
                        <span><?php echo date('l, F d, Y'); ?></span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <!-- Premium Search Bar -->
                <div class="search-container-premium">
                    <span class="search-label-premium">Live Personnel Search</span>
                    <div class="search-input-wrapper">
                        <input type="text" id="userSearch" class="search-input-premium"
                            placeholder="Type to filter by name, office, or position...">
                        <i class="bi bi-search search-icon-premium"></i>
                    </div>
                </div>

                <div class="user-grid">
                    <?php foreach ($users as $u): ?>
                        <?php
                        $u_stats = isset($stats[$u['id']]) ? $stats[$u['id']] : ['total' => 0, 'approved' => 0, 'pending' => 0];
                        $approved_pct = $u_stats['total'] > 0 ? round(($u_stats['approved'] / $u_stats['total']) * 100) : 0;
                        $pending_pct = $u_stats['total'] > 0 ? round(($u_stats['pending'] / $u_stats['total']) * 100) : 0;
                        $status_color = getStatusColor($u['last_action_time']);
                        ?>
                        <div class="user-card hover-elevate interactive-card"
                            onclick="showUserDetails(<?php echo $u['id']; ?>)"
                            data-name="<?php echo strtolower($u['full_name']); ?>"
                            data-office="<?php echo strtolower($u['office_station']); ?>"
                            data-position="<?php echo strtolower($u['position']); ?>">

                            <div class="card-header">
                                <?php if ($u['profile_picture']): ?>
                                    <img src="../<?php echo htmlspecialchars($u['profile_picture']); ?>" alt="" class="avatar">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="user-details">
                                    <span class="name"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                    <span class="position text-truncate"
                                        style="max-width: 180px;"><?php echo htmlspecialchars($u['position'] ?: 'Educational Personnel'); ?></span>
                                </div>
                                <div class="last-seen" style="margin-left: auto;">
                                    <a href="../pages/view_activity.php?id=<?php echo $u['latest_activity_id']; ?>"
                                        style="text-decoration:none; color:inherit; display:block;">
                                        <div
                                            style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 10px; border-radius: 8px; transition: all 0.2s; box-shadow: 0 0 8px rgba(59,130,246,0.2);">
                                            <?php if ($u['latest_activity_title']): ?>
                                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                                    <i class="bi bi-activity" style="font-size: 0.75rem; color: #3b82f6;"></i>
                                                    <span
                                                        style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px;">Latest
                                                        Entry</span>
                                                </div>
                                                <div style="font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 4px; line-height: 1.3;"
                                                    class="text-truncate" style="max-width: 100%;"
                                                    title="<?php echo htmlspecialchars($u['latest_activity_title']); ?>">
                                                    <?php echo htmlspecialchars($u['latest_activity_title']); ?>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <span
                                                        style="width: 6px; height: 6px; background-color: #22c55e; border-radius: 50%; box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);"></span>
                                                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                                                        <?php echo time_elapsed_string($u['latest_submission']); ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 0;">
                                                    <i class="bi bi-pause-circle"
                                                        style="font-size: 0.9rem; color: #cbd5e1;"></i>
                                                    <div style="display: flex; flex-direction: column;">
                                                        <span
                                                            style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px;">Status</span>
                                                        <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">No
                                                            recent activity</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div class="stats-row">
                                <div class="stat-item">
                                    <span class="stat-val"><?php echo $u_stats['total']; ?></span>
                                    <span class="stat-label">Total</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-val"
                                        style="color: var(--success);"><?php echo $u_stats['approved']; ?></span>
                                    <span class="stat-label">Approved</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-val" style="color: #f97316;"><?php echo $u_stats['pending']; ?></span>
                                    <span class="stat-label">Pending</span>
                                </div>
                            </div>

                            <div class="progress-section">
                                <div class="progress-label">
                                    <span>Approval Completion</span>
                                    <span
                                        style="font-weight: 700; color: var(--primary);"><?php echo $approved_pct; ?>%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill green" style="width: <?php echo $approved_pct; ?>%"></div>
                                    <div class="progress-bar-fill orange" style="width: <?php echo $pending_pct; ?>%"></div>
                                </div>
                            </div>

                            <div class="meta-info">
                                <span title="Primary Office"><i class="bi bi-building me-1"></i>
                                    <?php echo htmlspecialchars($u['office_station']); ?></span>
                                <span title="Registration Date"><i class="bi bi-person-check me-1"></i>
                                    <?php echo date('M Y', strtotime($u['joined_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
            </footer>
        </div>
    </div>

    <!-- Personnel Detail Modal -->
    <div id="userDetailsModal" class="details-modal-overlay">
        <div class="details-modal">
            <header class="modal-header-premium">
                <div class="header-content">
                    <div id="modalAvatar" class="header-avatar"></div>
                    <div class="header-text">
                        <h2 id="modalName">---</h2>
                        <p id="modalPosition">---</p>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="closeModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <div class="modal-scroll-area">
                <div class="detail-section-title">
                    <span><i class="bi bi-graph-up-arrow"></i> Performance Metrics</span>
                </div>
                <div class="detail-stats-grid">
                    <div class="detail-stat-card">
                        <span id="statTotal" class="detail-stat-val">0</span>
                        <span class="detail-stat-label">Submissions</span>
                    </div>
                    <div class="detail-stat-card">
                        <span id="statApproved" class="detail-stat-val" style="color: var(--success);">0</span>
                        <span class="detail-stat-label">Approved</span>
                    </div>
                    <div class="detail-stat-card">
                        <span id="statPending" class="detail-stat-val" style="color: #f97316;">0</span>
                        <span class="detail-stat-label">Pending</span>
                    </div>
                    <div class="detail-stat-card">
                        <span id="statRate" class="detail-stat-val" style="color: var(--primary);">0%</span>
                        <span class="detail-stat-label">Success Rate</span>
                    </div>
                </div>

                <div class="detail-section-title">
                    <span><i class="bi bi-bar-chart"></i> Submission Frequency</span>
                    <div class="timeline-selector">
                        <button class="timeline-btn active" onclick="fetchTimeline('week')" id="btn-week">Week</button>
                        <button class="timeline-btn" onclick="fetchTimeline('month')" id="btn-month">Month</button>
                        <button class="timeline-btn" onclick="fetchTimeline('year')" id="btn-year">Year</button>
                    </div>
                </div>
                <div class="activity-frequency-hub">
                    <div id="frequencyChart" class="frequency-chart">
                        <svg id="chartSvg" class="chart-svg" viewBox="0 0 1000 120" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="areaGradient" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.3" />
                                    <stop offset="100%" stop-color="var(--primary)" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <path id="chartArea" class="chart-path-area" d="" />
                            <path id="chartLine" class="chart-path-line" d="" />
                            <g id="chartDots"></g>
                        </svg>
                        <div id="chartLabels" class="chart-labels-container"></div>
                        <div id="chartNoData" class="no-data-msg"
                            style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%;">
                        </div>
                    </div>
                </div>

                <div class="detail-section-title">
                    <span><i class="bi bi-file-earmark-text"></i> Activity Submissions</span>
                </div>
                <div id="modalActivityList" class="submission-grid-modal">
                    <!-- Submissions injected by JS -->
                </div>

                <div class="detail-section-title">
                    <span><i class="bi bi-patch-check"></i> Certification History</span>
                </div>
                <div id="modalCertList" class="cert-list">
                    <!-- Certs injected by JS -->
                </div>

                <div class="detail-section-title">
                    <span><i class="bi bi-clock-history"></i> Recent Engagement Logs</span>
                </div>
                <div id="modalLogTimeline" class="log-timeline">
                    <!-- Logs injected by JS -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = null;
        let currentTimeframe = 'week';

        function showUserDetails(userId) {
            currentUserId = userId;
            currentTimeframe = 'week'; // Reset to default
            const modal = document.getElementById('userDetailsModal');

            // Show loading state or clear previous
            document.getElementById('modalName').textContent = 'Loading...';
            document.getElementById('modalPosition').textContent = 'Please wait...';
            document.getElementById('modalCertList').innerHTML = '';
            document.getElementById('modalActivityList').innerHTML = '';
            document.getElementById('modalLogTimeline').innerHTML = '';

            // Reset Chart UI
            document.getElementById('chartSvg').style.display = 'none';
            document.getElementById('chartLabels').innerHTML = '';
            document.getElementById('chartDots').innerHTML = '';
            const noData = document.getElementById('chartNoData');
            noData.style.display = 'block';
            noData.textContent = 'Loading chart...';

            // Reset buttons
            document.querySelectorAll('.timeline-btn').forEach(btn => btn.classList.remove('active'));
            const weekBtn = document.getElementById('btn-week');
            if (weekBtn) weekBtn.classList.add('active');

            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);

            fetchData();
        }

        function fetchTimeline(timeline) {
            currentTimeframe = timeline;

            // Update UI
            document.querySelectorAll('.timeline-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`btn-${timeline}`).classList.add('active');

            // Show loading on chart
            document.getElementById('chartSvg').style.display = 'none';
            document.getElementById('chartLabels').innerHTML = '';
            const noData = document.getElementById('chartNoData');
            noData.style.display = 'block';
            noData.textContent = 'Loading chart...';

            fetchData(true); // partial fetch
        }

        function fetchData(partial = false) {
            if (!currentUserId) return;

            fetch(`ajax/get_user_details.php?user_id=${currentUserId}&timeline=${currentTimeframe}`)
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Server response:', text);
                        throw new Error('Invalid server response: ' + text.substring(0, 100));
                    }
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    if (!partial) {
                        // Populate User Info
                        document.getElementById('modalName').textContent = data.user.full_name;
                        document.getElementById('modalPosition').textContent = data.user.position || 'Educational Personnel';

                        const avatarDiv = document.getElementById('modalAvatar');
                        if (data.user.profile_picture) {
                            avatarDiv.innerHTML = `<img src="../${data.user.profile_picture}" style="width:100%; height:100%; border-radius:inherit; object-fit:cover;">`;
                        } else {
                            avatarDiv.textContent = data.user.full_name.charAt(0).toUpperCase();
                            avatarDiv.innerHTML = data.user.full_name.charAt(0).toUpperCase();
                        }

                        // Populate Stats
                        document.getElementById('statTotal').textContent = data.stats.total;
                        document.getElementById('statApproved').textContent = data.stats.approved;
                        document.getElementById('statPending').textContent = data.stats.pending;
                        document.getElementById('statRate').textContent = data.stats.completion_rate + '%';

                        // Populate Certificates
                        const certList = document.getElementById('modalCertList');
                        if (data.certificates.length > 0) {
                            data.certificates.forEach(c => {
                                certList.innerHTML += `
                                    <a href="../${c.certificate_path}" target="_blank" class="cert-card-mini">
                                        <div class="cert-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                                        <div class="cert-info-mini">
                                            <h4>${c.title}</h4>
                                            <p>${new Date(c.date_attended).toLocaleDateString()}</p>
                                        </div>
                                    </a>
                                `;
                            });
                        } else {
                            certList.innerHTML = '<div class="no-data-msg" style="grid-column: 1/-1;">No certificates uploaded yet.</div>';
                        }

                        // Populate Submissions
                        const submissionList = document.getElementById('modalActivityList');
                        if (data.submissions && data.submissions.length > 0) {
                            data.submissions.forEach(s => {
                                let statusTag = '';
                                if (s.approved_sds == 1) statusTag = '<span class="submission-status-tag status-tag-approved">Approved</span>';
                                else if (s.recommending_asds == 1) statusTag = '<span class="submission-status-tag status-tag-recommending">Recommending</span>';
                                else if (s.reviewed_by_supervisor == 1) statusTag = '<span class="submission-status-tag status-tag-reviewed">Reviewed</span>';
                                else statusTag = '<span class="submission-status-tag status-tag-pending">Pending</span>';

                                submissionList.innerHTML += `
                                    <a href="../pages/view_activity.php?id=${s.id}" class="submission-card-mini">
                                        <div class="submission-icon"><i class="bi bi-file-earmark-text"></i></div>
                                        <div class="submission-info-mini">
                                            <h4>${s.title}</h4>
                                            <p>${s.type_ld}</p>
                                            ${statusTag}
                                        </div>
                                    </a>
                                `;
                            });
                        } else {
                            submissionList.innerHTML = '<div class="no-data-msg" style="grid-column: 1/-1;">No submissions found.</div>';
                        }

                        // Populate Logs
                        const timeline = document.getElementById('modalLogTimeline');
                        if (data.logs.length > 0) {
                            data.logs.forEach(l => {
                                timeline.innerHTML += `
                                    <div class="log-entry">
                                        <div class="log-dot"></div>
                                        <div class="log-content">
                                            <h5>${l.action}</h5>
                                            <span>${new Date(l.created_at).toLocaleString()}</span>
                                        </div>
                                    </div>
                                `;
                            });
                        } else {
                            timeline.innerHTML = '<div class="no-data-msg">No logs available.</div>';
                        }
                    }

                    // Always Populate Chart
                    const chartSvg = document.getElementById('chartSvg');
                    const chartLabels = document.getElementById('chartLabels');
                    const chartLine = document.getElementById('chartLine');
                    const chartArea = document.getElementById('chartArea');
                    const chartDots = document.getElementById('chartDots');
                    const chartNoData = document.getElementById('chartNoData');

                    chartLabels.innerHTML = '';
                    chartDots.innerHTML = '';
                    chartLine.setAttribute('d', '');
                    chartArea.setAttribute('d', '');
                    chartNoData.style.display = 'none';
                    chartSvg.style.display = 'block';

                    if (data.activity_data && data.activity_data.length > 0) {
                        const items = data.activity_data;
                        const maxCount = Math.max(...items.map(m => m.count), 1);
                        const points = [];
                        const width = 1000;
                        const height = 120;

                        items.forEach((m, i) => {
                            const x = items.length > 1 ? (i / (items.length - 1)) * width : width / 2;
                            const y = height - (m.count / maxCount) * (height - 20) - 10;
                            points.push({ x, y, label: m.label, count: m.count });

                            chartLabels.innerHTML += `<span class="frequency-label">${m.label}</span>`;
                            chartDots.innerHTML += `<circle cx="${x}" cy="${y}" class="chart-dot" title="${m.count} submissions"><title>${m.label}: ${m.count} submissions</title></circle>`;
                        });

                        if (points.length > 1) {
                            // Flowing Line Calculation (Cubic Bezier)
                            let d = `M ${points[0].x},${points[0].y}`;
                            for (let i = 0; i < points.length - 1; i++) {
                                const p0 = points[i];
                                const p1 = points[i + 1];
                                const cp1x = p0.x + (p1.x - p0.x) / 2;
                                d += ` C ${cp1x},${p0.y} ${cp1x},${p1.y} ${p1.x},${p1.y}`;
                            }
                            chartLine.setAttribute('d', d);

                            // Area path
                            const areaD = d + ` L ${points[points.length - 1].x},${height} L ${points[0].x},${height} Z`;
                            chartArea.setAttribute('d', areaD);
                        } else if (points.length === 1) {
                            const p = points[0];
                            chartLine.setAttribute('d', `M 0,${p.y} L ${width},${p.y}`);
                            chartArea.setAttribute('d', `M 0,${p.y} L ${width},${p.y} L ${width},${height} L 0,${height} Z`);
                        }
                    } else {
                        chartSvg.style.display = 'none';
                        chartNoData.textContent = `No activity recorded for this ${currentTimeframe}ly view.`;
                        chartNoData.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    alert(err.message);
                    closeModal();
                });
        }

        function closeModal() {
            const modal = document.getElementById('userDetailsModal');
            modal.classList.remove('active');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        window.onclick = function (event) {
            const modal = document.getElementById('userDetailsModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Real-time Search Logic
            const searchInput = document.getElementById('userSearch');
            const userCards = document.querySelectorAll('.user-card');

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const term = this.value.toLowerCase();
                    userCards.forEach(card => {
                        const name = card.dataset.name;
                        const office = card.dataset.office;
                        const position = card.dataset.position;

                        if (name.includes(term) || office.includes(term) || position.includes(term)) {
                            card.style.display = 'block';
                            card.style.opacity = '1';
                        } else {
                            card.style.display = 'none';
                            card.style.opacity = '0';
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>