<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_admin_dir = ($current_dir === 'admin');
$is_hr_dir = ($current_dir === 'hr');
$is_pages_dir = ($current_dir === 'pages');

// Determine path to root
$to_root = ($is_admin_dir || $is_hr_dir || $is_pages_dir) ? '../' : '';

// Define prefixes
$admin_prefix = $is_admin_dir ? '' : $to_root . 'admin/';
$pages_prefix = $is_pages_dir ? '' : $to_root . 'pages/';
?>
<?php // Sidebar no longer manages its own CSS/JS links; they are handled in head/admin_head PHP includes ?>

<div id="toast-container"></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="<?php echo $to_root; ?>assets/LogoLDP.png" alt="LDP Logo" class="logo-img">
            <div class="logo-text">
                <span class="logo-title">LDP</span>
                <span class="logo-subtitle">Passbook System</span>
            </div>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
            <i class="bi bi-chevron-left toggle-icon"></i>
        </button>
    </div>

    <div class="sidebar-nav">
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'immediate_head'): ?>
            <?php
            // Get Pending Count for Badge
            $pending_count = 0;
            if (isset($pdo)) {
                $stmt_pending = $pdo->query("SELECT COUNT(*) FROM ld_activities WHERE reviewed_by_supervisor = 0");
                $pending_count = $stmt_pending->fetchColumn();
            }
            ?>
            <a href="<?php echo $admin_prefix; ?>dashboard.php"
                class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <div class="nav-icon">
                    <i class="bi bi-grid-fill"></i>
                </div>
                <span class="nav-text">Dashboard</span>
            </a>

            <a href="<?php echo $admin_prefix; ?>submissions.php"
                class="nav-item <?php echo (in_array($current_page, ['submissions.php', 'view_activity.php', 'edit_activity.php']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin')) ? 'active' : ''; ?>"
                data-tooltip="Submissions">
                <div class="nav-icon">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <?php if ($pending_count > 0): ?>
                        <span class="nav-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </div>
                <span class="nav-text">Submissions</span>
            </a>

            <a href="<?php echo $admin_prefix; ?>users.php"
                class="nav-item <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" data-tooltip="Activity Logs">
                <div class="nav-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <span class="nav-text">Activity Logs</span>
            </a>

            <a href="<?php echo $admin_prefix; ?>user_status.php"
                class="nav-item <?php echo ($current_page == 'user_status.php') ? 'active' : ''; ?>"
                data-tooltip="User Status">
                <div class="nav-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <span class="nav-text">User Status</span>
            </a>

            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="<?php echo $admin_prefix; ?>manage_users.php"
                    class="nav-item <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"
                    data-tooltip="User Management">
                    <div class="nav-icon">
                        <i class="bi bi-person-fill-gear"></i>
                    </div>
                    <span class="nav-text">User Management</span>
                </a>

                <a href="<?php echo $admin_prefix; ?>register.php"
                    class="nav-item <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>"
                    data-tooltip="Register Account">
                    <div class="nav-icon">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <span class="nav-text">Register Account</span>
                </a>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'immediate_head'): ?>
                <a href="<?php echo $pages_prefix; ?>add_activity.php"
                    class="nav-item <?php echo ($current_page == 'add_activity.php') ? 'active' : ''; ?>"
                    data-tooltip="Record Activity">
                    <div class="nav-icon">
                        <i class="bi bi-plus-circle-fill"></i>
                    </div>
                    <span class="nav-text">Record Activity</span>
                </a>

                <a href="<?php echo $pages_prefix; ?>submissions_progress.php"
                    class="nav-item <?php echo ($current_page == 'submissions_progress.php') ? 'active' : ''; ?>"
                    data-tooltip="My Submissions">
                    <div class="nav-icon">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <span class="nav-text">My Submissions</span>
                </a>
            <?php endif; ?>

            <div class="nav-divider"></div>

            <a href="<?php echo $admin_prefix; ?>profile.php"
                class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" data-tooltip="My Profile">
                <div class="nav-icon">
                    <i class="bi bi-person-circle"></i>
                </div>
                <span
                    class="nav-text"><?php echo ($_SESSION['role'] === 'immediate_head') ? 'My Profile' : 'Admin Profile'; ?></span>
            </a>

        <?php else: // HR and Regular Users ?>
            <a href="<?php echo $pages_prefix; ?>home.php"
                class="nav-item <?php echo ($current_page == 'home.php') ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <div class="nav-icon">
                    <i class="bi bi-house-door-fill"></i>
                </div>
                <span class="nav-text">My Dashboard</span>
            </a>

            <?php if ($_SESSION['role'] === 'hr'): ?>
                <a href="<?php echo $admin_prefix; ?>../hr/register.php"
                    class="nav-item <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>"
                    data-tooltip="Register Personnel">
                    <div class="nav-icon">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <span class="nav-text">Register Personnel</span>
                </a>

                <a href="<?php echo $admin_prefix; ?>manage_users.php"
                    class="nav-item <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"
                    data-tooltip="User Management">
                    <div class="nav-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="nav-text">User Management</span>
                </a>
            <?php endif; ?>

            <a href="<?php echo $pages_prefix; ?>add_activity.php"
                class="nav-item <?php echo ($current_page == 'add_activity.php') ? 'active' : ''; ?>"
                data-tooltip="Add Activity">
                <div class="nav-icon">
                    <i class="bi bi-plus-circle-fill"></i>
                </div>
                <span class="nav-text">Record Activity</span>
            </a>

            <a href="<?php echo $pages_prefix; ?>submissions_progress.php"
                class="nav-item <?php echo (in_array($current_page, ['submissions_progress.php', 'view_activity.php', 'edit_activity.php']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') ? 'active' : ''; ?>"
                data-tooltip="My Submissions">
                <div class="nav-icon">
                    <i class="bi bi-journal-check"></i>
                </div>
                <span class="nav-text">My Submissions</span>
            </a>

            <div class="nav-divider"></div>

            <a href="<?php echo $pages_prefix; ?>profile.php"
                class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" data-tooltip="My Profile">
                <div class="nav-icon">
                    <i class="bi bi-person-circle"></i>
                </div>
                <span class="nav-text">My Profile</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <?php if (!empty($_SESSION['profile_picture'])): ?>
                <img src="../<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="User" class="user-avatar">
            <?php else: ?>
                <div class="user-avatar-placeholder">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?: $_SESSION['username'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($_SESSION['position'] ?: 'Employee'); ?></span>
            </div>
        </div>
        <a href="<?php echo $pages_prefix; ?>logout.php" class="logout-btn-new" title="Log out">
            <i class="bi bi-power"></i>
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('mainSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle'); // Chevron
        const mobileToggle = document.getElementById('toggleSidebar');  // Top-bar Burger
        const overlay = document.getElementById('sidebarOverlay');
        const layout = document.querySelector('.admin-layout') || document.querySelector('.user-layout');

        function toggleDesktopCollapse() {
            sidebar.classList.toggle('collapsed');
            if (layout) layout.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        function toggleMobileMenu() {
            sidebar.classList.toggle('mobile-open');
            if (overlay) overlay.classList.toggle('show');
        }

        // Top Bar Toggle (Burger)
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function () {
                if (window.innerWidth > 992) {
                    toggleDesktopCollapse();
                } else {
                    toggleMobileMenu();
                }
            });
        }

        // Sidebar Internal Toggle (Chevron)
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                toggleDesktopCollapse();
            });
        }

        // Overlay Close
        if (overlay) {
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
            });
        }

        // Persistence & Initialization
        if (window.innerWidth > 992 && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            if (layout) layout.classList.add('sidebar-collapsed');
        }

        // Cleanup flash-prevention class
        document.documentElement.classList.remove('sidebar-initial-collapsed');
    });
</script>