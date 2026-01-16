<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'hr')) {
    header("Location: dashboard.php");
    exit;
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_user'])) {
        $user_id = (int) $_POST['user_id'];
        $office = trim($_POST['office_station']);

        if ($_SESSION['role'] === 'super_admin') {
            $role = $_POST['role'];
            $stmt = $pdo->prepare("UPDATE users SET role = ?, office_station = ? WHERE id = ?");
            $success = $stmt->execute([$role, $office, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET office_station = ? WHERE id = ?");
            $success = $stmt->execute([$office, $user_id]);
        }

        if ($success) {
            $_SESSION['toast'] = ['title' => 'Success', 'message' => 'User updated successfully!', 'type' => 'success'];
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Updated User Record', "User ID: $user_id", $_SERVER['REMOTE_ADDR']]);
            header("Location: manage_users.php");
            exit;
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int) $_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_role = $stmt->fetchColumn();

            $can_delete = false;
            if ($_SESSION['role'] === 'super_admin')
                $can_delete = true;
            elseif ($_SESSION['role'] === 'hr' && $target_role === 'user')
                $can_delete = true;

            if ($can_delete) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $_SESSION['toast'] = ['title' => 'Deleted', 'message' => 'User account permanently removed.', 'type' => 'success'];
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$_SESSION['user_id'], 'Deleted User', "User ID: $user_id removed.", $_SERVER['REMOTE_ADDR']]);
                    header("Location: manage_users.php");
                    exit;
                }
            } else {
                $_SESSION['toast'] = ['title' => 'Error', 'message' => 'Permission denied.', 'type' => 'error'];
            }
        } else {
            $_SESSION['toast'] = ['title' => 'Error', 'message' => 'You cannot delete yourself!', 'type' => 'error'];
        }
        header("Location: manage_users.php");
        exit;
    } elseif (isset($_POST['toggle_active'])) {
        $user_id = (int) $_POST['user_id'];
        $new_status = (int) $_POST['new_status'];

        if ($user_id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_role = $stmt->fetchColumn();

            $can_toggle = false;
            if ($_SESSION['role'] === 'super_admin')
                $can_toggle = true;
            elseif ($_SESSION['role'] === 'hr' && $target_role === 'user')
                $can_toggle = true;

            if ($can_toggle) {
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $user_id])) {
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    $_SESSION['toast'] = ['title' => 'Updated', 'message' => "Account $status_text successfully.", 'type' => 'success'];
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$_SESSION['user_id'], 'Toggled Status', "User ID: $user_id $status_text.", $_SERVER['REMOTE_ADDR']]);
                }
            }
        }
        header("Location: manage_users.php");
        exit;
    }
}

// Handle Filtering
$search = trim($_GET['search'] ?? '');
$filter_role = trim($_GET['filter_role'] ?? '');
$filter_office = trim($_GET['filter_office'] ?? '');

$sql = "SELECT u.id, u.username, u.full_name, u.office_station, u.role, u.is_active, u.created_at, u.profile_picture,
        creator.full_name as creator_name,
        (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id AND action = 'Logged In') as last_login
        FROM users u 
        LEFT JOIN users creator ON u.created_by = creator.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_role) {
    $sql .= " AND u.role = ?";
    $params[] = $filter_role;
}

if ($filter_office) {
    $sql .= " AND u.office_station LIKE ?";
    $params[] = "%$filter_office%";
}

