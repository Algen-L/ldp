<?php
session_start();
require 'includes/db.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check if account is active
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                $message = "Your account has been deactivated. Please contact the administrator.";
            } else {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role']; // Store role for admin checks if needed
                $_SESSION['position'] = $user['position'];

                // Log successful login
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                $logStmt->execute([$user['id'], 'Logged In', $_SERVER['REMOTE_ADDR']]);

                if ($user['role'] === 'admin' || $user['role'] === 'super_admin' || $user['role'] === 'immediate_head') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] === 'hr') {
                    header("Location: pages/home.php");
                } else {
                    header("Location: pages/home.php");
                }
                exit;
            }
        } else {
            $message = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LDP Passbook</title>
    <?php require 'includes/head.php'; ?>
    <link rel="stylesheet" href="css/pages/auth.css?v=<?php echo time(); ?>">
</head>

<body class="auth-page">

    <div class="login-container">
        <div class="header">
            <div class="logo-container">
                <img src="assets/logo.png" alt="SDO Logo">
            </div>
            <h1>SDO L&D Passbook System</h1>
            <p>San Pedro Division Office - Learning & Development</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-error">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username / Email Address</label>
                <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="footer-text">
            Department of Education - San Pedro Division<br>
            <span style="font-size: 0.8em; opacity: 0.8;">Developed by Algen D. Loveres and Cedrick V. Bacaresas</span>
        </div>
    </div>

</body>

</html>