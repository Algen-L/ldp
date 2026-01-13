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
                (SELECT created_at FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_action_time,
                (SELECT action FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_action_name,
                (SELECT ip_address FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_ip
              FROM users u
              WHERE u.role != 'admin' AND u.role != 'super_admin' 
              ORDER BY last_action_time DESC";
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
    <?php require 'includes/admin_head.php'; ?>
</head>

<body>
    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle" id="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="text-muted">Admin Panel</span>
                        <i class="bi bi-chevron-right separator"></i>
                        <h1 class="page-title">User Engagement Monitor</h1>
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
                <!-- Advanced Search/Filter Section -->
                <div class="filter-bar">
                    <div class="filter-form" style="display: block;">
                        <div class="filter-group" style="width: 100%;">
                            <label>Live Personnel Search</label>
                            <div style="position: relative;">
                                <i class="bi bi-search"
                                    style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem;"></i>
                                <input type="text" id="userSearch" class="form-control"
                                    placeholder="Filter by name, office, or position..."
                                    style="padding-left: 48px; height: 50px; font-size: 1rem; width: 100%;">
                            </div>
                        </div>
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
                        <div class="user-card hover-elevate" data-name="<?php echo strtolower($u['full_name']); ?>"
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
                                    <div class="last-seen">
                                        <span class="status-dot <?php echo $status_color; ?>"></span>
                                        <span
                                            style="font-weight: 600; font-size: 0.75rem; color: var(--text-secondary);"><?php echo time_elapsed_string($u['last_action_time']); ?></span>
                                    </div>
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
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Live Engagement
                        Metrics.</span></p>
            </footer>
        </div>
    </div>

    <script>
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