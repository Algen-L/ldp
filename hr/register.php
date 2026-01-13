<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is HR or Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $rating_period = trim($_POST['rating_period'] ?? '');
    $area_of_specialization = trim($_POST['area_of_specialization'] ?? '');
    $age = isset($_POST['age']) ? (int) $_POST['age'] : 0;
    $sex = trim($_POST['sex'] ?? '');

    // Basic validation
    if (empty($username) || empty($password) || empty($full_name)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $message = "Username already exists.";
            $messageType = "error";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Handle Profile Picture Upload
            $dbPath = NULL;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/profile_pics/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);
                $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['profile_picture']['name']));
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                    $dbPath = str_replace('../', '', $targetPath); // Remove relative prefix for DB storage
                }
            }

            // Insert user with fixed 'user' role
            $sql = "INSERT INTO users (username, password, full_name, office_station, position, rating_period, area_of_specialization, age, sex, profile_picture, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$username, $hashed_password, $full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex, $dbPath])) {
                $message = "Personnel account created successfully!";
                $messageType = "success";

                // Log activity
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Created User', "Created new user: $username", $_SERVER['REMOTE_ADDR']]);
            } else {
                $message = "Something went wrong. Please try again.";
                $messageType = "error";
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
    <title>Register Personnel - HR Panel</title>
    <?php require 'includes/hr_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        .register-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            align-items: start;
        }

        .register-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            height: 100%;
        }

        .card-header-custom {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header-primary {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        .card-body-custom {
            padding: 24px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-section-header {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 8px;
            margin-bottom: 20px;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }

        .profile-upload-zone {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.2s;
            cursor: pointer;
            z-index: 10;
        }

        .position-relative:hover .profile-upload-zone {
            opacity: 1;
        }

        @media (max-width: 992px) {
            .register-container {
                grid-template-columns: 1fr;
            }

            .form-grid-2,
            .form-grid-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="user-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle" id="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="text-muted">HR Panel</span>
                        <i class="bi bi-chevron-right separator"></i>
                        <h1 class="page-title">Register New Personnel</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <i class="bi bi-calendar-check"></i>
                        <span>
                            <?php echo date('l, F d, Y'); ?>
                        </span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> fade show" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
                    <div class="register-container">
                        <!-- Left Column: Account Access -->
                        <div class="register-card">
                            <div class="card-header-custom card-header-primary">
                                <h2 class="text-white"><i class="bi bi-shield-lock-fill"></i> Account Access</h2>
                            </div>
                            <div class="card-body-custom">
                                <div class="mb-4 text-center">
                                    <div class="position-relative d-inline-block"
                                        style="position: relative; display: inline-block;">
                                        <div class="profile-upload-zone"
                                            onclick="document.getElementById('profile_picture').click()">
                                            <i class="bi bi-camera-fill" style="font-size: 1.5rem;"></i>
                                        </div>
                                        <img src="../assets/default_avatar.png" id="preview-image"
                                            class="img-fluid rounded-circle shadow-lg"
                                            style="width: 120px; height: 120px; object-fit:cover; border: 4px solid white; display: block;">
                                    </div>
                                    <input type="file" name="profile_picture" id="profile_picture" class="d-none"
                                        style="display: none;" accept="image/*" onchange="previewFile()">
                                    <p class="text-muted mt-2 small">Click image to upload</p>
                                </div>

                                <!-- HR creates standard users by default, role hidden/omitted -->

                                <div class="form-group mb-3">
                                    <label class="form-label">Username</label>
                                    <div class="input-group" style="display: flex;">
                                        <span class="input-group-text"
                                            style="background:var(--bg-secondary); border:1px solid var(--border-color); padding: 0 12px; display:flex; align-items:center;"><i
                                                class="bi bi-person"></i></span>
                                        <input type="text" name="username" class="form-control" required
                                            placeholder="jdelacruz"
                                            style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                    </div>
                                </div>

                                <div class="form-group mb-4">
                                    <label class="form-label">Password</label>
                                    <div class="input-group" style="display: flex;">
                                        <span class="input-group-text"
                                            style="background:var(--bg-secondary); border:1px solid var(--border-color); padding: 0 12px; display:flex; align-items:center;"><i
                                                class="bi bi-key"></i></span>
                                        <input type="password" name="password" class="form-control" required
                                            placeholder="••••••••"
                                            style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                    </div>
                                </div>

                                <div class="d-grid" style="display: grid; gap: 10px;">
                                    <button type="submit" class="btn btn-primary btn-lg"
                                        style="background: var(--primary-gradient); border:none; width: 100%;">
                                        <i class="bi bi-plus-circle"></i> Create Account
                                    </button>
                                    <a href="../pages/home.php" class="btn btn-secondary"
                                        style="border: 1px solid var(--border-color); background: transparent; color: var(--text-color); width: 100%; text-align: center;">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Personnel Info -->
                        <div class="register-card">
                            <div class="card-header-custom">
                                <h2><i class="bi bi-person-lines-fill text-primary"></i> Personnel Information</h2>
                            </div>
                            <div class="card-body-custom">
                                <div class="form-section-header">Personal Details</div>

                                <div class="form-group mb-4">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <input type="text" name="full_name" class="form-control form-control-lg"
                                        placeholder="First Name M.I. Last Name" required>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Office / Station</label>
                                        <select id="office-select" name="office_station" autocomplete="off"
                                            placeholder="Search office...">
                                            <option value="">Select Office/Station...</option>
                                            <optgroup label="OSDS">
                                                <option value="ADMINISTRATIVE (PERSONEL)">ADMINISTRATIVE (PERSONEL)
                                                </option>
                                                <option value="ADMINISTRATIVE (PROPERTY AND SUPPLY)">ADMINISTRATIVE
                                                    (PROPERTY AND SUPPLY)</option>
                                                <option value="ADMINISTRATIVE (RECORDS)">ADMINISTRATIVE (RECORDS)
                                                </option>
                                                <option value="ADMINISTRATIVE (CASH)">ADMINISTRATIVE (CASH)</option>
                                                <option value="ADMINISTRATIVE (GENERAL SERVICES)">ADMINISTRATIVE
                                                    (GENERAL SERVICES)</option>
                                                <option value="FINANCE (ACCOUNTING)">FINANCE (ACCOUNTING)</option>
                                                <option value="FINANCE (BUDGET)">FINANCE (BUDGET)</option>
                                                <option value="LEGAL">LEGAL</option>
                                                <option value="ICT">ICT</option>
                                            </optgroup>
                                            <optgroup label="SGOD">
                                                <option value="SCHOOL MANAGEMENT MONITORING & EVALUATION">SCHOOL
                                                    MANAGEMENT MONITORING & EVALUATION</option>
                                                <option value="HUMAN RESOURCES DEVELOPMENT">HUMAN RESOURCES
                                                    DEVELOPMENT</option>
                                                <option value="DISASTER RISK REDUCTION AND MANAGEMENT">DISASTER RISK
                                                    REDUCTION AND MANAGEMENT</option>
                                                <option value="EDUCATION FACILITIES">EDUCATION FACILITIES</option>
                                                <option value="SCHOOL HEALTH AND NUTRITION">SCHOOL HEALTH AND
                                                    NUTRITION</option>
                                                <option value="SCHOOL HEALTH AND NUTRITION (DENTAL)">SCHOOL HEALTH
                                                    AND NUTRITION (DENTAL)</option>
                                                <option value="SCHOOL HEALTH AND NUTRITION (MEDICAL)">SCHOOL HEALTH
                                                    AND NUTRITION (MEDICAL)</option>
                                            </optgroup>
                                            <optgroup label="CID">
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)">
                                                    CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)
                                                </option>
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)">
                                                    CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES
                                                    MANAGEMENT)</option>
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)">
                                                    CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)
                                                </option>
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)">
                                                    CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL
                                                    SUPERVISION)</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Position / Designation</label>
                                        <input type="text" name="position" class="form-control"
                                            placeholder="e.g. Teacher I">
                                    </div>
                                </div>

                                <div class="form-section-header">Employment Details</div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Rating Period</label>
                                        <input type="text" name="rating_period" class="form-control"
                                            placeholder="e.g. 2025">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Area of Specialization</label>
                                        <input type="text" name="area_of_specialization" class="form-control">
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Age</label>
                                        <input type="number" name="age" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Sex</label>
                                        <select name="sex" class="form-select form-control">
                                            <option value="">Select...</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        new TomSelect("#office-select", {
            create: true,
            sortField: {
                field: "text",
                direction: "asc"
            }
        });

        function previewFile() {
            var preview = document.querySelector('#preview-image');
            var file = document.querySelector('input[type=file]').files[0];
            var reader = new FileReader();

            reader.onloadend = function () {
                preview.src = reader.result;
            }

            if (file) {
                reader.readAsDataURL(file);
            } else {
                preview.src = "../assets/default_avatar.png";
            }
        }
    </script>
</body>

</html>