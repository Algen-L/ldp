<?php
session_start();
require '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageType = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Only HR and Super Admin can edit profiles
    if ($_SESSION['role'] === 'hr' || $_SESSION['role'] === 'super_admin') {
        $full_name = trim($_POST['full_name']);
        $office_station = trim($_POST['office_station']);
        $position = trim($_POST['position']);
        $rating_period = trim($_POST['rating_period']);
        $area_of_specialization = trim($_POST['area_of_specialization']);
        $age = (int) $_POST['age'];
        $sex = trim($_POST['sex']);
        $password = trim($_POST['password']);

        if (empty($full_name)) {
            $message = "Name is required.";
            $messageType = "error";
        } else {
            // Prepare update query
            $sql = "UPDATE users SET full_name = ?, office_station = ?, position = ?, rating_period = ?, area_of_specialization = ?, age = ?, sex = ?";
            $params = [$full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex];

            // Update password if provided
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed_password;
            }

            // Handle Profile Picture Upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/profile_pics/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);
                $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['profile_picture']['name']));
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                    $dbPath = str_replace('../', '', $targetPath);
                    $sql .= ", profile_picture = ?";
                    $params[] = $dbPath;
                }
            }

            $sql .= " WHERE id = ?";
            $params[] = $_SESSION['user_id'];

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $message = "Profile updated successfully!";
                $messageType = "success";

                // Update session variables
                $_SESSION['full_name'] = $full_name;
                $_SESSION['position'] = $position;

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Log activity
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Updated Profile', 'Profile information updated', $_SERVER['REMOTE_ADDR']]);
            } else {
                $message = "Error updating profile.";
                $messageType = "error";
            }
        }
    } else {
        $message = "You do not have permission to edit your profile. Please contact HR or administration.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage My Profile - LDP</title>
    <?php require '../includes/head.php'; ?>
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        .profile-hero {
            background: var(--primary-gradient);
            padding: 40px;
            border-radius: var(--radius-xl);
            display: flex;
            gap: 32px;
            align-items: center;
            margin-bottom: 32px;
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .hero-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            box-shadow: var(--shadow-md);
        }

        .hero-info h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .hero-info p {
            opacity: 0.9;
            font-weight: 500;
            font-size: 1.1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }

        /* Tom Select Custom Styling */
        .ts-control {
            border: 1.5px solid var(--border-color) !important;
            border-radius: var(--radius-md) !important;
            padding: 10px 14px !important;
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            font-family: inherit !important;
            font-size: 0.95rem !important;
            transition: all var(--transition-fast) !important;
        }

        .ts-control:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px var(--primary-light) !important;
            background: white !important;
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

    <div class="user-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle" id="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="text-muted">User</span>
                        <i class="bi bi-chevron-right separator"></i>
                        <h1 class="page-title">Profile Settings</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <a href="home.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-house"></i> Home
                    </a>
                </div>
            </header>

            <main class="content-wrapper">
                <?php if ($message): ?>
                    <script>
                        window.addEventListener('DOMContentLoaded', function () {
                            showToast("<?php echo ($messageType === 'success') ? 'Success!' : 'Notice'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
                        });
                    </script>
                <?php endif; ?>

                <div class="profile-hero">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" class="hero-avatar">
                    <?php else: ?>
                        <div class="hero-avatar"
                            style="background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; color: var(--text-muted);">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="hero-info">
                        <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <p><?php echo htmlspecialchars($user['position'] ?: 'Educational Professional'); ?></p>
                        <div style="margin-top: 12px; display: flex; gap: 12px;">
                            <span class="activity-status-badge status-approved"
                                style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2);">
                                <i class="bi bi-shield-check"></i> Account Active
                            </span>
                            <span class="activity-status-badge status-approved"
                                style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2);">
                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($user['office_station']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="bi bi-person-gear"></i> Account Information</h2>
                    </div>
                    <div class="card-body" style="padding: 40px;">
                        <?php
                        // Check if current user can edit profiles
                        $can_edit = ($_SESSION['role'] === 'hr' || $_SESSION['role'] === 'super_admin');
                        $readonly = $can_edit ? '' : 'readonly';
                        $disabled = $can_edit ? '' : 'disabled';
                        ?>

                        <?php if (!$can_edit): ?>
                            <div class="alert alert-info" style="margin-bottom: 24px;">
                                <i class="bi bi-info-circle"></i> Your profile is view-only. Please contact HR or
                                administration to update your information.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">

                            <div class="form-grid">
                                <!-- Col 1 -->
                                <div>
                                    <div class="form-group">
                                        <label class="form-label">Full Name <span
                                                style="color: var(--danger);">*</span></label>
                                        <input type="text" name="full_name" class="form-control" required
                                            value="<?php echo htmlspecialchars($user['full_name']); ?>" <?php echo $readonly; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Office / Station</label>
                                        <select name="office_station" id="office_select" class="form-control" required <?php echo $disabled; ?>>
                                            <option value="">Select your office...</option>
                                            <optgroup label="OSDS">
                                                <option value="ADMINISTRATIVE (PERSONEL)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (PERSONEL)') ? 'selected' : ''; ?>>ADMINISTRATIVE (PERSONEL)</option>
                                                <option value="ADMINISTRATIVE (PROPERTY AND SUPPLY)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (PROPERTY AND SUPPLY)') ? 'selected' : ''; ?>>ADMINISTRATIVE (PROPERTY AND SUPPLY)</option>
                                                <option value="ADMINISTRATIVE (RECORDS)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (RECORDS)') ? 'selected' : ''; ?>>ADMINISTRATIVE (RECORDS)</option>
                                                <option value="ADMINISTRATIVE (CASH)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (CASH)') ? 'selected' : ''; ?>>ADMINISTRATIVE (CASH)</option>
                                                <option value="ADMINISTRATIVE (GENERAL SERVICES)" <?php echo ($user['office_station'] == 'ADMINISTRATIVE (GENERAL SERVICES)') ? 'selected' : ''; ?>>ADMINISTRATIVE (GENERAL SERVICES)</option>
                                                <option value="FINANCE (ACCOUNTING)" <?php echo ($user['office_station'] == 'FINANCE (ACCOUNTING)') ? 'selected' : ''; ?>>FINANCE (ACCOUNTING)</option>
                                                <option value="FINANCE (BUDGET)" <?php echo ($user['office_station'] == 'FINANCE (BUDGET)') ? 'selected' : ''; ?>>
                                                    FINANCE (BUDGET)</option>
                                                <option value="LEGAL" <?php echo ($user['office_station'] == 'LEGAL') ? 'selected' : ''; ?>>LEGAL</option>
                                                <option value="ICT" <?php echo ($user['office_station'] == 'ICT') ? 'selected' : ''; ?>>ICT</option>
                                            </optgroup>
                                            <optgroup label="SGOD">
                                                <option value="SCHOOL MANAGEMENT MONITORING & EVALUATION" <?php echo ($user['office_station'] == 'SCHOOL MANAGEMENT MONITORING & EVALUATION') ? 'selected' : ''; ?>>SCHOOL MANAGEMENT MONITORING &
                                                    EVALUATION</option>
                                                <option value="HUMAN RESOURCES DEVELOPMENT" <?php echo ($user['office_station'] == 'HUMAN RESOURCES DEVELOPMENT') ? 'selected' : ''; ?>>HUMAN RESOURCES DEVELOPMENT</option>
                                                <option value="DISASTER RISK REDUCTION AND MANAGEMENT" <?php echo ($user['office_station'] == 'DISASTER RISK REDUCTION AND MANAGEMENT') ? 'selected' : ''; ?>>DISASTER RISK REDUCTION AND MANAGEMENT
                                                </option>
                                                <option value="EDUCATION FACILITIES" <?php echo ($user['office_station'] == 'EDUCATION FACILITIES') ? 'selected' : ''; ?>>EDUCATION FACILITIES</option>
                                                <option value="SCHOOL HEALTH AND NUTRITION" <?php echo ($user['office_station'] == 'SCHOOL HEALTH AND NUTRITION') ? 'selected' : ''; ?>>SCHOOL HEALTH AND NUTRITION</option>
                                                <option value="SCHOOL HEALTH AND NUTRITION (DENTAL)" <?php echo ($user['office_station'] == 'SCHOOL HEALTH AND NUTRITION (DENTAL)') ? 'selected' : ''; ?>>SCHOOL HEALTH AND NUTRITION (DENTAL)</option>
                                                <option value="SCHOOL HEALTH AND NUTRITION (MEDICAL)" <?php echo ($user['office_station'] == 'SCHOOL HEALTH AND NUTRITION (MEDICAL)') ? 'selected' : ''; ?>>SCHOOL HEALTH AND NUTRITION (MEDICAL)</option>
                                            </optgroup>
                                            <optgroup label="CID">
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)"
                                                    <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)') ? 'selected' : ''; ?>>
                                                    CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)
                                                </option>
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)"
                                                    <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)') ? 'selected' : ''; ?>>
                                                    CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)
                                                </option>
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)"
                                                    <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)') ? 'selected' : ''; ?>>
                                                    CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)
                                                </option>
                                                <option
                                                    value="CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)"
                                                    <?php echo ($user['office_station'] == 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)') ? 'selected' : ''; ?>>CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL
                                                    SUPERVISION)</option>
                                            </optgroup>
                                            <?php if ($user['office_station'] && !in_array($user['office_station'], ['ADMINISTRATIVE (PERSONEL)', 'ADMINISTRATIVE (PROPERTY AND SUPPLY)', 'ADMINISTRATIVE (RECORDS)', 'ADMINISTRATIVE (CASH)', 'ADMINISTRATIVE (GENERAL SERVICES)', 'FINANCE (ACCOUNTING)', 'FINANCE (BUDGET)', 'LEGAL', 'ICT', 'SCHOOL MANAGEMENT MONITORING & EVALUATION', 'HUMAN RESOURCES DEVELOPMENT', 'DISASTER RISK REDUCTION AND MANAGEMENT', 'EDUCATION FACILITIES', 'SCHOOL HEALTH AND NUTRITION', 'SCHOOL HEALTH AND NUTRITION (DENTAL)', 'SCHOOL HEALTH AND NUTRITION (MEDICAL)', 'CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)', 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)'])): ?>
                                                <option value="<?php echo htmlspecialchars($user['office_station']); ?>"
                                                    selected><?php echo htmlspecialchars($user['office_station']); ?>
                                                    (Current)</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Position / Designation</label>
                                        <input type="text" name="position" class="form-control"
                                            value="<?php echo htmlspecialchars($user['position']); ?>" <?php echo $readonly; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Rating Period</label>
                                        <input type="text" name="rating_period" class="form-control"
                                            value="<?php echo htmlspecialchars($user['rating_period']); ?>"
                                            placeholder="e.g. 2025" <?php echo $readonly; ?>>
                                    </div>
                                </div>

                                <!-- Col 2 -->
                                <div>
                                    <div class="form-group">
                                        <label class="form-label">Area of Specialization</label>
                                        <input type="text" name="area_of_specialization" class="form-control"
                                            value="<?php echo htmlspecialchars($user['area_of_specialization']); ?>" <?php echo $readonly; ?>>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <div class="form-group">
                                            <label class="form-label">Age</label>
                                            <input type="number" name="age" class="form-control"
                                                value="<?php echo htmlspecialchars($user['age']); ?>" <?php echo $readonly; ?>>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Sex</label>
                                            <select name="sex" class="form-control" <?php echo $disabled; ?>>
                                                <option value="">Select Sex</option>
                                                <option value="Male" <?php echo $user['sex'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo $user['sex'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group"
                                        style="padding: 20px; background: var(--bg-secondary); border-radius: var(--radius-lg); border: 1.5px dashed var(--border-color); margin-top: 24px;">
                                        <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                                            <i class="bi bi-image" style="color: var(--primary);"></i> Profile Picture
                                        </label>
                                        <input type="file" name="profile_picture" class="form-control" accept="image/*"
                                            style="border: none; padding: 0; background: transparent;">
                                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px;">Upload
                                            a professional photo (JPG or PNG).</p>
                                    </div>
                                </div>
                            </div>

                            <div
                                style="margin-top: 32px; padding-top: 32px; border-top: 1px solid var(--border-light);">
                                <div style="max-width: 400px; margin-bottom: 32px;">
                                    <?php if ($can_edit): ?>
                                        <h3
                                            style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">
                                            Security</h3>
                                        <div class="form-group">
                                            <label class="form-label">Change Password</label>
                                            <input type="password" name="password" class="form-control"
                                                placeholder="••••••••" autocomplete="new-password">
                                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">Leave
                                                blank to keep your current password.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($can_edit): ?>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-save"></i> Save Changes
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </main>

            <footer class="user-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. All rights reserved.</p>
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
                placeholder: "Type to search office..."
            });
        });
    </script>
</body>

</html>