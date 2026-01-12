<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_admin_dir = ($current_dir === 'admin');

// Define prefixes
$admin_prefix = $is_admin_dir ? '' : '../admin/';
$pages_prefix = $is_admin_dir ? '../pages/' : '';
$css_prefix = $is_admin_dir ? '../css/' : '../css/'; // Both go up to root
$js_prefix = $is_admin_dir ? '../js/' : '../js/';   // Both go up to root
?>
<?php // Sidebar no longer manages its own CSS links; they are handled in head/admin_head PHP includes ?>
<script src="<?php echo $js_prefix; ?>notifications.js"></script>

<div id="toast-container"></div>

<div class="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/LogoLDP.png"
            alt="<?php echo ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') ? 'Admin Panel' : 'LDP System'; ?>">
        <div class="sidebar-user">
            <span
                class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']); ?></span>
            <?php
            // Fallback for current sessions that don't have 'position' yet
            if (!isset($_SESSION['position'])) {
                require_once __DIR__ . '/db.php';
                $stmt_pos = $pdo->prepare("SELECT position FROM users WHERE id = ?");
                $stmt_pos->execute([$_SESSION['user_id']]);
                $_SESSION['position'] = $stmt_pos->fetchColumn();
            }
            ?>
            <span
                class="user-role"><?php echo htmlspecialchars($_SESSION['position'] ?: str_replace('_', ' ', $_SESSION['role'])); ?></span>
        </div>
    </div>
    <div class="sidebar-nav">
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
            <?php
            // Get Pending Count
            $pending_badges = 0;
            if (isset($pdo)) { // Ensure DB connection exists
                $stmt_pending = $pdo->query("SELECT COUNT(*) FROM ld_activities WHERE status = 'Pending'");
                $pending_badges = $stmt_pending->fetchColumn();
            }
            ?>
            <a href="<?php echo $admin_prefix; ?>dashboard.php"
                class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"
                style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Dashboard
                </div>
                <?php if ($pending_badges > 0): ?>
                    <span
                        style="background-color: #ef4444; color: white; font-size: 0.75em; padding: 2px 8px; border-radius: 10px; font-weight: bold; box-shadow: 0 2px 4px rgba(239,68,68,0.3);">
                        <?php echo $pending_badges; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?php echo $admin_prefix; ?>submissions.php"
                class="nav-item <?php echo ($current_page == 'submissions.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Submissions
            </a>
            <a href="<?php echo $admin_prefix; ?>users.php"
                class="nav-item <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
                Activity Logs
            </a>
            <a href="<?php echo $admin_prefix; ?>user_status.php"
                class="nav-item <?php echo ($current_page == 'user_status.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                User Status
            </a>

            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="<?php echo $admin_prefix; ?>manage_users.php"
                    class="nav-item <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    User Management
                </a>
            <?php endif; ?>
            <a href="<?php echo $admin_prefix; ?>profile.php"
                class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Profile
            </a>
        <?php else: ?>
            <a href="<?php echo $pages_prefix; ?>home.php"
                class="nav-item <?php echo ($current_page == 'home.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Dashboard
            </a>
            <a href="<?php echo $pages_prefix; ?>add_activity.php"
                class="nav-item <?php echo ($current_page == 'add_activity.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                ADD ACTIVITY
            </a>
            <a href="<?php echo $pages_prefix; ?>submissions_progress.php"
                class="nav-item <?php echo ($current_page == 'submissions_progress.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                SUBMISSIONS
            </a>
            <a href="<?php echo $pages_prefix; ?>profile.php"
                class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Profile
            </a>
        <?php endif; ?>
    </div>

    <div class="logout-container">
        <a href="<?php echo $pages_prefix; ?>logout.php" class="nav-item logout" title="Logout">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>
</div>