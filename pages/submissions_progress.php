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

if ($status_filter !== '') {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch all filtered L&D activities
$sql = "SELECT * FROM ld_activities WHERE $where_sql ORDER BY created_at DESC";
$stmt_ld = $pdo->prepare($sql);
$stmt_ld->execute($params);
$activities = $stmt_ld->fetchAll(PDO::FETCH_ASSOC);

/**
 * Helper to determine current stage and progress percentage
 */
function getProgressInfo($act) {
    $stages = [
        ['label' => 'Submitted', 'completed' => true, 'date' => $act['created_at']],
        ['label' => 'Reviewed', 'completed' => (bool)$act['reviewed_by_supervisor'], 'date' => $act['reviewed_at']],
        ['label' => 'Recommended', 'completed' => (bool)$act['recommending_asds'], 'date' => $act['recommended_at']],
        ['label' => 'Approved', 'completed' => (bool)$act['approved_sds'], 'date' => $act['approved_at']]
    ];
    
    $completedCount = 0;
    foreach ($stages as $stage) {
        if ($stage['completed']) $completedCount++;
    }
    
    $percentage = ($completedCount / count($stages)) * 100;
    
    return [
        'stages' => $stages,
        'percentage' => $percentage,
        'current_status' => $act['status']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions Progress - LDP Passbook</title>
    <?php require '../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/pages/submissions-progress.css">
</head>

<body>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php require '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="submissions-container">
                <div class="page-header">
                    <h1>Submissions Progress</h1>
                    <p>Track the status of your Professional Development activities</p>
                </div>

                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="search-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" name="search" placeholder="Search by activity title..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-controls">
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Reviewed" <?php echo $status_filter == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            </select>
                            <button type="submit" class="btn-filter">Filter</button>
                            <?php if ($search !== '' || $status_filter !== ''): ?>
                                <a href="submissions_progress.php" class="btn-reset">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="submissions-scroll-wrapper">
                    <div class="submissions-list">
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $act): 
                                $progress = getProgressInfo($act);
                            ?>
                                <div class="submission-item">
                                    <div class="submission-info">
                                        <div class="submission-header">
                                            <h3><?php echo htmlspecialchars($act['title']); ?></h3>
                                            <span class="submission-date">Submitted: <?php echo date('M d, Y', strtotime($act['created_at'])); ?></span>
                                        </div>
                                        <div class="submission-details">
                                            <span><strong>Venue:</strong> <?php echo htmlspecialchars($act['venue'] ?: 'N/A'); ?></span>
                                            <span><strong>Modality:</strong> <?php echo htmlspecialchars($act['modality'] ?: 'N/A'); ?></span>
                                            <div class="status-badge" data-status="<?php echo $act['status']; ?>">
                                                <?php echo htmlspecialchars($act['status']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="progress-container">
                                        <div class="progress-track">
                                            <div class="progress-bar" style="width: <?php echo $progress['percentage']; ?>%;"></div>
                                        </div>
                                        <div class="progress-steps">
                                            <?php foreach ($progress['stages'] as $index => $stage): ?>
                                                <div class="step <?php echo $stage['completed'] ? 'completed' : ''; ?>">
                                                    <div class="step-icon">
                                                        <?php if ($stage['completed']): ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        <?php else: ?>
                                                            <?php echo $index + 1; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="step-label">
                                                        <?php echo $stage['label']; ?>
                                                        <?php if ($stage['completed'] && $stage['date']): ?>
                                                            <small><?php echo date('M d, Y', strtotime($stage['date'])); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="submission-actions">
                                        <a href="view_activity.php?id=<?php echo $act['id']; ?>" class="btn-view">View Details</a>
                                        <?php if (!$act['reviewed_by_supervisor']): ?>
                                            <a href="edit_activity.php?id=<?php echo $act['id']; ?>" class="btn-edit">Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-submissions">
                                <div class="no-data-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                </div>
                                <h3>No Submissions Found</h3>
                                <p>You haven't added any activities yet. Start by adding one!</p>
                                <a href="add_activity.php" class="btn-primary">Add Activity</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
