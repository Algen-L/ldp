<?php
session_start();
require '../includes/init_repos.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Fetch user data from DB to get the latest info
$user = $userRepo->getUserById($_SESSION['user_id']);

if (!$user) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

// Fetch L&D activities (Recent 10)
$activities = $activityRepo->getActivitiesByUser($_SESSION['user_id'], ['limit' => 10]);

// Fetch Stats for Progress Bar
$stats = $activityRepo->getUserStats($_SESSION['user_id']);
$total_count = $stats['total'];
$approved_count = $stats['approved'] ?: 0;
$progress_pct = $total_count > 0 ? round(($approved_count / $total_count) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - LDP Passbook</title>
    <?php require 'includes/user_head.php'; ?>
</head>

<body>

    <div class="app-layout">
        <!-- Sidebar -->
        <?php require '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">My Dashboard</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <div class="time-section">
                            <span id="real-time-clock">
                                <?php echo date('h:i:s A'); ?>
                            </span>
                        </div>
                        <div class="date-section">
                            <i class="bi bi-calendar3"></i>
                            <span>
                                <?php echo date('F j, Y'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <div class="user-dashboard-grid">
                    <div class="dashboard-card user-profile-card">
                        <div class="card-profile-header"></div>
                        <div class="card-profile-body">
                            <div class="profile-avatar-container">
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile"
                                        class="profile-avatar">
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h2 class="profile-name">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </h2>
                            <p class="profile-position">
                                <?php echo htmlspecialchars($user['position'] ?: 'Employee'); ?>
                            </p>

                            <div class="profile-stats">
                                <div class="profile-stat-item">
                                    <span class="profile-stat-val">
                                        <?php echo $total_count; ?>
                                    </span>
                                    <span class="profile-stat-label">Activities</span>
                                </div>
                                <div class="profile-stat-item">
                                    <span class="profile-stat-val" style="color: var(--success);">
                                        <?php echo $approved_count; ?>
                                    </span>
                                    <span class="profile-stat-label">Approved</span>
                                </div>
                            </div>

                            <div class="progress-section" style="margin-bottom: 24px;">
                                <div class="progress-label"
                                    style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <span>Goal Completion</span>
                                    <span style="color: var(--primary); font-weight: 800;">
                                        <?php echo $progress_pct; ?>%
                                    </span>
                                </div>
                                <div class="progress-bar-bg"
                                    style="height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                    <div class="progress-bar-fill green"
                                        style="width: <?php echo $progress_pct; ?>%; height: 100%; border-radius: 4px; background: var(--success); transition: width 0.6s ease;">
                                    </div>
                                </div>
                            </div>

                            <div class="profile-info-list"
                                style="border-top: 1px solid var(--border-light); padding-top: 12px;">
                                <div class="profile-info-item">
                                    <div class="profile-info-icon"><i class="bi bi-building"></i></div>
                                    <div class="profile-info-content">
                                        <span class="profile-info-label">Office / Station</span>
                                        <span class="profile-info-value">
                                            <?php echo htmlspecialchars($user['office_station'] ?: 'Not Set'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="profile-info-item">
                                    <div class="profile-info-icon"
                                        style="background: var(--warning-bg); color: var(--warning);"><i
                                            class="bi bi-calendar-event"></i></div>
                                    <div class="profile-info-content">
                                        <span class="profile-info-label">Rating Period</span>
                                        <span class="profile-info-value">
                                            <?php echo htmlspecialchars($user['rating_period'] ?: 'Not Set'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="profile-info-item">
                                    <div class="profile-info-icon"
                                        style="background: var(--success-bg); color: var(--success);"><i
                                            class="bi bi-award"></i></div>
                                    <div class="profile-info-content">
                                        <span class="profile-info-label">Specialization</span>
                                        <span class="profile-info-value">
                                            <?php echo htmlspecialchars($user['area_of_specialization'] ?: 'Generalist'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Right Column: Activity Center -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        <!-- Quick Actions -->
                        <div class="dashboard-card dashboard-hero-card">
                            <div class="hero-content">
                                <div class="hero-text">
                                    <h3>Ready to record a new success?</h3>
                                    <p>This System will track your learning and development engagements to address
                                        your competency gaps that surfaced in your Individual Development Plan (IDP).
                                    </p>
                                </div>
                                <div class="hero-action">
                                    <a href="../pages/add_activity.php" class="hero-action-btn">
                                        <i class="bi bi-plus"></i>
                                        <span>ADD ACTIVITY</span>
                                    </a>
                                </div>
                                <img src="../assets/logologo.png" alt="Decorative" class="hero-decorative-img">
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="dashboard-card recent-activity-card">
                            <div class="card-header">
                                <h2><i class="bi bi-clock-history"></i> Recent Activity Log</h2>
                                <a href="../pages/submissions_progress.php"
                                    style="font-size: 0.85rem; font-weight: 700; color: var(--primary); text-decoration: none;">View
                                    All History <i class="bi bi-arrow-right"></i></a>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <div class="activity-list" style="max-height: 400px; overflow-y: auto;">
                                    <?php if (count($activities) > 0): ?>
                                        <?php foreach ($activities as $act): ?>
                                            <div class="activity-item">
                                                <div class="activity-icon">
                                                    <i class="bi bi-journal-check"></i>
                                                </div>
                                                <div class="activity-content">
                                                    <div class="activity-header">
                                                        <span class="activity-title">
                                                            <?php echo htmlspecialchars($act['title']); ?>
                                                        </span>
                                                        <span class="activity-time">
                                                            <?php
                                                            $dates = explode(', ', $act['date_attended']);
                                                            echo date('M d, Y', strtotime($dates[0]));
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <div
                                                        style="display: flex; align-items: center; justify-content: space-between; margin-top: 6px;">
                                                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                                                            <?php echo htmlspecialchars($act['competency']); ?>
                                                        </span>
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
                                                        <span class="activity-status-badge <?php echo $statusClass; ?>">
                                                            <i class="bi bi-circle-fill"
                                                                style="font-size: 0.4rem; margin-right: 4px;"></i>
                                                            <?php echo $statusLabel; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                                            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                            <p style="margin-top: 15px; font-weight: 500;">No activities recorded yet.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="user-footer">
                <p>&copy;
                    <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span>
                </p>
            </footer>
        </div>
    </div>

</body>

</html>