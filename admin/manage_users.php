<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'hr')) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_user'])) {
        $user_id = (int) $_POST['user_id'];
        $office = trim($_POST['office_station']);

        if ($_SESSION['role'] === 'super_admin') {
            // Super Admin: Update Role AND Office
            $role = $_POST['role'];
            $stmt = $pdo->prepare("UPDATE users SET role = ?, office_station = ? WHERE id = ?");
            $success = $stmt->execute([$role, $office, $user_id]);
            $details = "User ID: $user_id (Role: $role, Office: $office)";
        } else {
            // HR: Update ONLY Office
            $stmt = $pdo->prepare("UPDATE users SET office_station = ? WHERE id = ?");
            $success = $stmt->execute([$office, $user_id]);
            $details = "User ID: $user_id (Office: $office)";
        }

        if ($success) {
            $message = "User updated successfully!";
            $messageType = "success";
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Updated User Record', $details, $_SERVER['REMOTE_ADDR']]);
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int) $_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) {
            // Check target user role first
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_role = $stmt->fetchColumn();

            $can_delete = false;

            if ($_SESSION['role'] === 'super_admin') {
                $can_delete = true;
            } elseif ($_SESSION['role'] === 'hr' && $target_role === 'user') {
                $can_delete = true;
            }

            if ($can_delete) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "User deleted successfully!";
                    $messageType = "success";
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$_SESSION['user_id'], 'Deleted User', "User ID: $user_id removed.", $_SERVER['REMOTE_ADDR']]);
                }
            } else {
                $message = "You do not have permission to delete this user.";
                $messageType = "danger";
            }
        } else {
            $message = "You cannot delete yourself!";
            $messageType = "danger";
        }
    } elseif (isset($_POST['toggle_active'])) {
        $user_id = (int) $_POST['user_id'];
        $new_status = (int) $_POST['new_status'];

        if ($user_id != $_SESSION['user_id']) {
            // Check target user role first
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_role = $stmt->fetchColumn();

            $can_toggle = false;

            if ($_SESSION['role'] === 'super_admin') {
                $can_toggle = true;
            } elseif ($_SESSION['role'] === 'hr' && $target_role === 'user') {
                $can_toggle = true;
            }

            if ($can_toggle) {
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $user_id])) {
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    $message = "User account {$status_text} successfully!";
                    $messageType = "success";
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$_SESSION['user_id'], 'Toggled User Status', "User ID: $user_id {$status_text}.", $_SERVER['REMOTE_ADDR']]);
                }
            } else {
                $message = "You do not have permission to modify this user.";
                $messageType = "danger";
            }
        } else {
            $message = "You cannot deactivate yourself!";
            $messageType = "danger";
        }
    }
}

// Handle Filtering
$search = trim($_GET['search'] ?? '');
$filter_role = trim($_GET['filter_role'] ?? '');
$filter_office = trim($_GET['filter_office'] ?? '');

$sql = "SELECT id, username, full_name, office_station, role, is_active, created_at FROM users WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_role) {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
}

if ($filter_office) {
    $sql .= " AND office_station LIKE ?";
    $params[] = "%$filter_office%";
}

$sql .= " ORDER BY full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Super Admin</title>
    <?php require 'includes/admin_head.php'; ?>
</head>

