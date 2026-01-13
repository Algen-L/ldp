<?php
session_start();
require '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head')) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station']);
    $position = trim($_POST['position']);
    $password = $_POST['password'];

    if ($password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, office_station = ?, position = ?, password = ? WHERE id = ?");
        $success = $stmt->execute([$full_name, $office_station, $position, $hashed_password, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, office_station = ?, position = ? WHERE id = ?");
        $success = $stmt->execute([$full_name, $office_station, $position, $user_id]);
    }

    if ($success) {
        $_SESSION['toast'] = ['title' => 'Profile Updated', 'message' => 'Your profile has been successfully updated.', 'type' => 'success'];
        $_SESSION['full_name'] = $full_name;

        // Log the action
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$user_id, 'Updated Admin Profile', 'Profile details changed', $_SERVER['REMOTE_ADDR']]);
    } else {
        $_SESSION['toast'] = ['title' => 'Update Failed', 'message' => 'There was an error updating your profile.', 'type' => 'error'];
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - LDP</title>
    <?php require 'includes/admin_head.php'; ?>
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        /* Tom Select Custom Styling for Admin Panel */
        .ts-control {
            border: 1px solid var(--border-color) !important;
            border-radius: var(--radius-md) !important;
            padding: 12px 14px 12px 42px !important;
            /* Extra padding-left for the icon */
            background: white !important;
            color: var(--text-primary) !important;
            font-family: inherit !important;
            font-size: 0.95rem !important;
            height: 48px !important;
            transition: all var(--transition-fast) !important;
            box-shadow: none !important;
        }

        .ts-control:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px var(--primary-light) !important;
        }

        .ts-dropdown {
            border-radius: var(--radius-lg) !important;
            border: 1px solid var(--border-light) !important;
            box-shadow: var(--shadow-lg) !important;
            margin-top: 8px !important;
            padding: 8px !important;
            z-index: 2000 !important;
        }

        .ts-dropdown .optgroup-header {
            font-weight: 800 !important;
            text-transform: uppercase !important;
            font-size: 0.7rem !important;
            color: var(--primary) !important;
            letter-spacing: 0.05em !important;
            padding: 12px 12px 6px !important;
            background: var(--bg-secondary) !important;
            border-radius: var(--radius-sm) !important;
        }

        .ts-dropdown .option {
            padding: 10px 12px !important;
            border-radius: var(--radius-sm) !important;
            font-size: 0.9rem !important;
            color: var(--text-secondary) !important;
        }

        .ts-dropdown .active {
            background: var(--primary) !important;
            color: white !important;
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle" id="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="text-muted">Admin Panel</span>
                        <i class="bi bi-chevron-right separator"></i>
                        <h1 class="page-title">Personal Profile</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <i class="bi bi-person-badge"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <div style="max-width: 900px; margin: 0 auto;">


                    <div class="dashboard-card hover-elevate">
                        <div class="card-header">
                            <h2><i class="bi bi-person-vcard text-gradient"></i> Core Identification</h2>
                        </div>
                        <div class="card-body" style="padding: 30px;">
                            <form method="POST" action="">
                                <div class="filter-group" style="margin-bottom: 25px;">
                                    <label>Primary Full Name</label>
                                    <div style="position: relative;">
                                        <i class="bi bi-person"
                                            style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                        <input type="text" name="full_name" class="form-control" required
                                            value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                            style="padding-left: 42px; height: 48px;">
                                    </div>
                                </div>

                                <div
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                                    <div class="filter-group">
                                        <label>Current Office / Assignment</label>
                                        <div style="position: relative;">
                                            <i class="bi bi-building"
                                                style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 10;"></i>
                                            <select name="office_station" id="office_select" class="form-control"
                                                required style="padding-left: 42px; height: 48px;">
                                                <option value="">Select your office...</option>
                                                <optgroup label="OSDS">
                                                    <option value="ADMINISTRATIVE (PERSONEL)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (PERSONEL)') ? 'selected' : ''; ?>>ADMINISTRATIVE (PERSONEL)</option>
                                                    <option value="ADMINISTRATIVE (PROPERTY AND SUPPLY)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (PROPERTY AND SUPPLY)') ? 'selected' : ''; ?>>ADMINISTRATIVE (PROPERTY AND
                                                        SUPPLY)</option>
                                                    <option value="ADMINISTRATIVE (RECORDS)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (RECORDS)') ? 'selected' : ''; ?>>ADMINISTRATIVE (RECORDS)</option>
                                                    <option value="ADMINISTRATIVE (CASH)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (CASH)') ? 'selected' : ''; ?>>ADMINISTRATIVE (CASH)</option>
                                                    <option value="ADMINISTRATIVE (GENERAL SERVICES)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (GENERAL SERVICES)') ? 'selected' : ''; ?>>ADMINISTRATIVE (GENERAL SERVICES)</option>
                                                    <option value="FINANCE (ACCOUNTING)" <?php echo ($user['office_station'] == 'FINANCE (ACCOUNTING)') ? 'selected' : ''; ?>>FINANCE (ACCOUNTING)</option>
                                                    <option value="FINANCE (BUDGET)" <?php echo ($user['office_station'] == 'FINANCE (BUDGET)') ? 'selected' : ''; ?>>FINANCE (BUDGET)</option>
                                                    <option value="LEGAL" <?php echo ($user['office_station'] == 'LEGAL') ? 'selected' : ''; ?>>LEGAL</option>
                                                    <option value="ICT" <?php echo ($user['office_station'] == 'ICT') ? 'selected' : ''; ?>>ICT</option>
                                                </optgroup>
                                                <optgroup label="SGOD">
                                                    <option value="SCHOOL MANAGEMENT MONITORING & EVALUATION" <?php echo ($user['office_station'] == 'SCHOOL MANAGEMENT MONITORING & EVALUATION') ? 'selected' : ''; ?>>SCHOOL MANAGEMENT MONITORING
                                                        & EVALUATION</option>
                                                    <option value="HUMAN RESOURCES DEVELOPMENT" <?php echo ($user['office_station'] == 'HUMAN RESOURCES DEVELOPMENT') ? 'selected' : ''; ?>>HUMAN RESOURCES DEVELOPMENT</option>
                                                    <option value="DISASTER RISK REDUCTION AND MANAGEMENT" <?php echo ($user['office_station'] == 'DISASTER RISK REDUCTION AND MANAGEMENT') ? 'selected' : ''; ?>>DISASTER RISK REDUCTION AND
                                                        MANAGEMENT</option>
                                                    <option value="EDUCATION FACILITIES" <?php echo ($user['office_station'] == 'EDUCATION FACILITIES') ? 'selected' : ''; ?>>EDUCATION FACILITIES</option>
                                                    <option value="SCHOOL HEALTH AND NUTRITION" <?php echo ($user['office_station'] == 'SCHOOL HEALTH AND NUTRITION') ? 'selected' : ''; ?>>SCHOOL HEALTH AND NUTRITION</option>
                                                    <option value="SCHOOL HEALTH AND NUTRITION (DENTAL)" <?php echo ($user['office_station'] == 'SCHOOL HEALTH AND NUTRITION (DENTAL)') ? 'selected' : ''; ?>>SCHOOL HEALTH AND NUTRITION
                                                        (DENTAL)</option>
                                                    <option value="SCHOOL HEALTH AND NUTRITION (MEDICAL)" <?php echo ($user['office_station'] == 'SCHOOL HEALTH AND NUTRITION (MEDICAL)') ? 'selected' : ''; ?>>SCHOOL HEALTH AND NUTRITION
                                                        (MEDICAL)</option>
                                                </optgroup>
                                                <optgroup label="CID">
                                                    <option
                                                        value="CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)"
                                                        <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)') ? 'selected' : ''; ?>>
                                                        CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)
                                                    </option>
                                                    <option
                                                        value="CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)"
                                                        <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)') ? 'selected' : ''; ?>>CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES
                                                        MANAGEMENT)</option>
                                                    <option
                                                        value="CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)"
                                                        <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)') ? 'selected' : ''; ?>>
                                                        CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)
                                                    </option>
                                                    <option
                                                        value="CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)"
                                                        <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)') ? 'selected' : ''; ?>>CURRICULUM IMPLEMENTATION DIVISION (DISTRICT
                                                        INSTRUCTIONAL SUPERVISION)</option>
                                                </optgroup>
                                                <?php if ($user['office_station'] && !in_array($user['office_station'], ['ADMINISTRATIVE (PERSONEL)', 'ADMINISTRATIVE (PROPERTY AND SUPPLY)', 'ADMINISTRATIVE (RECORDS)', 'ADMINISTRATIVE (CASH)', 'ADMINISTRATIVE (GENERAL SERVICES)', 'FINANCE (ACCOUNTING)', 'FINANCE (BUDGET)', 'LEGAL', 'ICT', 'SCHOOL MANAGEMENT MONITORING & EVALUATION', 'HUMAN RESOURCES DEVELOPMENT', 'DISASTER RISK REDUCTION AND MANAGEMENT', 'EDUCATION FACILITIES', 'SCHOOL HEALTH AND NUTRITION', 'SCHOOL HEALTH AND NUTRITION (DENTAL)', 'SCHOOL HEALTH AND NUTRITION (MEDICAL)', 'CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)', 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)'])): ?>
                                                    <option value="<?php echo htmlspecialchars($user['office_station']); ?>"
                                                        selected><?php echo htmlspecialchars($user['office_station']); ?>
                                                        (Current)</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="filter-group">
                                        <label>Official Position</label>
                                        <div style="position: relative;">
                                            <i class="bi bi-briefcase"
                                                style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                            <input type="text" name="position" class="form-control"
                                                value="<?php echo htmlspecialchars($user['position']); ?>"
                                                style="padding-left: 42px; height: 48px;">
                                        </div>
                                    </div>
                                </div>

                                <div class="filter-group" style="margin-bottom: 35px;">
                                    <label>Security Override (Leave blank to keep password)</label>
                                    <div style="position: relative;">
                                        <i class="bi bi-shield-lock"
                                            style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                        <input type="password" name="password" class="form-control"
                                            placeholder="••••••••" style="padding-left: 42px; height: 48px;">
                                    </div>
                                </div>

                                <div
                                    style="display: flex; gap: 15px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid var(--border-color);">
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Return
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-cloud-arrow-up"></i> Synchronize Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="dashboard-card" style="margin-top: 24px; border-left: 4px solid var(--primary);">
                        <div class="card-header">
                            <h2><i class="bi bi-shield-check text-primary"></i> Administrative Privileges</h2>
                        </div>
                        <div class="card-body" style="padding: 24px;">
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <div
                                    style="width: 60px; height: 60px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.75rem;">
                                    <i class="bi bi-key-fill"></i>
                                </div>
                                <div>
                                    <div
                                        style="font-weight: 800; color: var(--text-primary); font-size: 1.1rem; letter-spacing: -0.01em;">
                                        Role Level: <?php echo strtoupper($_SESSION['role']); ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5;">
                                        Your account is authorized with <strong>Higher Level</strong> administrative
                                        permissions. You can manage personnel records and audit system logs.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Account Security
                        Hub.</span></p>
            </footer>
        </div>
    </div>
    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
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
    </script>
</body>

</html>