$sql .= " ORDER BY u.full_name ASC";
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
    <?php require '../includes/admin_head.php'; ?>
    <style>
        /* Premium Delete Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .delete-modal {
            background: white;
            width: 100%;
            max-width: 400px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .delete-modal {
            transform: translateY(0);
        }

        .modal-header-danger {
            background: #fff1f2;
            padding: 30px;
            text-align: center;
        }

        .danger-icon-circle {
            width: 60px;
            height: 60px;
            background: #fee2e2;
            color: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 16px;
        }

        .modal-body {
            padding: 0 30px 30px;
            text-align: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .modal-text {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .modal-footer-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding: 0 30px 30px;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-confirm-delete {
            background: #ef4444;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
            transition: all 0.2s;
        }

        .btn-confirm-delete:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3);
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Personnel Management</h1>
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
                <div style="height: 1px;"></div>

                <!-- Redesigned Minimal Filter Section -->
                <div class="filter-bar"
                    style="padding: 24px; background: white; border-radius: 16px; margin-bottom: 24px;">
                    <form method="GET" class="filter-form"
                        style="display: flex; flex-wrap: wrap; gap: 24px; align-items: flex-end;">
                        <div class="filter-group" style="flex: 1; min-width: 200px;">
                            <label
                                style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; display: block;">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                class="filter-input" placeholder="Name or username..."
                                style="width: 100%; border-radius: 10px; height: 44px;">
                        </div>

                        <div class="filter-group" style="width: 200px;">
                            <label
                                style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; display: block;">Role</label>
                            <select name="filter_role" class="filter-select"
                                style="width: 100%; border-radius: 10px; height: 44px;">
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

                        <div class="filter-group" style="width: 200px;">
                            <label
                                style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; display: block;">Unit</label>
                            <input type="text" name="filter_office"
                                value="<?php echo htmlspecialchars($filter_office); ?>" class="filter-input"
                                placeholder="All Units" style="width: 100%; border-radius: 10px; height: 44px;">
                        </div>

                        <div class="filter-actions" style="display: flex; gap: 12px; margin-left: auto;">
                            <button type="submit" class="btn btn-primary"
                                style="height: 44px; padding: 0 24px; border-radius: 10px;">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <?php if ($search || $filter_role || $filter_office): ?>
                                <a href="manage_users.php" class="btn btn-secondary"
                                    style="height: 44px; display: flex; align-items: center; border-radius: 10px; padding: 0 20px;">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="dashboard-card hover-elevate"
                    style="border-radius: 16px; overflow: hidden; border: none; box-shadow: var(--shadow-sm);">
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive"
                            style="width: 100%; max-height: 380px; overflow-y: auto; overflow-x: hidden; position: relative;">
                            <table class="data-table"
                                style="border-collapse: collapse; margin-top: 0; width: 100%; table-layout: fixed;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th
                                            style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 20%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                            User</th>
                                        <th
                                            style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 10%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                            Role</th>
                                        <th
                                            style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; white-space: nowrap; width: 15%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                            Unit</th>
                                        <th
                                            style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; white-space: nowrap; width: 8%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                            Status</th>
                                        <th
                                            style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 14%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                            Last Login</th>
                                        <th
                                            style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 12%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                            Created</th>
                                        <th
                                            style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: right; white-space: nowrap; width: 21%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody style="background: white;">
                                    <?php foreach ($users as $u):
                                        $initial = strtoupper(substr($u['full_name'], 0, 1));
                                        $role_class = 'badge-role-' . $u['role'];
                                        $role_label = [
                                            'super_admin' => 'Super Admin',
                                            'admin' => 'Admin',
                                            'hr' => 'HR Personnel',
                                            'immediate_head' => 'Head',
                                            'user' => 'Staff'
                                        ][$u['role']] ?? ucfirst($u['role']);
                                        ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                            <td style="padding: 12px 20px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <?php if ($u['profile_picture']): ?>
                                                        <img src="../<?php echo htmlspecialchars($u['profile_picture']); ?>"
                                                            style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                                    <?php else: ?>
                                                        <div class="user-avatar-placeholder"
                                                            style="width: 38px; height: 38px; font-size: 0.9rem; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">
                                                            <?php echo $initial; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div style="min-width: 0;">
                                                        <div
                                                            style="font-weight: 700; color: #1e293b; font-size: 0.92rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                            <?php echo htmlspecialchars($u['full_name']); ?>
                                                        </div>
                                                        <div style="font-size: 0.78rem; color: #64748b; margin-top: 1px;">
                                                            <?php echo htmlspecialchars($u['username']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding: 12px 12px;">
                                                <span class="badge <?php echo $role_class; ?>"
                                                    style="font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 6px; white-space: nowrap;">
                                                    <?php echo $role_label; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 12px; text-align: center;">
                                                <div
                                                    style="font-weight: 600; color: #475569; font-size: 0.82rem; line-height: 1.3; max-width: 200px; margin: 0 auto;">
                                                    <?php
                                                    $unit = $u['office_station'];
                                                    if (stripos($unit, 'SGOD') !== false)
                                                        echo 'SGOD';
                                                    elseif (stripos($unit, 'OSDS') !== false)
                                                        echo 'OSDS';
                                                    elseif (stripos($unit, 'CID') !== false)
                                                        echo 'CID';
                                                    else
                                                        echo $unit ? htmlspecialchars($unit) : '-';
                                                    ?>
                                                </div>
                                            </td>
                                            <td style="padding: 12px 12px; text-align: center;">
                                                <span
                                                    style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; background: <?php echo $u['is_active'] ? '#ecfdf5' : '#f1f5f9'; ?>; color: <?php echo $u['is_active'] ? '#10b981' : '#64748b'; ?>; white-space: nowrap;">
                                                    <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 12px;">
                                                <div
                                                    style="font-size: 0.8rem; color: #334155; font-weight: 500; line-height: 1.2;">
                                                    <?php echo $u['last_login'] ? date('M d, Y', strtotime($u['last_login'])) . '<br><small style="color:#94a3b8">' . date('g:i A', strtotime($u['last_login'])) . '</small>' : 'Never'; ?>
                                                </div>
                                            </td>
                                            <td style="padding: 12px 12px;">
                                                <div
                                                    style="font-size: 0.8rem; color: #334155; font-weight: 600; line-height: 1.2;">
                                                    <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                                </div>
                                                <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 2px;">
                                                    <?php echo htmlspecialchars($u['creator_name'] ?: 'System Admin'); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 12px 12px; text-align: right;">
                                                <div
                                                    style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                                    <a href="edit_user.php?id=<?php echo $u['id']; ?>"
                                                        class="btn btn-secondary btn-sm"
                                                        style="border-radius: 6px; padding: 4px 12px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.8rem; height: 32px;">Edit</a>

                                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                        <form method="POST" style="margin: 0;">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <input type="hidden" name="new_status"
                                                                value="<?php echo $u['is_active'] ? 0 : 1; ?>">
                                                            <button type="submit" name="toggle_active"
                                                                class="btn btn-secondary btn-sm"
                                                                style="border-radius: 6px; padding: 4px 12px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.8rem; height: 32px;">
                                                                <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>

                                                        <button type="button"
                                                            onclick="confirmAction('delete_user', <?php echo $u['id']; ?>, '<?php echo addslashes($u['full_name']); ?>')"
                                                            class="btn btn-secondary btn-sm"
                                                            style="border-radius: 6px; width: 32px; height: 32px; padding: 0; background: white; border: 1px solid #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center;">
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
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
            </footer>
        </div>
    </div>
    <!-- Custom Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="delete-modal">
            <div class="modal-header-danger">
                <div class="danger-icon-circle">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
            <div class="modal-body">
                <h3 class="modal-title">Confirm Deletion</h3>
                <p class="modal-text">Are you sure you want to delete <strong id="deleteTargetName"></strong>? This
                    action will permanently remove all associated data and cannot be undone.</p>
            </div>
            <div class="modal-footer-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <form id="deleteForm" method="POST" style="margin: 0;">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" name="delete_user" class="btn-confirm-delete">Delete Account</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmAction(action, userId, fullName) {
            if (action === 'delete_user') {
                document.getElementById('deleteTargetName').textContent = fullName;
                document.getElementById('deleteUserId').value = userId;
                const modal = document.getElementById('deleteModal');
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('active'), 10);
            }
        }

        function closeModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('active');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>

</html>