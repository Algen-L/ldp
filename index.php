<?php
session_start();
require 'includes/db.php';

$message = '';
$isRegistration = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        $isRegistration = true;
        $username = trim($_POST['reg_username']);
        $password = trim($_POST['reg_password']);
        $full_name = trim($_POST['full_name']);
        $office_station = trim($_POST['office_station'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $rating_period = trim($_POST['rating_period'] ?? '');
        $area_of_specialization = trim($_POST['area_of_specialization'] ?? '');
        $age = isset($_POST['age']) ? (int) $_POST['age'] : 0;
        $sex = trim($_POST['sex'] ?? '');

        if (empty($username) || empty($password) || empty($full_name)) {
            $message = "Please fill in all required fields.";
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $message = "Username already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, password, full_name, office_station, position, rating_period, area_of_specialization, age, sex, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$username, $hashed_password, $full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex])) {
                    $message = "Registration successful! Your account is pending HR verification.";
                    $isRegistration = false; // Switch back to login
                } else {
                    $message = "Something went wrong. Please try again.";
                }
            }
        }
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($password)) {
            $message = "Please enter both username and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (isset($user['is_active']) && $user['is_active'] == 0) {
                    $message = "Your account is pending HR verification. Please wait for approval.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['position'] = $user['position'];

                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Logged In', $_SERVER['REMOTE_ADDR']]);

                    if ($user['role'] === 'admin' || $user['role'] === 'super_admin' || $user['role'] === 'immediate_head' || $user['role'] === 'head_hr') {
                        header("Location: admin/dashboard.php");
                    } elseif ($user['role'] === 'hr') {
                        header("Location: hr/dashboard.php");
                    } else {
                        header("Location: user/home.php");
                    }
                    exit;
                }
            } else {
                $message = "Invalid username or password.";
            }
        }
    }
}

// Fetch Offices for Dropdown
try {
    $stmt_offices = $pdo->query("SELECT category, name, id FROM offices ORDER BY category, name");
    $offices_list = $stmt_offices->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $offices_list = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDP Passbook - Login/Register</title>
    <?php require 'includes/head.php'; ?>
    <link rel="stylesheet" href="css/pages/auth.css?v=<?php echo time(); ?>">
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">

</head>

<body class="auth-page">
    <div class="grid-background" id="gridBackground"></div>

    <div class="login-container <?php echo $isRegistration ? 'register-mode' : ''; ?>" id="authContainer">
        <div class="header">
            <div class="logo-container">
                <img src="assets/logo.png" alt="SDO Logo">
            </div>
            <h1 id="authTitle"><?php echo $isRegistration ? 'Create Account' : 'SDO L&D Passbook System'; ?></h1>
            <p id="authSubtitle">
                <?php echo $isRegistration ? 'Fill in your details to get started' : 'San Pedro Division Office'; ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'successful') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="loginSection" class="form-section <?php echo !$isRegistration ? 'active' : ''; ?>">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password"
                        required>
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
            <div class="footer-text">
                Don't have an account? <span class="toggle-link" onclick="toggleAuth(true)">Register here</span>
            </div>
        </div>

        <!-- Register Form -->
        <div id="registerSection" class="form-section <?php echo $isRegistration ? 'active' : ''; ?>">
            <form method="POST" action="">
                <input type="hidden" name="register" value="1">
                <div class="form-group">
                    <label>Full Name <span class="required-asterisk">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Username <span class="required-asterisk">*</span></label>
                        <input type="text" name="reg_username" class="form-control" placeholder="j.doe" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span class="required-asterisk">*</span></label>
                        <input type="password" name="reg_password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Office / Station</label>
                    <select name="office_station" id="office_select" class="form-control" required>
                        <option value="">Select your office...</option>
                        <?php if (!empty($offices_list)): ?>
                            <?php foreach ($offices_list as $category => $items): ?>
                                <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                    <?php foreach ($items as $office): ?>
                                        <option value="<?php echo htmlspecialchars($office['name']); ?>">
                                            <?php echo htmlspecialchars($office['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-grid grid-3">
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control" placeholder="Teacher I">
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="area_of_specialization" class="form-control" placeholder="Science">
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" class="form-control" placeholder="25">
                    </div>
                </div>

                <div class="form-grid grid-3">
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex" class="form-control">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label>Employee Number</label>
                        <input type="text" name="employee_number" class="form-control" placeholder="e.g. 1234567">
                    </div>
                </div>

                <button type="submit" class="btn">Register Account</button>
            </form>
            <div class="footer-text">
                Already have an account? <span class="toggle-link" onclick="toggleAuth(false)">Back to Login</span>
            </div>
        </div>

        <div class="footer-text auth-footer">
            Department of Education - San Pedro Division<br>
            <span class="dev-info">Developed by A.L and C.B</span>
        </div>
    </div>

    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        function toggleAuth(isReg) {
            const container = document.getElementById('authContainer');
            const loginSec = document.getElementById('loginSection');
            const regSec = document.getElementById('registerSection');
            const title = document.getElementById('authTitle');
            const subtitle = document.getElementById('authSubtitle');

            if (isReg) {
                container.classList.add('register-mode');
                loginSec.classList.remove('active');
                regSec.classList.add('active');
                title.innerText = 'Create Account';
                subtitle.innerText = 'Fill in your details to get started';
            } else {
                container.classList.remove('register-mode');
                loginSec.classList.add('active');
                regSec.classList.remove('active');
                title.innerText = 'SDO L&D Passbook System';
                subtitle.innerText = 'San Pedro Division Office';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            new TomSelect('#office_select', {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                },
                placeholder: "Type to search office...",
                maxOptions: 50
            });
        });

        // Create animated grid background
        const gridBg = document.getElementById('gridBackground');
        const tileSize = 100;
        const gap = 2;
        const cols = Math.ceil(window.innerWidth / (tileSize + gap)) + 1;
        const rows = Math.ceil(window.innerHeight / (tileSize + gap)) + 1;
        const totalTiles = cols * rows;

        gridBg.style.gridTemplateColumns = `repeat(${cols}, ${tileSize}px)`;
        gridBg.style.gridTemplateRows = `repeat(${rows}, ${tileSize}px)`;

        for (let i = 0; i < totalTiles; i++) {
            const tile = document.createElement('div');
            tile.className = 'grid-tile';
            gridBg.appendChild(tile);
        }

        const tiles = document.querySelectorAll('.grid-tile');

        function randomGlow() {
            const randomTile = tiles[Math.floor(Math.random() * tiles.length)];
            randomTile.classList.add('glow');
            setTimeout(() => {
                randomTile.classList.remove('glow');
            }, 2000);
        }

        setInterval(randomGlow, 400);

        gridBg.addEventListener('mousemove', (e) => {
            const rect = gridBg.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            tiles.forEach(tile => {
                const tileRect = tile.getBoundingClientRect();
                const tileCenterX = tileRect.left + tileRect.width / 2 - rect.left;
                const tileCenterY = tileRect.top + tileRect.height / 2 - rect.top;

                const distance = Math.sqrt(
                    Math.pow(x - tileCenterX, 2) + Math.pow(y - tileCenterY, 2)
                );

                if (distance < 150) {
                    tile.classList.add('active');
                } else {
                    tile.classList.remove('active');
                }
            });
        });

        gridBg.addEventListener('mouseleave', () => {
            tiles.forEach(tile => tile.classList.remove('active'));
        });
    </script>
</body>

</html>