<?php
session_start();
require '../includes/init_repos.php';

// Check if user is logged in and is Super Admin or HR
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'hr')) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Get user to edit
if (!isset($_GET['id'])) {
    header("Location: manage_users.php");
    exit;
}

$id = (int) $_GET['id'];
$user_to_edit = $userRepo->getUserById($id);

if (!$user_to_edit) {
    header("Location: manage_users.php");
    exit;
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $office_station = trim($_POST['office_station']);
    $position = trim($_POST['position']);
    $rating_period = trim($_POST['rating_period'] ?? '');
    $area_of_specialization = trim($_POST['area_of_specialization'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $sex = trim($_POST['sex'] ?? '');
    $password = trim($_POST['password']);

    // Only Super Admin can change role
    $role = ($_SESSION['role'] === 'super_admin') ? $_POST['role'] : $user_to_edit['role'];

    // Handle Profile Picture
    $dbPath = $user_to_edit['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/profile_pics/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['profile_picture']['name']));
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            $dbPath = 'uploads/profile_pics/' . $fileName;
        }
    }

    // Update Data
    $updateData = [
        'full_name' => $full_name,
        'username' => $username,
        'office_station' => $office_station,
        'position' => $position,
        'rating_period' => $rating_period,
        'area_of_specialization' => $area_of_specialization,
        'age' => $age,
        'sex' => $sex,
        'role' => $role,
        'profile_picture' => $dbPath
    ];

    if ($password) {
        $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($userRepo->updateUserProfile($id, $updateData)) {
        // Log the action
        $logRepo->logAction($_SESSION['user_id'], 'Edited User Profile', "Target User: $full_name (ID: $id)");

        $_SESSION['toast'] = ['title' => 'User Updated', 'message' => 'The user record has been updated successfully.', 'type' => 'success'];
        header("Location: manage_users.php");
        exit;
    } else {
        $message = "Update failed. Please check for duplicate username.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User -
        <?php echo htmlspecialchars($user_to_edit['full_name']); ?>
    </title>
    <?php require '../includes/admin_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        .edit-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .form-grid-main {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 32px;
            align-items: start;
        }

        .avatar-preview-section {
            background: white;
            border-radius: 24px;
            padding: 40px 24px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            position: sticky;
            top: 100px;
        }

        .preview-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            margin: 0 auto 24px;
            border: 6px solid #f1f5f9;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            font-weight: 800;
            background: #f8fafc;
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .edit-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .form-section-title {
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .form-grid-inner {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .form-control,
        .form-select {
            width: 100%;
            height: 46px;
            padding: 10px 16px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.2s;
            outline: none;
        }

        .form-control:focus,
        .form-select:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 76, 117, 0.1);
        }

        .btn-save {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            width: 100%;
            box-shadow: 0 4px 12px rgba(15, 76, 117, 0.2);
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 76, 117, 0.3);
        }

        /* Fix TomSelect Double Box & Styling */
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
            padding: 10px 16px !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            min-height: 46px !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.2s !important;
        }

        .ts-wrapper.form-control.focus .ts-control {
            background: white !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(15, 76, 117, 0.1) !important;
        }

        /* Refined Category Labels (Headers) */
        .ts-dropdown .optgroup-header {
            font-weight: 800 !important;
            background: var(--primary) !important;
            color: white !important;
            font-size: 0.72rem !important;
            padding: 8px 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            border-radius: 8px !important;
            margin: 10px 8px 6px !important;
            display: block !important;
            line-height: 1 !important;
            pointer-events: none !important;
            /* Non-clickable */
        }

        /* Indent options within groups */
        .ts-dropdown .optgroup .option {
            padding-left: 24px !important;
        }

        .ts-dropdown .option[data-value="SCHOOL GOVERNANCE AND OPERATION DIVISION"],
        .ts-dropdown .option[data-value="CURRICULUM IMPLEMENTATION DIVISION"] {
            font-weight: 700 !important;
            color: var(--primary) !important;
            background: rgba(15, 76, 117, 0.05) !important;
            border-left: 3px solid var(--primary) !important;
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>
        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title">Edit Personnel</h1>
                </div>
                <div class="top-bar-right">
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </header>

            <main class="content-wrapper">
                <form method="POST" enctype="multipart/form-data" class="edit-container">
                    <?php if ($message): ?>
                        <div class="alert alert-danger mb-4">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-grid-main">
                        <!-- Left Column: Avatar -->
                        <div class="avatar-preview-section">
                            <?php if ($user_to_edit['profile_picture']): ?>
                                <img src="../<?php echo htmlspecialchars($user_to_edit['profile_picture']); ?>"
                                    class="preview-circle" id="imgPreview">
                            <?php else: ?>
                                <div class="preview-circle" id="imgPlaceholder">
                                    <?php echo strtoupper(substr($user_to_edit['full_name'], 0, 1)); ?>
                                </div>
                                <img src="" class="preview-circle" id="imgPreview" style="display: none;">
                            <?php endif; ?>

                            <label class="btn btn-secondary btn-sm w-100"
                                style="cursor: pointer; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 600;">
                                <i class="bi bi-camera" style="margin-right: 8px;"></i> Change Photo
                                <input type="file" name="profile_picture" hidden onchange="previewImage(this)">
                            </label>
                            <p class="text-muted mt-3" style="font-size: 0.75rem;">JPG, PNG or WEBP. Max 2MB.</p>
                        </div>

                        <!-- Right Column: Details -->
                        <div class="edit-card">
                            <div class="form-section-title"><i class="bi bi-person-badge"></i> Personal details</div>
                            <div class="form-grid-inner">
                                <div class="form-group full-width">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" required
                                        value="<?php echo htmlspecialchars($user_to_edit['full_name']); ?>"
                                        placeholder="Full Name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Age</label>
                                    <input type="number" name="age" class="form-control"
                                        value="<?php echo $user_to_edit['age']; ?>" placeholder="Enter age">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Sex</label>
                                    <select name="sex" class="form-select">
                                        <option value="Male" <?php echo $user_to_edit['sex'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $user_to_edit['sex'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-section-title"><i class="bi bi-briefcase"></i> Professional assignment
                            </div>
                            <div class="form-grid-inner">
                                <div class="form-group full-width">
                                    <label class="form-label">Office / Station</label>
                                    <select name="office_station" id="office_select" class="form-control" required>
                                        <option value="<?php echo htmlspecialchars($user_to_edit['office_station']); ?>"
                                            selected>
                                            <?php echo htmlspecialchars($user_to_edit['office_station']); ?>
                                        </option>
                                        <optgroup label="OSDS">
                                            <option value="ADMINISTRATIVE (PERSONEL)">ADMINISTRATIVE (PERSONEL)</option>
                                            <option value="ADMINISTRATIVE (PROPERTY AND SUPPLY)">ADMINISTRATIVE
                                                (PROPERTY AND SUPPLY)</option>
                                            <option value="ADMINISTRATIVE (RECORDS)">ADMINISTRATIVE (RECORDS)</option>
                                            <option value="ADMINISTRATIVE (CASH)">ADMINISTRATIVE (CASH)</option>
                                            <option value="ADMINISTRATIVE (GENERAL SERVICES)">ADMINISTRATIVE (GENERAL
                                                SERVICES)</option>
                                            <option value="FINANCE (ACCOUNTING)">FINANCE (ACCOUNTING)</option>
                                            <option value="FINANCE (BUDGET)">FINANCE (BUDGET)</option>
                                            <option value="LEGAL">LEGAL</option>
                                            <option value="ICT">ICT</option>
                                        </optgroup>
                                        <optgroup label="SGOD">
                                            <option value="SCHOOL GOVERNANCE AND OPERATION DIVISION">SCHOOL GOVERNANCE
                                                AND OPERATION DIVISION</option>
                                            <option value="SCHOOL MANAGEMENT MONITORING & EVALUATION">SCHOOL MANAGEMENT
                                                MONITORING & EVALUATION</option>
                                            <option value="HUMAN RESOURCES DEVELOPMENT">HUMAN RESOURCES DEVELOPMENT
                                            </option>
                                            <option value="DISASTER RISK REDUCTION AND MANAGEMENT">DISASTER RISK
                                                REDUCTION AND MANAGEMENT</option>
                                            <option value="EDUCATION FACILITIES">EDUCATION FACILITIES</option>
                                            <option value="SCHOOL HEALTH AND NUTRITION">SCHOOL HEALTH AND NUTRITION
                                            </option>
                                            <option value="SCHOOL HEALTH AND NUTRITION (DENTAL)">SCHOOL HEALTH AND
                                                NUTRITION (DENTAL)</option>
                                            <option value="SCHOOL HEALTH AND NUTRITION (MEDICAL)">SCHOOL HEALTH AND
                                                NUTRITION (MEDICAL)</option>
                                        </optgroup>
                                        <optgroup label="CID">
                                            <option value="CURRICULUM IMPLEMENTATION DIVISION">CURRICULUM IMPLEMENTATION
                                                DIVISION</option>
                                            <option
                                                value="CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)">
                                                CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)</option>
                                            <option
                                                value="CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)">
                                                CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)
                                            </option>
                                            <option
                                                value="CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)">
                                                CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)
                                            </option>
                                            <option
                                                value="CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)">
                                                CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)
                                            </option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="form-group full-width">
                                    <label class="form-label">Position / Designation</label>
                                    <input type="text" name="position" class="form-control"
                                        value="<?php echo htmlspecialchars($user_to_edit['position']); ?>"
                                        placeholder="Enter position">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Rating Period</label>
                                    <input type="text" name="rating_period" class="form-control"
                                        value="<?php echo htmlspecialchars($user_to_edit['rating_period']); ?>"
                                        placeholder="e.g. 2023-2024">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Area of Specialization</label>
                                    <input type="text" name="area_of_specialization" class="form-control"
                                        value="<?php echo htmlspecialchars($user_to_edit['area_of_specialization']); ?>"
                                        placeholder="e.g. Management">
                                </div>
                            </div>

                            <div class="form-section-title"><i class="bi bi-shield-lock"></i> Account access</div>
                            <div class="form-grid-inner">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required
                                        value="<?php echo htmlspecialchars($user_to_edit['username']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Reset Password (Blank to keep)</label>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                                </div>
                                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                    <div class="form-group full-width">
                                        <label class="form-label">System Role</label>
                                        <select name="role" class="form-select">
                                            <option value="user" <?php echo $user_to_edit['role'] === 'user' ? 'selected' : ''; ?>>L&D Personnel</option>
                                            <option value="hr" <?php echo $user_to_edit['role'] === 'hr' ? 'selected' : ''; ?>>HR Personnel</option>
                                            <option value="immediate_head" <?php echo $user_to_edit['role'] === 'immediate_head' ? 'selected' : ''; ?>>Immediate
                                                Head</option>
                                            <option value="admin" <?php echo $user_to_edit['role'] === 'admin' ? 'selected' : ''; ?>>System Admin</option>
                                            <option value="super_admin" <?php echo $user_to_edit['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-2 text-center">
                                <button type="submit" class="btn-save">
                                    <i class="bi bi-check-circle"></i> Save All Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new TomSelect('#office_select', {
                create: true,
                placeholder: 'Select or type office...'
            });
        });

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('imgPreview');
                    const placeholder = document.getElementById('imgPlaceholder');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (placeholder) placeholder.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>