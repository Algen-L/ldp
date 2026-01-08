<?php
session_start();
require '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}



// Fetch user data from DB to get the latest info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

// Fetch L&D activities
$stmt_ld = $pdo->prepare("SELECT * FROM ld_activities WHERE user_id = ? ORDER BY date_attended DESC LIMIT 5");
$stmt_ld->execute([$_SESSION['user_id']]);
$activities = $stmt_ld->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LDP Passbook</title>
    <?php require '../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/pages/home.css">
</head>

<body>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php require '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-grid">
                <!-- Left Column: Profile Card -->
                <div class="profile-card">
                    <div class="profile-photo-circle">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="no-photo">No Photo</div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-name">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </div>

                    <div class="profile-position">
                        <?php echo htmlspecialchars($user['position']); ?>
                    </div>

                    <div class="profile-details">
                        <div class="profile-detail-item">
                            <span class="profile-detail-label">Office/Station:</span>
                            <span
                                class="profile-detail-value"><?php echo htmlspecialchars($user['office_station']); ?></span>
                        </div>
                        <div class="profile-detail-item">
                            <span class="profile-detail-label">Rating Period:</span>
                            <span
                                class="profile-detail-value"><?php echo htmlspecialchars($user['rating_period']); ?></span>
                        </div>
                        <div class="profile-detail-item">
                            <span class="profile-detail-label">Specialization:</span>
                            <span
                                class="profile-detail-value"><?php echo htmlspecialchars($user['area_of_specialization']); ?></span>
                        </div>
                        <div class="profile-detail-item">
                            <span class="profile-detail-label">Age/Sex:</span>
                            <span class="profile-detail-value"><?php echo htmlspecialchars($user['age']); ?> /
                                <?php echo htmlspecialchars($user['sex']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Activity Card -->
                <div class="activity-card">
                    <!-- Add Activity Section -->
                    <div class="add-activity-section">
                        <a href="add_activity.php" class="add-activity-btn">ADD ACTIVITY</a>
                    </div>

                    <!-- Recent Activity Section -->
                    <div class="recent-activity-section">
                        <div class="recent-activity-header">
                            RECENT ACTIVITY
                        </div>
                        <div class="recent-activity-content">
                            <?php if (count($activities) > 0): ?>
                                <ul class="activity-list">
                                    <?php foreach ($activities as $act): ?>
                                        <li class="activity-list-item">
                                            <span class="activity-title"><?php echo htmlspecialchars($act['title']); ?></span>
                                            <span
                                                class="activity-date"><?php echo date('M d, Y', strtotime($act['date_attended'])); ?></span>
                                            <span class="activity-status"
                                                style="background-color: <?php echo $act['status'] == 'Approved' ? '#d4edda' : '#fff3cd'; ?>; 
                                                         color: <?php echo $act['status'] == 'Approved' ? '#155724' : '#856404'; ?>;">
                                                <?php echo htmlspecialchars($act['status']); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="no-activities">No activities recorded yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>