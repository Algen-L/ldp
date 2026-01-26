<?php
session_start();
require '../includes/init_repos.php';

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
            $success = $userRepo->updateUserRole($user_id, $role, $office);
        } else {
            $success = $userRepo->updateUserProfile($user_id, ['office_station' => $office]);
        }

        if ($success) {
            $_SESSION['toast'] = ['title' => 'Success', 'message' => 'User updated successfully!', 'type' => 'success'];
            $logRepo->logAction($_SESSION['user_id'], 'Updated User Record', "User ID: $user_id");
            header("Location: manage_users.php");
            exit;
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int) $_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) {
            $target_user = $userRepo->getUserById($user_id);
            $target_role = $target_user['role'] ?? null;

            $can_delete = false;
            if ($_SESSION['role'] === 'super_admin')
                $can_delete = true;
            elseif ($_SESSION['role'] === 'hr' && $target_role === 'user')
                $can_delete = true;

            if ($can_delete) {
                if ($userRepo->deleteUser($user_id)) {
                    $_SESSION['toast'] = ['title' => 'Deleted', 'message' => 'User account permanently removed.', 'type' => 'success'];
                    $logRepo->logAction($_SESSION['user_id'], 'Deleted User', "User ID: $user_id removed.");
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
            $target_user = $userRepo->getUserById($user_id);
            $target_role = $target_user['role'] ?? null;

            $can_toggle = false;
            if ($_SESSION['role'] === 'super_admin')
                $can_toggle = true;
            elseif ($_SESSION['role'] === 'hr' && $target_role === 'user')
                $can_toggle = true;

            if ($can_toggle) {
                if ($userRepo->toggleUserStatus($user_id, $new_status)) {
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    $_SESSION['toast'] = ['title' => 'Updated', 'message' => "Account $status_text successfully.", 'type' => 'success'];
                    $logRepo->logAction($_SESSION['user_id'], 'Toggled Status', "User ID: $user_id $status_text.");
                }
            }
        }
        header("Location: manage_users.php");
        exit;
    } elseif (isset($_POST['approve_registration'])) {
        $user_id = (int) $_POST['user_id'];
        if ($userRepo->activateUser($user_id)) {
            $_SESSION['toast'] = ['title' => 'Approved', 'message' => 'User registration approved!', 'type' => 'success'];
            $logRepo->logAction($_SESSION['user_id'], 'Approved Registration', "User ID: $user_id");
        }
        header("Location: manage_users.php");
        exit;
    } elseif (isset($_POST['reject_registration'])) {
        $user_id = (int) $_POST['user_id'];
        if ($userRepo->deleteUser($user_id)) {
            $_SESSION['toast'] = ['title' => 'Rejected', 'message' => 'Registration rejected and user deleted.', 'type' => 'success'];
            $logRepo->logAction($_SESSION['user_id'], 'Rejected Registration', "User ID: $user_id");
        }
        header("Location: manage_users.php");
        exit;
    }
}

// View State Management
$view = $_GET['view'] ?? 'active';
$target_id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Handle Filtering (only for active view)
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'role' => trim($_GET['filter_role'] ?? ''),
    'office' => trim($_GET['filter_office'] ?? '')
];

