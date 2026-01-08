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
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role']; // Store role for admin checks if needed
            if ($user['role'] === 'admin') {
                header("Location: pages/admin_dashboard.php");
            } else {
                header("Location: pages/home.php");
            }
            exit;
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
    <link rel="stylesheet" href="css/common.css">
    <link rel="stylesheet" href="css/passbook.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        .login-container {
            width: 400px;
            /* Smaller container for login */
            text-align: center;
        }
    </style>
</head>

<body class="auth-page">

    <div class="passbook-container login-container">
        <div class="header">
            <h1>L&D Passbook</h1>
            <p>Login to your account</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-error">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label style="text-align: left;">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label style="text-align: left;">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn">Login</button>
            <div class="link-text">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </form>
    </div>

</body>

</html>