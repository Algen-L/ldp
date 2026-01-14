<?php
session_start();
require '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$where_clauses = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if ($search !== '') {
    $where_clauses[] = "title LIKE ?";
    $params[] = "%$search%";
}

// Modified status filtering to match current logic
if ($status_filter === 'Approved') {
    $where_clauses[] = "approved_sds = 1";
} elseif ($status_filter === 'Reviewed') {
    $where_clauses[] = "reviewed_by_supervisor = 1 AND approved_sds = 0";
} elseif ($status_filter === 'Pending') {
    $where_clauses[] = "reviewed_by_supervisor = 0";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch all filtered L&D activities
$sql = "SELECT * FROM ld_activities WHERE $where_sql ORDER BY date_attended DESC, created_at DESC";
$stmt_ld = $pdo->prepare($sql);
$stmt_ld->execute($params);
$activities = $stmt_ld->fetchAll(PDO::FETCH_ASSOC);

/**
 * Helper to determine current stage and progress percentage
 */
function getProgressInfo($act)
{
    $stages = [
        ['label' => 'Submitted', 'completed' => true, 'date' => $act['created_at'], 'icon' => 'bi-send'],
        ['label' => 'Reviewed', 'completed' => (bool) $act['reviewed_by_supervisor'], 'date' => $act['reviewed_at'], 'icon' => 'bi-eye'],
        ['label' => 'Recommended', 'completed' => (bool) $act['recommending_asds'], 'date' => $act['recommended_at'], 'icon' => 'bi-check2-circle'],
        ['label' => 'Approved', 'completed' => (bool) $act['approved_sds'], 'date' => $act['approved_at'], 'icon' => 'bi-trophy']
    ];

    $completedCount = 0;
    foreach ($stages as $stage) {
        if ($stage['completed'])
            $completedCount++;
    }

    $percentage = ($completedCount / count($stages)) * 100;

    return [
        'stages' => $stages,
        'percentage' => $percentage
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions Progress - LDP</title>
    <?php require '../includes/head.php'; ?>
    <style>
        .submission-card {
            margin-bottom: 24px;
            position: relative;
        }

        .submission-card .card-body {
            padding: 32px;
        }

        .prog-track-wrapper {
            margin-top: 32px;
            position: relative;
            padding: 0 10px;
        }

        .prog-track-line {
            position: absolute;
            top: 18px;
            left: 30px;
            right: 30px;
            height: 4px;
            background: var(--bg-tertiary);
            z-index: 1;
            border-radius: 2px;
        }

        .prog-track-fill {
            position: absolute;
            top: 18px;
            left: 30px;
            height: 4px;
            background: var(--success);
            z-index: 2;
            border-radius: 2px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .prog-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 3;
        }

        .prog-step {
            text-align: center;
            flex: 1;
        }

        .prog-icon {
            width: 40px;
            height: 40px;
            background: white;
            border: 3px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1rem;
            color: var(--text-muted);
            transition: all var(--transition-base);
        }

        .prog-step.active .prog-icon {
            border-color: var(--success);
            color: var(--success);
            box-shadow: 0 0 0 6px var(--success-bg);
        }

        .prog-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .prog-date {
            font-size: 0.65rem;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
        }

        .filter-bar-custom {
            background: var(--card-bg);
            padding: 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 32px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>

<body>

    <div class="user-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">My Activity History</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <i class="bi bi-calendar-check"></i>
                        <span><?php echo count($activities); ?> Records Found</span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <!-- Specialized Filter Bar -->
                <form method="GET" class="filter-bar-custom">
                    <div style="position: relative; flex: 1; min-width: 250px;">
                        <i class="bi bi-search"
                            style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" name="search" class="form-control" placeholder="Search activities..."
                            value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 42px;">
                    </div>
                    <div style="width: 200px;">
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending
                            </option>
                            <option value="Reviewed" <?php echo $status_filter == 'Reviewed' ? 'selected' : ''; ?>>
                                Reviewed</option>
                            <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>
                                Approved</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Apply Filter
                    </button>
                    <?php if ($search || $status_filter): ?>
                        <a href="submissions_progress.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <div class="submissions-list">
                    <?php if (count($activities) > 0): ?>
                        <?php foreach ($activities as $act):
                            $prog = getProgressInfo($act);
                            $fill_pct = 0;
                            if ($prog['percentage'] > 0)
                                $fill_pct = ($prog['percentage'] - 25) / (100 - 25) * 100; // Adjustment for visual line
                            // Simpler logic for the fill line:
                            $active_count = 0;
                            foreach ($prog['stages'] as $s)
                                if ($s['completed'])
                                    $active_count++;
                            $line_pct = ($active_count - 1) / (count($prog['stages']) - 1) * 100;
                            if ($line_pct < 0)
                                $line_pct = 0;
                            ?>
                            <div class="dashboard-card submission-card">
                                <div class="card-body">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <h3
                                                style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
                                                <?php echo htmlspecialchars($act['title']); ?>
                                            </h3>
                                            <div
                                                style="display: flex; gap: 16px; font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                                                <span><i class="bi bi-geo-alt"></i>
                                                    <?php echo htmlspecialchars($act['venue'] ?: 'N/A'); ?></span>
                                                <span><i class="bi bi-layers"></i>
                                                    <?php echo htmlspecialchars($act['modality'] ?: 'N/A'); ?></span>
                                                <span><i class="bi bi-calendar-check"></i> Attended:
                                                    <?php
                                                    $dates = explode(', ', $act['date_attended']);
                                                    echo date('M d, Y', strtotime($dates[0]));
                                                    if (count($dates) > 1)
                                                        echo ' (+' . (count($dates) - 1) . ' more)';
                                                    ?></span>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <?php
                                            $statusLabel = 'Pending';
                                            $statusClass = 'status-pending';
                                            if ($act['approved_sds']) {
                                                $statusLabel = 'Approved';
                                                $statusClass = 'status-approved';
                                            } elseif ($act['recommending_asds']) {
                                                $statusLabel = 'Recommending';
                                                $statusClass = 'status-recommending';
                                            } elseif ($act['reviewed_by_supervisor']) {
                                                $statusLabel = 'Reviewed';
                                                $statusClass = 'status-reviewed';
                                            }
                                            ?>
                                            <span class="activity-status-badge <?php echo $statusClass; ?>"
                                                style="padding: 6px 14px; font-size: 0.8rem;">
                                                <?php echo $statusLabel; ?>
                                            </span>
                                            <span
                                                style="display: block; font-size: 0.7rem; color: var(--text-muted); margin-top: 8px;">
                                                Submitted <?php echo date('M d, Y', strtotime($act['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="prog-track-wrapper">
                                        <div class="prog-track-line"></div>
                                        <div class="prog-track-fill" style="width: <?php echo $line_pct; ?>%;"></div>
                                        <div class="prog-steps">
                                            <?php foreach ($prog['stages'] as $index => $stage): ?>
                                                <div class="prog-step <?php echo $stage['completed'] ? 'active' : ''; ?>">
                                                    <div class="prog-icon">
                                                        <i class="bi <?php echo $stage['icon']; ?>"></i>
                                                    </div>
                                                    <span class="prog-label"><?php echo $stage['label']; ?></span>
                                                    <?php if ($stage['completed'] && $stage['date']): ?>
                                                        <span
                                                            class="prog-date"><?php echo date('M d', strtotime($stage['date'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div
                                        style="margin-top: 32px; padding-top: 24px; border-top: 1.5px solid var(--border-light); display: flex; justify-content: flex-end; gap: 12px;">
                                        <a href="view_activity.php?id=<?php echo $act['id']; ?>" class="btn btn-secondary">
                                            <i class="bi bi-eye"></i> View Details
                                        </a>
                                        <?php if (!$act['reviewed_by_supervisor']): ?>
                                            <a href="edit_activity.php?id=<?php echo $act['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit Record
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dashboard-card" style="padding: 100px 40px; text-align: center;">
                            <i class="bi bi-clipboard-x"
                                style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>
                            <h2 style="margin-top: 24px; font-weight: 800; color: var(--text-primary);">No records found
                            </h2>
                            <p style="color: var(--text-secondary); margin-bottom: 32px;">We couldn't find any activities
                                matching your filters.</p>
                            <a href="add_activity.php" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg"></i> Record
                                New Activity</a>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <footer class="user-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. All rights reserved.</p>
            </footer>
        </div>
    </div>

</body>

</html>