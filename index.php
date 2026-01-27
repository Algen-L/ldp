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
    <style>
        .login-container {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-container.register-mode {
            width: 850px;
            /* Slightly wider for registration */
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .toggle-link {
            color: var(--primary-blue);
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
        }

        .toggle-link:hover {
            text-decoration: underline;
        }

        /* Tom Select Dark Mode Fix */
        .ts-control {
            background-color: var(--input-bg) !important;
            border-color: var(--border-color) !important;
            color: var(--text-main) !important;
        }

        .ts-dropdown {
            background-color: var(--card-bg) !important;
            color: var(--text-main) !important;
            border-color: var(--border-color) !important;
        }

        .ts-dropdown .active {
            background-color: var(--primary-blue) !important;
        }
    </style>
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
            <div class="alert <?php echo strpos($message, 'successful') !== false ? 'alert-success' : 'alert-error'; ?>"
                style="<?php echo strpos($message, 'successful') !== false ? 'background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #10b981; padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px; text-align: center;' : ''; ?>">
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
                    <label>Full Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Username <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="reg_username" class="form-control" placeholder="j.doe" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="reg_password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Office / Station</label>
                    <select name="office_station" id="office_select" class="form-control" required>
                        <option value="">Select your office...</option>
                        <optgroup label="OSDS">
                            <option value="ADMINISTRATIVE (PERSONEL)">ADMINISTRATIVE (PERSONEL)</option>
                            <option value="ADMINISTRATIVE (PROPERTY AND SUPPLY)">ADMINISTRATIVE (PROPERTY AND SUPPLY)
                            </option>
                            <option value="ADMINISTRATIVE (RECORDS)">ADMINISTRATIVE (RECORDS)</option>
                            <option value="ADMINISTRATIVE (CASH)">ADMINISTRATIVE (CASH)</option>
                            <option value="ADMINISTRATIVE (GENERAL SERVICES)">ADMINISTRATIVE (GENERAL SERVICES)</option>
                            <option value="FINANCE (ACCOUNTING)">FINANCE (ACCOUNTING)</option>
                            <option value="FINANCE (BUDGET)">FINANCE (BUDGET)</option>
                            <option value="LEGAL">LEGAL</option>
                            <option value="ICT">ICT</option>
                        </optgroup>
                        <optgroup label="SGOD">
                            <option value="SCHOOL MANAGEMENT MONITORING & EVALUATION">SCHOOL MANAGEMENT MONITORING &
                                EVALUATION</option>
                            <option value="HUMAN RESOURCES DEVELOPMENT">HUMAN RESOURCES DEVELOPMENT</option>
                            <option value="DISASTER RISK REDUCTION AND MANAGEMENT">DISASTER RISK REDUCTION AND
                                MANAGEMENT</option>
                            <option value="EDUCATION FACILITIES">EDUCATION FACILITIES</option>
                            <option value="SCHOOL HEALTH AND NUTRITION">SCHOOL HEALTH AND NUTRITION</option>
                            <option value="SCHOOL HEALTH AND NUTRITION (DENTAL)">SCHOOL HEALTH AND NUTRITION (DENTAL)
                            </option>
                            <option value="SCHOOL HEALTH AND NUTRITION (MEDICAL)">SCHOOL HEALTH AND NUTRITION (MEDICAL)
                            </option>
                        </optgroup>
                        <optgroup label="CID">
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)">CURRICULUM
                                IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)</option>
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)">
                                CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)</option>
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)">CURRICULUM
                                IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)</option>
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)">
                                CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control" placeholder="e.g. Teacher I">
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="area_of_specialization" class="form-control"
                            placeholder="e.g. Science">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" class="form-control" placeholder="25">
                    </div>
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex" class="form-control">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Rating Period</label>
                    <input type="text" name="rating_period" class="form-control" placeholder="e.g. 2025">
                </div>

                <button type="submit" class="btn">Register Account</button>
            </form>
            <div class="footer-text">
                Already have an account? <span class="toggle-link" onclick="toggleAuth(false)">Back to Login</span>
            </div>
        </div>

        <div class="footer-text" style="margin-top: 30px;">
            Department of Education - San Pedro Division<br>
            <span style="font-size: 0.8em; opacity: 0.5;">Developed by A.L and C.B</span>
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