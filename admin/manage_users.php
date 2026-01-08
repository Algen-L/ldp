<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_user'])) {
        $user_id = (int) $_POST['user_id'];
        $role = $_POST['role'];
        $office = trim($_POST['office_station']);
        $stmt = $pdo->prepare("UPDATE users SET role = ?, office_station = ? WHERE id = ?");
        if ($stmt->execute([$role, $office, $user_id])) {
            $message = "User updated successfully!";
            $messageType = "success";
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Updated User Record', "User ID: $user_id (Role: $role)", $_SERVER['REMOTE_ADDR']]);
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int) $_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $message = "User deleted successfully!";
                $messageType = "success";
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Deleted User', "User ID: $user_id removed.", $_SERVER['REMOTE_ADDR']]);
            }
        } else {
            $message = "You cannot delete yourself!";
            $messageType = "error";
        }
    }
}

$users = $pdo->query("SELECT id, username, full_name, office_station, role, created_at FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Management - Super Admin</title>
    <?php require 'includes/admin_head.php'; ?>
    <!-- Page Styles -->
    <link rel="stylesheet" href="css/pages/manage-users.css">
</head>

<body>
    <div class="dashboard-container">
        <?php require '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="passbook-container">
                <div class="header">
                    <h1>User Management</h1>
                    <p>Super Admin Exclusive Control</p>
                </div>
                <?php if ($message): ?>
                    <div class="msg <?php echo $messageType; ?>"><?php echo $message; ?></div><?php endif; ?>
                <div class="user-mgmt-card">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Office</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong><br><small>@<?php echo htmlspecialchars($u['username']); ?></small>
                                    </td>
                                    <form method="POST">
                                        <td><input type="text" name="office_station"
                                                value="<?php echo htmlspecialchars($u['office_station']); ?>"
                                                class="office-input"></td>
                                        <td><select name="role" class="role-select">
                                                <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>
                                                    User</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>
                                                    Admin</option>
                                                <option value="super_admin" <?php echo $u['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                            </select></td>
                                        <td><input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="update_user" class="btn-primary">Update</button>
                                            <?php if ($u['id'] != $_SESSION['user_id']): ?><button type="submit"
                                                    name="delete_user" class="btn-danger"
                                                    onclick="return confirm('Delete user?')">Delete</button><?php endif; ?>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>