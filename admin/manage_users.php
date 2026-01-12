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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(226, 232, 240, 0.8);
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            --accent-blue: #3b82f6;
            --accent-orange: #f97316;
            --accent-red: #ef4444;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
        }

        .passbook-container {
            animation: fadeIn 0.5s ease-out;
            width: 1400px;
            max-width: 98%;
            margin: 0 auto;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #1e293b;
            border-left: 5px solid var(--accent-orange);
            padding-left: 15px;
        }

        .header p {
            color: #64748b;
            font-weight: 400;
            padding-left: 20px;
        }

        /* Table Styling */
        .styled-table {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-top: 25px;
        }

        .styled-table thead th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            padding: 16px 20px;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }

        .styled-table thead th:first-child {
            border-left: 3px solid var(--accent-orange);
        }

        .styled-table tbody tr {
            transition: all 0.2s ease;
        }

        .styled-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .styled-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .office-input,
        .role-select {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            color: #1e293b;
            transition: all 0.2s ease;
            outline: none;
            width: 100%;
        }

        .office-input:focus,
        .role-select:focus {
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .btn-update {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(249, 115, 22, 0.2);
        }

        .btn-update:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -2px rgba(249, 115, 22, 0.3);
        }

        .btn-delete {
            background: #fff;
            color: var(--accent-red);
            border: 1.5px solid #fee2e2;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: 5px;
        }

        .btn-delete:hover {
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .msg {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .msg.success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .msg.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .user-info .name {
            color: #1e293b;
            font-weight: 700;
            font-size: 0.95rem;
            display: block;
        }

        .user-info .user {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }
    </style>
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
                    <div class="msg <?php echo $messageType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="card" style="padding: 0; background: transparent; border: none; box-shadow: none;">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>User Information</th>
                                <th style="width: 300px;">Assigned Office / Station</th>
                                <th style="width: 200px;">Access Level</th>
                                <th style="width: 200px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <span class="name"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                            <span class="user">@<?php echo htmlspecialchars($u['username']); ?></span>
                                        </div>
                                    </td>
                                    <form method="POST">
                                        <td>
                                            <input type="text" name="office_station"
                                                value="<?php echo htmlspecialchars($u['office_station']); ?>"
                                                class="office-input">
                                        </td>
                                        <td>
                                            <select name="role" class="role-select">
                                                <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>
                                                    User</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>
                                                    Admin</option>
                                                <option value="super_admin" <?php echo $u['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                            </select>
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="update_user" class="btn-update">Update</button>
                                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                <button type="submit" name="delete_user" class="btn-delete"
                                                    onclick="return confirm('Permanentely delete this user account?')">Delete</button>
                                            <?php endif; ?>
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