<body>
    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle" id="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="text-muted">Admin Panel</span>
                        <i class="bi bi-chevron-right separator"></i>
                        <h1 class="page-title">User Management</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <i class="bi bi-calendar3"></i>
                        <span><?php echo date('l, F d, Y'); ?></span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> fade show" role="alert">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="bi <?php echo $messageType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"
                                style="font-size: 1.25rem;"></i>
                            <div><?php echo $message; ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-bar">
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label>Search Personnel</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                class="filter-input" placeholder="Name or Username...">
                        </div>

                        <div class="filter-group">
                            <label>Filter by Role</label>
                            <select name="filter_role" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>L&D
                                    Personnel</option>
                                <option value="hr" <?php echo $filter_role === 'hr' ? 'selected' : ''; ?>>HR Personnel
                                </option>
                                <option value="immediate_head" <?php echo $filter_role === 'immediate_head' ? 'selected' : ''; ?>>Immediate Head</option>
                                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>System
                                    Admin</option>
                                <option value="super_admin" <?php echo $filter_role === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Office / Station</label>
                            <input type="text" name="filter_office"
                                value="<?php echo htmlspecialchars($filter_office); ?>" class="filter-input"
                                placeholder="e.g. ICT, Legal...">
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i> Apply Filter
                            </button>
                            <?php if ($search || $filter_role || $filter_office): ?>
                                <a href="manage_users.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="dashboard-card hover-elevate">
                    <div class="card-header">
                        <h2><i class="bi bi-people-fill text-gradient"></i> Registry of Personnel</h2>
                        <span class="result-count">Managing <?php echo count($users); ?> active accounts</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Personnel Full Name</th>
                                        <th>Official Office/Station</th>
                                        <th>Assign System Role</th>
                                        <th style="text-align: center;">Account Status</th>
                                        <th style="text-align: right;">Record Management</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-placeholder-sm">
                                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="cell-primary">
                                                            <?php echo htmlspecialchars($u['full_name']); ?>
                                                        </div>
                                                        <div class="cell-secondary" style="font-family: monospace;">
                                                            @<?php echo htmlspecialchars($u['username']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="position: relative;">
                                                    <i class="bi bi-building"
                                                        style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                                                    <input type="text" name="office_station"
                                                        form="form-<?php echo $u['id']; ?>"
                                                        value="<?php echo htmlspecialchars($u['office_station']); ?>"
                                                        class="filter-input" style="min-width: 220px; padding-left: 36px;">
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                                    <select name="role" form="form-<?php echo $u['id']; ?>"
                                                        class="filter-select" style="min-width: 150px;">
                                                        <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>L&D Personnel</option>
                                                        <option value="hr" <?php echo $u['role'] === 'hr' ? 'selected' : ''; ?>>HR
                                                            Personnel</option>
                                                        <option value="immediate_head" <?php echo $u['role'] === 'immediate_head' ? 'selected' : ''; ?>>Immediate Head</option>
                                                        <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>System Admin</option>
                                                        <option value="super_admin" <?php echo $u['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Administrator</option>
                                                    </select>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"
                                                        style="font-size: 0.85rem; padding: 6px 12px; font-weight: 500;">
                                                        <?php
                                                        $roleMap = [
                                                            'user' => 'L&D Personnel',
                                                            'hr' => 'HR Personnel',
                                                            'immediate_head' => 'Immediate Head',
                                                            'admin' => 'System Admin',
                                                            'super_admin' => 'Super Administrator'
                                                        ];
                                                        echo htmlspecialchars($roleMap[$u['role']] ?? ucfirst($u['role']));
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php
                                                $can_toggle = false;
                                                if ($u['id'] != $_SESSION['user_id']) {
                                                    if ($_SESSION['role'] === 'super_admin') {
                                                        $can_toggle = true;
                                                    } elseif ($_SESSION['role'] === 'hr' && $u['role'] === 'user') {
                                                        $can_toggle = true;
                                                    }
                                                }
                                                ?>
                                                <div
                                                    style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                                                    <?php if ($u['is_active']): ?>
                                                        <span class="badge bg-success"
                                                            style="font-size: 0.75rem; padding: 4px 10px;">
                                                            <i class="bi bi-check-circle"></i> Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"
                                                            style="font-size: 0.75rem; padding: 4px 10px;">
                                                            <i class="bi bi-x-circle"></i> Inactive
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($can_toggle): ?>
                                                        <form method="POST" style="display: inline; margin: 0;">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <input type="hidden" name="new_status"
                                                                value="<?php echo $u['is_active'] ? 0 : 1; ?>">
                                                            <button type="submit" name="toggle_active"
                                                                class="btn btn-sm <?php echo $u['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                                                style="padding: 4px 10px; font-size: 0.75rem;"
                                                                title="<?php echo $u['is_active'] ? 'Deactivate Account' : 'Activate Account'; ?>">
                                                                <i
                                                                    class="bi <?php echo $u['is_active'] ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                                                <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="text-align: right;">
                                                <form method="POST" id="form-<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                </form>
                                                <div class="action-buttons"
                                                    style="display: flex; gap: 8px; justify-content: flex-end;">
                                                    <button type="submit" name="update_user"
                                                        form="form-<?php echo $u['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-check2"></i> Save Changes
                                                    </button>
                                                    <?php
                                                    $show_delete = false;
                                                    if ($u['id'] != $_SESSION['user_id']) {
                                                        if ($_SESSION['role'] === 'super_admin') {
                                                            $show_delete = true;
                                                        } elseif ($_SESSION['role'] === 'hr' && $u['role'] === 'user') {
                                                            $show_delete = true;
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($show_delete): ?>
                                                        <button type="submit" name="delete_user"
                                                            form="form-<?php echo $u['id']; ?>"
                                                            class="btn btn-danger btn-sm btn-icon"
                                                            onclick="return confirm('CRITICAL: Permanently delete this personnel account? This cannot be undone.')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Privileged Access
                        Mode.</span></p>
            </footer>
        </div>
    </div>
</body>

</html>