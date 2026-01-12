<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ../index.php");
    exit;
}



// Fetch all activities
$stmt = $pdo->query("
    SELECT ld.*, u.full_name, u.office_station 
    FROM ld_activities ld 
    JOIN users u ON ld.user_id = u.id 
    ORDER BY ld.created_at DESC
");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Statistics
$totalSubmissions = count($activities);

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'");
$totalUsers = $stmt->fetchColumn();

// Count per status
$pendingCount = 0;
$approvedCount = 0;
foreach ($activities as $act) {
    if ($act['status'] === 'Pending')
        $pendingCount++;
    if ($act['status'] === 'Approved')
        $approvedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LDP</title>
    <?php require 'includes/admin_head.php'; ?>
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
            --orange-glow: rgba(249, 115, 22, 0.15);
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
            padding-left: 20px;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin: 30px 0;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-blue);
        }

        .stat-card.orange-theme::after {
            background: var(--accent-orange);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Table Section */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            margin-top: 40px;
        }

        .section-title {
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.01em;
            font-size: 1.1rem;
        }

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
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .styled-table thead th:first-child {
            border-left: 3px solid var(--accent-orange);
        }

        .styled-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .styled-table tr:last-child td {
            border-bottom: none;
        }

        .styled-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
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

        .btn-view {
            color: var(--accent-blue);
            font-weight: 600;
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-view:hover {
            color: #2563eb;
            transform: translateX(3px);
        }
    </style>
</head>

<body>

    <div class="dashboard-container">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="passbook-container" style="width: 1000px; max-width: 95%;">
                <div class="header">
                    <h1>Admin Dashboard</h1>
                    <p>System insight and recent passbook activity</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Total Submissions</span>
                        <span class="stat-value"><?php echo number_format($totalSubmissions); ?></span>
                    </div>
                    <div class="stat-card orange-theme">
                        <span class="stat-label">Total Users</span>
                        <span class="stat-value"><?php echo number_format($totalUsers); ?></span>
                    </div>
                    <div class="stat-card orange-theme">
                        <span class="stat-label">Pending Approval</span>
                        <span class="stat-value"
                            style="color: var(--accent-orange);"><?php echo number_format($pendingCount); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Final Approved</span>
                        <span class="stat-value"
                            style="color: var(--accent-green);"><?php echo number_format($approvedCount); ?></span>
                    </div>
                </div>

                <div class="section-header">
                    <div class="section-title">Recent Activity Logs</div>
                    <a href="submissions.php" class="btn-view">View All Submissions →</a>
                </div>

                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Date Attended</th>
                            <th>User Details</th>
                            <th>Activity Title</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">No recent
                                    activities found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($activities, 0, 8) as $act): ?>
                                <tr>
                                    <td style="font-weight: 500; color: #1e293b;">
                                        <?php echo date('M d, Y', strtotime($act['date_attended'])); ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #1e293b;">
                                            <?php echo htmlspecialchars($act['full_name']); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;">
                                            <?php echo htmlspecialchars($act['office_station']); ?>
                                        </div>
                                    </td>
                                    <td style="max-width: 300px; line-height: 1.4;">
                                        <?php echo htmlspecialchars($act['title']); ?>
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
                                    <td style="text-align: right;">
                                        <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>" class="btn-view"
                                            style="justify-content: flex-end;">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

</html>