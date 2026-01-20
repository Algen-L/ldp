<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
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
    $role = trim($_POST['role'] ?? 'user'); // Default to user if not set
    $rating_period = trim($_POST['rating_period'] ?? '');
    $area_of_specialization = trim($_POST['area_of_specialization'] ?? '');
    $age = isset($_POST['age']) ? (int) $_POST['age'] : 0;
    $sex = trim($_POST['sex'] ?? '');

    // Basic validation
    if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
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

            // Insert user
            $sql = "INSERT INTO users (username, password, full_name, office_station, position, rating_period, area_of_specialization, age, sex, profile_picture, role, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$username, $hashed_password, $full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex, $dbPath, $role, $_SESSION['user_id']])) {
                $message = "Account created successfully!";
                $messageType = "success";

                // Log activity
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Created User (Super Admin)', "Created new $role: $username", $_SERVER['REMOTE_ADDR']]);
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
    <title>Register Account - Super Admin</title>
    <?php require '../includes/admin_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        .register-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            align-items: start;
            max-width: 1300px;
            margin: 0 auto;
        }

        .register-card {
            background: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .card-header-custom {
            padding: 12px 24px;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-header-custom h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header-primary {
            background: linear-gradient(135deg, #0f4c75 0%, #3282b8 100%);
            color: white;
            border: none;
        }

        .card-header-primary h2 {
            color: white;
        }

        .card-body-custom {
            padding: 20px 24px;
        }

        .form-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control,
        .form-select {
            height: 38px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding: 0 14px;
            font-size: 0.88rem;
            color: #1e293b;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control:focus,
        .form-select:focus {
            background: white;
            border-color: #3282b8;
            box-shadow: 0 0 0 3px rgba(50, 130, 184, 0.1);
            outline: none;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 12px;
        }

        .form-section-header {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            margin-top: 4px;
        }

        .form-section-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #f1f5f9;
        }

        .profile-upload-zone {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 76, 117, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: all 0.3s ease;
            cursor: pointer;
            z-index: 10;
            backdrop-filter: blur(4px);
        }

        .position-relative:hover .profile-upload-zone {
            opacity: 1;
        }

        .input-group {
            display: flex;
            width: 100%;
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            border-radius: 10px 0 0 10px;
            color: white;
            padding: 0 14px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .input-group .form-control {
            flex: 1;
            border-radius: 0 10px 10px 0;
        }

        @media (max-width: 1200px) {
            .register-container {
                grid-template-columns: 1fr;
                max-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }

            .content-wrapper {
                padding: 16px;
            }
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
                        <h1 class="page-title">Super Admin Registration</h1>
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
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> fade show" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
                    <div class="register-container">
                        <!-- Left Column: Account Access -->
                        <div class="register-card"
                            style="background: linear-gradient(135deg, #0f4c75 0%, #3282b8 100%); color: white;">
                            <div class="card-header-custom" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <h2 style="color: white;"><i class="bi bi-shield-lock-fill"></i> Account Access</h2>
                            </div>
                            <div class="card-body-custom">
                                <div class="mb-3 text-center">
                                    <div class="position-relative d-inline-block"
                                        style="position: relative; display: inline-block;">
                                        <div class="profile-upload-zone"
                                            onclick="document.getElementById('profile_picture').click()">
                                            <i class="bi bi-camera-fill" style="font-size: 1.2rem;"></i>
                                        </div>
                                        <img src="../assets/human_avatar.png" id="preview-image" class="shadow-lg"
                                            style="width: 100px; height: 100px; border-radius: 50%; object-fit:cover; border: 3px solid rgba(255,255,255,0.2); display: block; background: #f1f5f9;">
                                    </div>
                                    <input type="file" name="profile_picture" id="profile_picture" class="d-none"
                                        style="display: none;" accept="image/*" onchange="previewFile()">
                                    <p class="mt-2"
                                        style="color: rgba(255,255,255,0.7); font-weight: 500; font-size: 0.72rem;">
                                        Click to upload photo</p>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label" style="color: rgba(255,255,255,0.9);">System Role</label>
                                    <select name="role" class="form-select" required
                                        style="border: none; background: rgba(255,255,255,0.15); color: white; border-radius: 10px; height: 42px; font-weight: 600; font-size: 0.88rem;">
                                        <option value="user" style="color: #334155;">User (L&D Personnel)</option>
                                        <option value="hr" style="color: #334155;">HR Personnel</option>
                                        <option value="immediate_head" style="color: #334155;">Immediate Head (Approver)
                                        </option>
                                        <option value="admin" style="color: #334155;">System Admin</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label" style="color: rgba(255,255,255,0.9);">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="username" class="form-control" required
                                            style="background: rgba(255,255,255,0.1); border: none; color: white; height: 38px; font-size: 0.88rem;">
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label" style="color: rgba(255,255,255,0.9);">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="password" name="password" class="form-control" required
                                            style="background: rgba(255,255,255,0.1); border: none; color: white; height: 38px; font-size: 0.88rem;">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg mt-1"
                                    style="background: white; color: #0f4c75; border:none; width: 100%; border-radius: 12px; height: 48px; font-weight: 800; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                    <i class="bi bi-person-plus-fill"></i> CREATE ACCOUNT
                                </button>
                            </div>
                        </div>

                        <!-- Right Column: Personnel Info -->
                        <div class="register-card">
                            <div class="card-header-custom">
                                <h2><i class="bi bi-person-lines-fill text-primary"></i> Personnel Information</h2>
                            </div>
                            <div class="card-body-custom">
                                <div class="form-section-header">Personal Details</div>

                                <div class="form-group mb-3">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <input type="text" name="full_name" class="form-control"
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

                                <div class="form-grid-2" style="margin-bottom: 0;">
                                    <div class="form-group">
                                        <label class="form-label">Age</label>
                                        <input type="number" name="age" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Sex</label>
                                        <select name="sex" class="form-select">
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
                preview.src = "../assets/human_avatar.png";
            }
        }
    </script>
</body>

</html>