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
        $_SESSION['toast'] = ['title' => 'Missing Fields', 'message' => 'Please fill in all required fields.', 'type' => 'error'];
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['toast'] = ['title' => 'Registration Error', 'message' => 'Username already exists.', 'type' => 'error'];
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

            // Insert user with fixed 'user' role and auto-verified status
            $sql = "INSERT INTO users (username, password, full_name, office_station, position, rating_period, area_of_specialization, age, sex, profile_picture, role, created_by, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', ?, 1)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$username, $hashed_password, $full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex, $dbPath, $_SESSION['user_id']])) {
                $_SESSION['toast'] = ['title' => 'Personnel Created', 'message' => "Account for $username has been created successfully!", 'type' => 'success'];

                // Log activity
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Created User', "Created new user: $username", $_SERVER['REMOTE_ADDR']]);

                header("Location: register.php");
                exit;
            } else {
                $_SESSION['toast'] = ['title' => 'Creation Failed', 'message' => 'Something went wrong. Please try again.', 'type' => 'error'];
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
    <title>Register Personnel - HR Panel</title>
    <?php require 'includes/hr_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/layout/notifications.css">
    <style>
        .register-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 16px;
            align-items: start;
            max-width: 1250px;
            margin: 20px auto;
            justify-content: center;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--border-color, #e2e8f0);
            box-shadow: var(--shadow-sm, 0 2px 6px -1px rgba(0, 0, 0, 0.1));
            overflow: hidden;
        }

        .card-header-custom {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-header-custom h2 {
            font-size: 0.95rem;
            font-weight: 800;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-header-primary {
            background: linear-gradient(135deg, #0f4c75 0%, #3282b8 100%);
            color: white;
            border: none;
        }

        .card-body-custom {
            padding: 20px;
        }

        .form-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-control,
        .form-select {
            height: 42px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 0 16px;
            font-size: 0.92rem;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control:focus,
        .form-select:focus {
            background: white;
            border-color: #3282b8;
            box-shadow: 0 0 0 4px rgba(50, 130, 184, 0.1);
            outline: none;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 8px;
        }

        .form-section-header {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            margin-top: 4px;
        }

        .form-section-header::after {
            content: '';
            flex: 1;
            height: 2px;
            background: #f1f5f9;
            border-radius: 2px;
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
            border-radius: 12px 0 0 12px;
            color: white;
            padding: 0 14px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .input-group .form-control {
            flex: 1;
            border-radius: 0 12px 12px 0;
        }

        /* TomSelect Hierarchy & Styles */
        .ts-wrapper.form-control {
            padding: 0 !important;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            height: auto !important;
        }

        .ts-control {
            background: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 0 16px !important;
            font-size: 0.92rem !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            min-height: 42px !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.2s !important;
        }

        .ts-dropdown .optgroup-header {
            font-weight: 800 !important;
            background: #0f4c75 !important;
            color: white !important;
            font-size: 0.72rem !important;
            padding: 8px 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            border-radius: 8px !important;
            margin: 8px 8px 4px !important;
            line-height: 1.2 !important;
        }

        .ts-dropdown .optgroup .option {
            padding-left: 24px !important;
        }

        @media (max-width: 1200px) {
            .register-container {
                grid-template-columns: 1fr;
                max-width: 800px;
            }
        }

        .content-wrapper {
            padding: 12px 24px;
        }

        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }

            .content-wrapper {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">HR Registration</h1>
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
                <!-- Alert Removed in favor of Toast Notification -->

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
                                    <select name="role" class="form-select" disabled
                                        style="border: none; background: rgba(255,255,255,0.15); color: white; border-radius: 10px; height: 42px; font-weight: 600; font-size: 0.88rem; cursor: not-allowed;">
                                        <option value="user" selected>User (L&D Personnel)</option>
                                    </select>
                                    <input type="hidden" name="role" value="user">
                                    <p class="mt-1" style="font-size: 0.65rem; color: rgba(255,255,255,0.5);">HR can
                                        only create standard users.</p>
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
                                    <div class="form-group">
                                        <label class="form-label">Position / Designation</label>
                                        <input type="text" name="position" class="form-control"
                                            placeholder="e.g. Teacher I">
                                    </div>
                                </div>

                                <div class="form-section-header">Employment Details</div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Employee Number</label>
                                        <input type="text" name="employee_number" class="form-control"
                                            placeholder="e.g. 1234567">
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
    <script src="../js/notifications.js"></script>
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