<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageType = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station']);
    $position = trim($_POST['position']);
    $password = trim($_POST['password']);

    if (empty($full_name)) {
        $message = "Name is required.";
        $messageType = "error";
    } else {
        // Prepare update query
        $sql = "UPDATE users SET full_name = ?, office_station = ?, position = ?";
        $params = [$full_name, $office_station, $position];

        // Update password if provided
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $hashed_password;
        }

        $sql .= " WHERE id = ?";
        $params[] = $_SESSION['user_id'];

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            // Log admin profile update
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Updated Admin Profile', $_SERVER['REMOTE_ADDR']]);

            $message = "Admin profile updated successfully.";
            $messageType = "success";
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Error updating profile.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - LDP</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/passbook.css">
</head>

<body>

    <div class="dashboard-container">
        <div class="sidebar">
            <?php require '../includes/sidebar.php'; ?>
        </div>

        <div class="main-content">
            <div class="passbook-container">
                <div class="header">
                    <h1>Admin Profile</h1>
                    <p>Update your account details</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="full_name" class="form-control" required
                            value="<?php echo htmlspecialchars($user['full_name']); ?>">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Office/Station</label>
                        <input type="text" name="office_station" class="form-control"
                            value="<?php echo htmlspecialchars($user['office_station']); ?>">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control"
                            value="<?php echo htmlspecialchars($user['position']); ?>">
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Change Password</label>
                        <input type="password" name="password" class="form-control"
                            placeholder="Leave blank to keep current">
                    </div>

                    <button type="submit" class="btn">Update Admin Profile</button>
                </form>
            </div>
        </div>
    </div>

</body>

</html>