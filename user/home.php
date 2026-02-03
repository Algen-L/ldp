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

// Fetch Unaddressed Needs (usage_count = 0)
$all_ildns = $ildnRepo->getILDNsByUser($_SESSION['user_id']);
$unaddressed_needs = array_filter($all_ildns, function ($item) {
    return $item['usage_count'] == 0;
});

// Fetch System Notifications
$notifications = $notifRepo->getUnreadNotifications($_SESSION['user_id']);

// AJAX handler for marking as read
if (isset($_GET['action']) && $_GET['action'] === 'read_notif' && isset($_GET['notif_id'])) {
    $notif_id = (int) $_GET['notif_id'];
    if ($notifRepo->markAsRead($notif_id, $_SESSION['user_id'])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
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
                                            class="bi bi-person-badge"></i></div>
                                    <div class="profile-info-content">
                                        <span class="profile-info-label">Employee Number</span>
                                        <span class="profile-info-value">
                                            <?php echo htmlspecialchars($user['employee_number'] ?: 'Not Set'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="profile-info-item">
                                    <div class="profile-info-icon" style="background: #e0f2fe; color: #0ea5e9;">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="profile-info-content">
                                        <span class="profile-info-label">Rating Period</span>
                                        <span class="profile-info-value">
                                            <?php echo htmlspecialchars($user['rating_period'] ?: 'Not Set'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="profile-info-item">
                                    <div class="profile-info-icon"
                                        style="background: var(--success-bg); color: var(--success);">
                                        <i class="bi bi-award"></i>
                                    </div>
                                    <div class="profile-info-content">
                                        <span class="profile-info-label">Specialization</span>
                                        <span class="profile-info-value">
                                            <?php echo htmlspecialchars($user['area_of_specialization'] ?: 'Generalist'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="profile-info-item">
                                    <div class="profile-info-icon" style="background: #fff1f2; color: #ef4444;">
                                        <i class="bi bi-lightbulb-fill"></i>
                                    </div>
                                    <div class="profile-info-content">
                                        <span class="profile-info-label">Unaddressed Needs</span>
                                        <div class="profile-info-value"
                                            style="font-size: 0.8rem; line-height: 1.4; margin-top: 4px;">
                                            <?php if (!empty($unaddressed_needs)): ?>
                                                    <ul
                                                        style="margin: 0; padding-left: 14px; color: #ef4444; font-weight: 700;">
                                                        <?php foreach ($unaddressed_needs as $need): ?>
                                                                <li><?php echo htmlspecialchars($need['need_text']); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                            <?php else: ?>
                                                    <span style="color: var(--success); font-weight: 800;">✓ All needs currently
                                                        addressed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        </div>

                    <!-- Right Column: Activity Center -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        
                        <!-- System Notifications -->
                        <?php if (!empty($notifications)): ?>
                            <div class="dashboard-card" style="border-left: 4px solid #f59e0b; background: #fffbeb;">
                                <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(245, 158, 11, 0.1); padding-bottom: 12px; margin-bottom: 12px;">
                                    <h2 style="color: #92400e; font-size: 1rem;"><i class="bi bi-megaphone-fill"></i> System Notifications</h2>
                                    <span class="badge" style="background: #f59e0b; color: white; border-radius: 20px; padding: 4px 10px; font-size: 0.7rem;">
                                        <?php echo count($notifications); ?> NEW
                                    </span>
                                </div>
                                <div class="card-body" style="padding: 0;">
                                    <div class="notification-feed" style="max-height: 300px; overflow-y: auto; padding-right: 5px;">
                                        <?php foreach ($notifications as $notif): ?>
                                            <div class="notification-item" id="notif-<?php echo $notif['id']; ?>" 
                                                 style="background: white; border-radius: 12px; padding: 16px; margin-bottom: 12px; border: 1px solid #fef3c7; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: relative; transition: all 0.2s;">
                                                <div style="display: flex; gap: 12px; align-items: flex-start;">
                                                    <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #f1f5f9; flex-shrink: 0; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                                        <?php if (!empty($notif['sender_picture'])): ?>
                                                                <img src="../<?php echo htmlspecialchars($notif['sender_picture']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--primary); color: white; font-weight: 800; font-size: 1rem;">
                                                                    <?php echo strtoupper(substr($notif['sender_name'], 0, 1)); ?>
                                                                </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="flex: 1;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                            <span style="font-size: 0.8rem; font-weight: 800; color: var(--primary);"><?php echo htmlspecialchars($notif['sender_name']); ?></span>
                                                            <span style="font-size: 0.65rem; font-weight: 600; color: #94a3b8;"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></span>
                                                        </div>
                                                        <p style="font-size: 0.9rem; color: #475569; line-height: 1.5; margin: 0; font-weight: 500;">
                                                            <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                                                        </p>
                                                        <div style="margin-top: 12px; text-align: right;">
                                                            <button onclick="markAsRead(<?php echo $notif['id']; ?>)" 
                                                                    style="background: #f8fafc; border: 1.5px solid #e2e8f0; color: #64748b; padding: 6px 12px; border-radius: 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;">
                                                                <i class="bi bi-check2-all"></i> DISMISS
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <script>
                            function markAsRead(notifId) {
                                const item = document.getElementById('notif-' + notifId);
                                item.style.opacity = '0.5';
                                item.style.transform = 'scale(0.95)';
                            
                                fetch('home.php?action=read_notif&notif_id=' + notifId)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            item.style.height = '0';
                                            item.style.padding = '0';
                                            item.style.margin = '0';
                                            item.style.overflow = 'hidden';
                                            item.style.border = 'none';
                                            setTimeout(() => {
                                                item.remove();
                                                if (document.querySelectorAll('.notification-item').length === 0) {
                                                    location.reload(); // Reload to hide the card if no more notifs
                                                }
                                            }, 300);
                                        } else {
                                            item.style.opacity = '1';
                                            item.style.transform = 'none';
                                            alert('Could not dismiss notification.');
                                        }
                                    });
                            }
                            </script>
                        <?php endif; ?>
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