<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_admin_dir = ($current_dir === 'admin');
$is_hr_dir = ($current_dir === 'hr');
$is_pages_dir = ($current_dir === 'pages');
$is_user_dir = ($current_dir === 'user');

// Determine path to root
$to_root = ($is_admin_dir || $is_hr_dir || $is_pages_dir || $is_user_dir) ? '../' : '';

// Define prefixes
$admin_prefix = $is_admin_dir ? '' : $to_root . 'admin/';
$pages_prefix = $is_pages_dir ? '' : $to_root . 'pages/';

// Fallback: If $user is not set (e.g. on admin pages), fetch it
if (!isset($user) && isset($_SESSION['user_id'])) {
    if (isset($pdo)) {
        $stmt_sidebar_user = $pdo->prepare("SELECT full_name, office_station, position, profile_picture FROM users WHERE id = ?");
        $stmt_sidebar_user->execute([$_SESSION['user_id']]);
        $fetched_user = $stmt_sidebar_user->fetch(PDO::FETCH_ASSOC);
        if ($fetched_user) {
            $user = $fetched_user;
        }
    }
}
?>
<script>
    // Immediate execution to prevent FOUC (Flash of Unstyled Content)
    (function () {
        try {
            if (window.innerWidth > 992 && localStorage.getItem('sidebarCollapsed') === 'true') {
                document.documentElement.classList.add('sidebar-initial-collapsed');
            }
        } catch (e) {
            console.error('Sidebar preference error', e);
        }
    })();
</script>
<?php // Sidebar no longer manages its own CSS/JS links; they are handled in head/admin_head PHP includes ?>

<div id="toast-container"></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="<?php echo $to_root; ?>assets/LogoLDP.png" alt="LDP Logo" class="logo-img">
            <div class="logo-text">
                <span class="logo-title">Learning & Development</span>
                <span class="logo-subtitle">Passbook System</span>
            </div>
        </div>
    </div>

    <div class="sidebar-nav">
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'immediate_head' || $_SESSION['role'] === 'head_hr'): ?>
            <?php
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

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
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
            <?php endif; ?>


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

            <?php if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'head_hr'): ?>
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

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'immediate_head' || $_SESSION['role'] === 'head_hr'): ?>
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
                    class="nav-text"><?php echo ($_SESSION['role'] === 'immediate_head' || $_SESSION['role'] === 'head_hr') ? 'My Profile' : 'Admin Profile'; ?></span>
            </a>

        <?php else: // HR and Regular Users ?>
            <?php if ($_SESSION['role'] === 'hr'): ?>
                <a href="<?php echo $pages_prefix; ?>../hr/dashboard.php"
                    class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" data-tooltip="Dashboard">
                    <div class="nav-icon">
                        <i class="bi bi-house-door-fill"></i>
                    </div>
                    <span class="nav-text">My Dashboard</span>
                </a>
            <?php else: ?>
                <a href="<?php echo $to_root; ?>user/home.php"
                    class="nav-item <?php echo ($current_page == 'home.php') ? 'active' : ''; ?>" data-tooltip="Dashboard">
                    <div class="nav-icon">
                        <i class="bi bi-house-door-fill"></i>
                    </div>
                    <span class="nav-text">My Dashboard</span>
                </a>
            <?php endif; ?>

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

            <?php if ($_SESSION['role'] === 'hr'): ?>
                <a href="<?php echo $pages_prefix; ?>../hr/profile.php"
                    class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" data-tooltip="My Profile">
                    <div class="nav-icon">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <span class="nav-text">My Profile</span>
                </a>
            <?php else: ?>
                <a href="<?php echo $to_root; ?>user/profile.php"
                    class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" data-tooltip="My Profile">
                    <div class="nav-icon">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <span class="nav-text">My Profile</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <?php
            // Use $user if available (from parent script), otherwise use session
            $display_pic = isset($user['profile_picture']) ? $user['profile_picture'] : (isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '');
            $display_name = isset($user['full_name']) ? $user['full_name'] : (isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '');
            $display_role = isset($user['position']) ? $user['position'] : (isset($_SESSION['position']) ? $_SESSION['position'] : 'Employee');
            ?>
            <?php if (!empty($display_pic)): ?>
                <img src="<?php echo $to_root . htmlspecialchars($display_pic); ?>" alt="User" class="user-avatar">
            <?php else: ?>
                <div class="user-avatar-placeholder">
                    <?php echo strtoupper(substr($display_name ?: $_SESSION['username'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($display_name); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($display_role); ?></span>
            </div>
        </div>
        <a href="<?php echo $to_root; ?>includes/logout.php" class="logout-btn-new" title="Log out">
            <i class="bi bi-power"></i>
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('mainSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle'); // Internal Chevron
        const overlay = document.getElementById('sidebarOverlay');
        const layout = document.querySelector('.admin-layout') || document.querySelector('.user-layout') || document.querySelector('.app-layout');

        function toggleDesktopCollapse() {
            sidebar.classList.toggle('collapsed');
            if (layout) {
                layout.classList.toggle('sidebar-collapsed');
            }
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        function toggleMobileMenu() {
            sidebar.classList.toggle('mobile-open');
            if (overlay) overlay.classList.toggle('show');
        }

        // Logic to inject/bind the Burger Button (Top-bar Burger)
        function initBurgerToggle() {
            let mobileToggle = document.getElementById('toggleSidebar');

            // If button doesn't exist, try to inject it into .top-bar-left
            if (!mobileToggle) {
                const topBarLeft = document.querySelector('.top-bar-left');
                if (topBarLeft) {
                    mobileToggle = document.createElement('button');
                    mobileToggle.className = 'mobile-menu-toggle';
                    mobileToggle.id = 'toggleSidebar';
                    mobileToggle.innerHTML = '<i class="bi bi-list"></i>';
                    topBarLeft.prepend(mobileToggle);
                }
            }

            if (mobileToggle) {
                mobileToggle.addEventListener('click', function () {
                    if (window.innerWidth > 992) {
                        toggleDesktopCollapse();
                    } else {
                        toggleMobileMenu();
                    }
                });
            }
        }

        initBurgerToggle();

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
            if (layout) {
                layout.classList.add('sidebar-collapsed');
            }
        }

        // Cleanup flash-prevention class
        document.documentElement.classList.remove('sidebar-initial-collapsed');
    });
</script>