$users = ($view === 'active') ? $userRepo->getUsersForManagement($filters) : [];
$pending_users = $userRepo->getPendingUsers();
$target_user = ($view === 'details' && $target_id) ? $userRepo->getUserById($target_id) : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Super Admin</title>
    <?php require '../includes/admin_head.php'; ?>
    <style>
        /* Multi-View Tab Styles */
        .management-tabs {
            display: flex;
            gap: 8px;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 14px;
            margin-bottom: 24px;
            width: fit-content;
        }

        .tab-item {
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tab-item:hover {
            color: var(--primary);
            background: rgba(255, 255, 255, 0.5);
        }

        .tab-item.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .tab-badge {
            background: #f59e0b;
            color: white;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 0.75rem;
        }

        /* Detail View Styles */
        .details-container {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .detail-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            border: 1.5px solid #f1f5f9;
            overflow: hidden;
        }

        .detail-header {
            padding: 32px;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-body {
            padding: 40px;
        }

        .info-grid-modern {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 32px;
        }

        .info-block {
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-weight: 700;
            margin-bottom: 16px;
            transition: color 0.2s;
            width: fit-content;
        }

        .back-btn:hover {
            color: var(--primary);
        }

        /* Verification List Styles */
        .ver-item {
            display: grid;
            grid-template-columns: 2fr 1.8fr 1.2fr 1fr;
            padding: 22px 28px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            cursor: pointer;
        }

        .ver-item:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 20px rgba(15, 76, 117, 0.12);
            transform: translateY(-2px);
            background: #fdfdfd;
        }

        .ver-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .ver-avatar {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(15, 76, 117, 0.2);
        }

        .ver-name {
            font-weight: 800;
            color: #1e293b;
            font-size: 1.05rem;
            display: block;
            margin-bottom: 2px;
        }

        .ver-handle {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 600;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .ver-info-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .ver-info-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: #334155;
            line-height: 1.4;
        }

        .ver-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .ver-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1.2rem;
        }

        .ver-btn-approve {
            background: #ecfdf5;
            color: #10b981;
            border: 1px solid #dcfce7;
        }

        .ver-btn-approve:hover {
            background: #10b981;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .ver-btn-reject {
            background: #fff1f2;
            color: #ef4444;
            border: 1px solid #fee2e2;
        }

        .ver-btn-reject:hover {
            background: #ef4444;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
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
                        <h1 class="page-title">
                            Personnel Management 
                            <span style="color: #94a3b8; font-weight: 500; margin-left: 8px;">
                                <?php if ($view === 'active'): ?>
                                    / Active Personnel
                                <?php elseif ($view === 'verification'): ?>
                                    / Pending Requests
                                <?php elseif ($view === 'details'): ?>
                                    / Verification / <?php echo htmlspecialchars($target_user['full_name']); ?>
                                <?php endif; ?>
                            </span>
                        </h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <div class="time-section">
                            <span id="real-time-clock"><?php echo date('h:i:s A'); ?></span>
                        </div>
                        <div class="date-section">
                            <i class="bi bi-calendar3"></i>
                            <span><?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <div class="management-tabs">
                    <a href="manage_users.php?view=active" class="tab-item <?php echo $view === 'active' ? 'active' : ''; ?>">
                        <i class="bi bi-people-fill"></i> Active Personnel
                    </a>
                    <a href="manage_users.php?view=verification" class="tab-item <?php echo $view === 'verification' ? 'active' : ''; ?>">
                        <i class="bi bi-person-check-fill"></i> Pending Requests
                        <?php if (count($pending_users) > 0): ?>
                                <span class="tab-badge"><?php echo count($pending_users); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div style="height: 1px;"></div>

                <?php if ($view === 'active'): ?>
                        <!-- Redesigned High-Fidelity Filter Bar -->
                        <div class="filter-bar"
                            style="padding: 16px; background: white; border-radius: 12px; margin-bottom: 24px; box-shadow: var(--shadow-sm); border: 1.5px solid #f1f5f9;">
                            <form method="GET" class="filter-form"
                                style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: nowrap;">
                                <input type="hidden" name="view" value="active">
                                <!-- Search Field with Icon -->
                                <div class="filter-item" style="position: relative; flex: 1.5; min-width: 0;">
                                    <i class="bi bi-search"
                                        style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem;"></i>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>"
                                        placeholder="Search entries..."
                                        style="width: 100%; height: 42px; padding: 0 12px 0 42px; border: 1.2px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; color: #475569; font-weight: 500; background: #fafbfc; outline: none; transition: all 0.2s;">
                                </div>

                                <!-- Role Field -->
                                <div class="filter-item" style="position: relative; flex: 1;">
                                    <select name="filter_role"
                                        style="width: 100%; height: 42px; padding: 0 40px 0 16px; border: 1.2px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; color: #475569; font-weight: 600; background: white; appearance: none; cursor: pointer; outline: none;">
                                        <option value="">All Personnel</option>
                                        <option value="user" <?php echo $filters['role'] === 'user' ? 'selected' : ''; ?>>Staff
                                        </option>
                                        <option value="hr" <?php echo $filters['role'] === 'hr' ? 'selected' : ''; ?>>HR Personnel
                                        </option>
                                        <option value="immediate_head" <?php echo $filters['role'] === 'immediate_head' ? 'selected' : ''; ?>>Immediate Head</option>
                                        <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>System
                                            Admin</option>
                                        <option value="super_admin" <?php echo $filters['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                    </select>
                                    <i class="bi bi-chevron-down"
                                        style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 0.8rem;"></i>
                                </div>

                                <!-- Unit Field -->
                                <div class="filter-item" style="position: relative; flex: 1;">
                                    <input type="text" name="filter_office"
                                        value="<?php echo htmlspecialchars($filters['office']); ?>" placeholder="All Units"
                                        style="width: 100%; height: 42px; padding: 0 12px; border: 1.2px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; color: #475569; font-weight: 500; background: white; outline: none;">
                                </div>

                                <!-- Apply Button -->
                                <div class="filter-actions" style="display: flex; gap: 8px; align-items: center;">
                                    <button type="submit" class="btn-apply"
                                        style="height: 42px; padding: 0 24px; background: #0f4c75; color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 10px rgba(15, 76, 117, 0.2);">
                                        <i class="bi bi-funnel-fill" style="font-size: 0.85rem;"></i> Apply
                                    </button>
                                    <?php if ($filters['search'] || $filters['role'] || $filters['office']): ?>
                                            <a href="manage_users.php?view=active" class="btn-clear"
                                                style="height: 42px; width: 42px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #64748b; border-radius: 10px; border: none; text-decoration: none; transition: all 0.2s;">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="dashboard-card hover-elevate"
                            style="border-radius: 16px; overflow: hidden; border: none; box-shadow: var(--shadow-sm);">
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive"
                                    style="width: 100%; max-height: 500px; overflow-y: auto; overflow-x: hidden; position: relative;">
                                    <table class="data-table"
                                        style="border-collapse: collapse; margin-top: 0; width: 100%; table-layout: fixed;">
                                        <thead style="background: #f8fafc;">
                                            <tr>
                                                <th style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 22%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">User</th>
                                                <th style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 12%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Role</th>
                                                <th style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; white-space: nowrap; width: 15%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Unit</th>
                                                <th style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; white-space: nowrap; width: 10%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Status</th>
                                                <th style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 15%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Last Login</th>
                                                <th style="font-size: 0.68rem; font-weight: 700; color: #94a3b8; padding: 14px 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: right; white-space: nowrap; width: 16%; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody style="background: white;">
                                            <?php if (empty($users)): ?>
                                                    <tr><td colspan="6" style="padding: 40px; text-align: center; color: #94a3b8;">No staff records found match your criteria.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($users as $u):
                                                $initial = strtoupper(substr($u['full_name'], 0, 1));
                                                $role_class = 'badge-role-' . $u['role'];
                                                $role_label = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'hr' => 'HR Personnel', 'immediate_head' => 'Head', 'user' => 'Staff'][$u['role']] ?? ucfirst($u['role']);
                                                ?>
                                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                                        <td style="padding: 12px 20px;">
                                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                                <?php if ($u['profile_picture']): ?>
                                                                        <img src="../<?php echo htmlspecialchars($u['profile_picture']); ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                                                <?php else: ?>
                                                                        <div class="user-avatar-placeholder" style="width: 38px; height: 38px; font-size: 0.9rem; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;"><?php echo $initial; ?></div>
                                                                <?php endif; ?>
                                                                <div><div style="font-weight: 700; color: #1e293b; font-size: 0.92rem;"><?php echo htmlspecialchars($u['full_name']); ?></div><div style="font-size: 0.78rem; color: #64748b;">@<?php echo htmlspecialchars($u['username']); ?></div></div>
                                                            </div>
                                                        </td>
                                                        <td><span class="badge <?php echo $role_class; ?>" style="font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 6px;"><?php echo $role_label; ?></span></td>
                                                        <td style="text-align: center;"><div style="font-weight: 600; color: #475569; font-size: 0.82rem;"><?php echo $u['office_station'] ?: '-'; ?></div></td>
                                                        <td style="text-align: center;"><span style="padding: 3px 10px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; background: <?php echo $u['is_active'] ? '#ecfdf5' : '#f1f5f9'; ?>; color: <?php echo $u['is_active'] ? '#10b981' : '#64748b'; ?>;"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                                        <td><div style="font-size: 0.8rem; color: #334155; font-weight: 500;"><?php echo $u['last_login'] ? date('M d, Y', strtotime($u['last_login'])) : 'Never'; ?></div></td>
                                                        <td style="text-align: right;">
                                                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                                <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-secondary btn-sm" style="border-radius: 6px; padding: 4px 12px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.8rem; height: 32px;">Edit</a>
                                                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                                        <button type="button" onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo addslashes($u['full_name']); ?>')" class="btn btn-secondary btn-sm" style="border-radius: 6px; width: 32px; height: 32px; border: 1px solid #fee2e2; color: #ef4444; background: white;"><i class="bi bi-trash"></i></button>
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

                <?php elseif ($view === 'verification'): ?>
                        <div class="verification-view" style="animation: fadeIn 0.3s ease;">
                            <?php if (empty($pending_users)): ?>
                                    <div style="text-align: center; padding: 80px 40px; background: white; border-radius: 24px; box-shadow: var(--shadow-sm);">
                                        <i class="bi bi-check2-all" style="font-size: 4rem; color: #cbd5e1;"></i>
                                        <h2 style="margin-top: 24px; color: #1e293b; font-weight: 800;">All Clear!</h2>
                                        <p style="color: #64748b; font-size: 1.1rem;">There are no new registration requests to verify.</p>
                                    </div>
                            <?php else: ?>
                                    <div class="verification-list" style="display: grid; gap: 16px;">
                                        <?php foreach ($pending_users as $p): ?>
                                                <div class="ver-item" onclick="location.href='manage_users.php?view=details&id=<?php echo $p['id']; ?>'">
                                                    <div class="ver-user">
                                                        <div class="ver-avatar"><?php echo strtoupper(substr($p['full_name'], 0, 1)); ?></div>
                                                        <div><span class="ver-name"><?php echo htmlspecialchars($p['full_name']); ?></span><span class="ver-handle">@<?php echo htmlspecialchars($p['username']); ?></span></div>
                                                    </div>
                                                    <div><span class="ver-info-label">Office</span><span class="ver-info-value"><?php echo htmlspecialchars($p['office_station']); ?></span></div>
                                                    <div><span class="ver-info-label">Position</span><span class="ver-info-value"><?php echo htmlspecialchars($p['position'] ?: 'Staff'); ?></span></div>
                                                    <div class="ver-actions" onclick="event.stopPropagation()">
                                                        <form method="POST" style="margin: 0; display: inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                                            <button type="submit" name="approve_registration" class="ver-btn ver-btn-approve" title="Approve"><i class="bi bi-check-lg"></i></button>
                                                        </form>
                                                        <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Reject this registration?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                                            <button type="submit" name="reject_registration" class="ver-btn ver-btn-reject" title="Reject"><i class="bi bi-trash3-fill"></i></button>
                                                        </form>
                                                    </div>
                                                </div>
                                        <?php endforeach; ?>
                                    </div>
                            <?php endif; ?>
                        </div>

                <?php elseif ($view === 'details' && $target_user): ?>
                        <div class="details-container">
                            <a href="manage_users.php?view=verification" class="back-btn"><i class="bi bi-arrow-left"></i> Back to Verification Requests</a>
                            <div class="detail-card">
                                <div class="detail-header">
                                    <div style="display: flex; align-items: center; gap: 24px;">
                                        <div class="ver-avatar" style="width: 80px; height: 80px; font-size: 2rem; border-radius: 20px; box-shadow: 0 8px 16px rgba(0,0,0,0.1);"><?php echo strtoupper(substr($target_user['full_name'], 0, 1)); ?></div>
                                        <div>
                                            <h2 style="margin: 0; font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px;"><?php echo htmlspecialchars($target_user['full_name']); ?></h2>
                                            <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">@<?php echo htmlspecialchars($target_user['username']); ?></span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 12px;">
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="user_id" value="<?php echo $target_user['id']; ?>">
                                            <button type="submit" name="reject_registration" class="btn-cancel" style="background: #fee2e2; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.1); padding: 12px 24px;">Reject Account</button>
                                        </form>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="user_id" value="<?php echo $target_user['id']; ?>">
                                            <button type="submit" name="approve_registration" class="btn-confirm-delete" style="background: #10b981; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); padding: 12px 24px;">Authorize Account</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="detail-body">
                                    <div class="info-grid-modern">
                                        <div class="info-block"><span class="info-label">Office / Station</span><div class="info-value"><?php echo htmlspecialchars($target_user['office_station']); ?></div></div>
                                        <div class="info-block"><span class="info-label">Current Position</span><div class="info-value"><?php echo htmlspecialchars($target_user['position'] ?: 'Not Specified'); ?></div></div>
                                        <div class="info-block"><span class="info-label">Area of Specialization</span><div class="info-value"><?php echo htmlspecialchars($target_user['area_of_specialization'] ?: 'Not Specified'); ?></div></div>
                                        <div class="info-block"><span class="info-label">Rating Period</span><div class="info-value"><?php echo htmlspecialchars($target_user['rating_period'] ?: 'Not Specified'); ?></div></div>
                                        <div class="info-block"><span class="info-label">Age</span><div class="info-value"><?php echo $target_user['age'] ?: 'Not Specified'; ?></div></div>
                                        <div class="info-block"><span class="info-label">Sex</span><div class="info-value"><?php echo htmlspecialchars($target_user['sex'] ?: 'Not Specified'); ?></div></div>
                                        <div class="info-block"><span class="info-label">Registration IP</span><div class="info-value"><?php echo $_SERVER['REMOTE_ADDR']; ?></div></div>
                                        <div class="info-block"><span class="info-label">Registration Date</span><div class="info-value"><?php echo date('F j, Y, g:i A', strtotime($target_user['created_at'])); ?></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                <?php endif; ?>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
            </footer>
        </div>
    </div>
    <!-- Deletion Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="delete-modal" style="background: white; max-width: 400px; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="modal-header-danger" style="background: #fff1f2; padding: 30px; text-align: center;">
                <div class="danger-icon-circle" style="width: 60px; height: 60px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 16px;"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
            <div class="modal-body" style="padding: 0 30px 30px; text-align: center;">
                <h3 class="modal-title">Confirm Deletion</h3>
                <p class="modal-text">Are you sure you want to delete <strong id="deleteTargetName"></strong>? This action will permanently remove all associated data.</p>
            </div>
            <div class="modal-footer-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <form id="deleteForm" method="POST" style="margin: 0;">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" name="delete_user" class="btn-confirm-delete">Delete Account</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(userId, fullName) {
            document.getElementById('deleteTargetName').textContent = fullName;
            document.getElementById('deleteUserId').value = userId;
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('active');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        window.onclick = function (event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeModal(event.target.id);
            }
        }
    </script>
</body>
</html>
