<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ../index.php");
    exit;
}

// 1. Fetch Users with Expanded Metrics
// We select the LAST action performed by the user to show accurate "Last Seen"
$sql_users = "SELECT 
                u.id, u.username, u.full_name, u.office_station, u.role, u.position, u.profile_picture, u.created_at as joined_at,
                (SELECT created_at FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_action_time,
                (SELECT action FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_action_name,
                (SELECT ip_address FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_ip
              FROM users u
              WHERE u.role != 'admin' AND u.role != 'super_admin' 
              ORDER BY last_action_time DESC";
// Ordering by activity keeps active users at top
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

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'min',
        's' => 'sec',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(226, 232, 240, 0.8);
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            --accent-green: #22c55e;
            --accent-orange: #f97316;
            --accent-blue: #3b82f6;
            --accent-gray: #64748b;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
        }

        .passbook-container {
            animation: fadeIn 0.5s ease-out;
            max-width: 98%;
            width: 1400px;
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
            color: #1e293b;
            border-left: 5px solid var(--accent-orange);
            padding-left: 15px;
        }

        .header p {
            color: #64748b;
            font-weight: 400;
        }

        /* Search Bar */
        .search-box {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-top: 20px;
            display: flex;
            align-items: center;
            border: 1px solid var(--glass-border);
        }

        .search-input {
            border: none;
            outline: none;
            font-size: 1rem;
            width: 100%;
            margin-left: 10px;
            color: #334155;
        }

        /* Card Grid Layout */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .user-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }

        .avatar {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: #f1f5f9;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .avatar-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #64748b;
            flex-shrink: 0;
            border: 2px solid white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .user-details {
            flex-grow: 1;
        }

        .name {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.1rem;
            margin-bottom: 2px;
            display: block;
        }

        .position {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            display: block;
            margin-bottom: 6px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .status-green {
            background: #22c55e;
            box-shadow: 0 0 0 3px #dcfce7;
        }

        .status-orange {
            background: #f97316;
            box-shadow: 0 0 0 3px #ffedd5;
        }

        .status-blue {
            background: #3b82f6;
            box-shadow: 0 0 0 3px #dbeafe;
        }

        .status-gray {
            background: #94a3b8;
            box-shadow: 0 0 0 3px #f1f5f9;
        }

        .last-seen {
            font-size: 0.75rem;
            color: #64748b;
            background: #f8fafc;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px 15px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-val {
            font-weight: 700;
            font-size: 1rem;
            color: #0f172a;
            display: block;
        }

        .stat-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .progress-section {
            margin-top: 15px;
        }

        .progress-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }

        .progress-bar-bg {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
        }

        .progress-bar-fill {
            height: 100%;
            transition: width 0.5s ease;
        }

        .fill-green {
            background: #22c55e;
        }

        .fill-orange {
            background: #f97316;
        }

        .meta-info {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .office-tag {
            background: #f0f9ff;
            color: #0369a1;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="passbook-container">
                <div class="header">
                    <h1>User Insights</h1>
                    <p>Detailed activity tracking and performance metrics</p>
                </div>

                <div class="search-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="userSearch" class="search-input"
                        placeholder="Search by name, office, or username..." onkeyup="filterUsers()">
                </div>

                <div class="user-grid" id="userGrid">
                    <?php foreach ($users as $u):
                        $s = isset($stats[$u['id']]) ? $stats[$u['id']] : ['total' => 0, 'pending' => 0, 'approved' => 0];
                        $profile_pic = !empty($u['profile_picture']) ? '../uploads/profile_pics/' . htmlspecialchars($u['profile_picture']) : null;

                        // Calculate percentages
                        $total = $s['total'] > 0 ? $s['total'] : 1;
                        $width_approved = ($s['approved'] / $total) * 100;
                        $width_pending = ($s['pending'] / $total) * 100;

                        $status_color = getStatusColor($u['last_action_time']);
                        $last_seen_text = time_elapsed_string($u['last_action_time']);
                        ?>
                        <div class="user-card"
                            data-search="<?php echo strtolower($u['full_name'] . ' ' . $u['office_station'] . ' ' . $u['username']); ?>">
                            <div class="card-header">
                                <?php if ($profile_pic && file_exists($profile_pic)): ?>
                                    <img src="<?php echo $profile_pic; ?>" alt="AV" class="avatar">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="user-details">
                                    <span class="name"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                    <span class="position"><?php echo htmlspecialchars($u['office_station']); ?></span>

                                    <div class="last-seen">
                                        <div class="status-dot status-<?php echo $status_color; ?>"></div>
                                        <?php echo $u['last_action_name'] ? htmlspecialchars($u['last_action_name']) : 'Joined'; ?>
                                        • <?php echo $last_seen_text; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="stats-row">
                                <div class="stat-item">
                                    <span class="stat-val"><?php echo $s['total']; ?></span>
                                    <span class="stat-label">Entries</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-val" style="color: #f97316;"><?php echo $s['pending']; ?></span>
                                    <span class="stat-label">Pending</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-val" style="color: #22c55e;"><?php echo $s['approved']; ?></span>
                                    <span class="stat-label">Approved</span>
                                </div>
                            </div>

                            <div class="progress-section">
                                <div class="progress-label">
                                    <span>Approval Rate</span>
                                    <span><?php echo $width_approved > 0 ? round($width_approved) . '%' : '0%'; ?></span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill fill-green"
                                        style="width: <?php echo $width_approved; ?>%"></div>
                                    <div class="progress-bar-fill fill-orange"
                                        style="width: <?php echo $width_pending; ?>%"></div>
                                </div>
                            </div>

                            <div class="meta-info">
                                <span>Joined <?php echo date('M Y', strtotime($u['joined_at'])); ?></span>
                                <span title="Last IP: <?php echo htmlspecialchars($u['last_ip']); ?>">
                                    IP: <?php echo $u['last_ip'] ? '...' . substr($u['last_ip'], -3) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </div>

    <script>
        function filterUsers() {
            let input = document.getElementById('userSearch').value.toLowerCase();
            let cards = document.getElementsByClassName('user-card');

            for (let i = 0; i < cards.length; i++) {
                let text = cards[i].getAttribute('data-search');
                if (text.indexOf(input) > -1) {
                    cards[i].style.display = "";
                } else {
                    cards[i].style.display = "none";
                }
            }
        }
    </script>
</body>

</html>