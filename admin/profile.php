<?php
session_start();
require '../includes/init_repos.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head' && $_SESSION['role'] !== 'head_hr')) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';
$is_super_admin = ($_SESSION['role'] === 'super_admin');

// Handle Certificate Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_certificate'])) {
    $activity_id = (int) $_POST['activity_id'];

    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/certificates/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $fileExtension = strtolower(pathinfo($_FILES['certificate']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = uniqid() . '_cert_' . $activity_id . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['certificate']['tmp_name'], $targetPath)) {
                $dbPath = 'uploads/certificates/' . $fileName;
                if ($activityRepo->updateActivity($activity_id, $user_id, ['certificate_path' => $dbPath])) {
                    $message = "Certificate uploaded successfully!";
                    $messageType = "success";
                }
            }
        } else {
            $message = "Invalid file type. Only PDF, JPG, and PNG are allowed.";
            $messageType = "error";
        }
    }
}

// Handle form submission (Super Admin / Head HR / Admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['update_profile_admin']) || isset($_POST['update_profile_user']))) {
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station']);
    $position = trim($_POST['position']);
    $password = $_POST['password'];

    $updateData = [
        'full_name' => $full_name,
        'office_station' => $office_station,
        'position' => $position
    ];

    // Handle Profile Picture
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/profile_pics/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['profile_picture']['name']));
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            $updateData['profile_picture'] = 'uploads/profile_pics/' . $fileName;
        }
    }

    if ($password) {
        $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($_SESSION['role'] === 'immediate_head') {
        $updateData['age'] = (int) $_POST['age'];
        $updateData['sex'] = trim($_POST['sex']);
        $updateData['rating_period'] = trim($_POST['rating_period']);
        $updateData['area_of_specialization'] = trim($_POST['area_of_specialization']);
    }

    if ($userRepo->updateUserProfile($user_id, $updateData)) {
        $_SESSION['toast'] = ['title' => 'Profile Updated', 'message' => 'Your profile has been successfully updated.', 'type' => 'success'];
        $_SESSION['full_name'] = $full_name;

        // Update session profile picture if changed
        if (isset($updateData['profile_picture'])) {
            $_SESSION['profile_picture'] = $updateData['profile_picture'];
        }

        // Log the action (Skip for Super Admin as per request)
        if ($_SESSION['role'] !== 'super_admin') {
            $logRepo->logAction($user_id, 'Profile Updated', 'User updated their personal information and/or profile picture.');
        }
    } else {
        $_SESSION['toast'] = ['title' => 'Update Failed', 'message' => 'There was an error updating your profile.', 'type' => 'error'];
    }
}

// Fetch current user data
$user = $userRepo->getUserById($user_id);

// Fetch activities for certificate hub
$activities = [];
if (!$is_super_admin) {
    $activities = $activityRepo->getActivitiesByUser($user_id);
}

// Handle ILDN Management
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_ildn'])) {
        $need_text = trim($_POST['need_text']);
        $description = trim($_POST['description'] ?? '');
        if (!empty($need_text)) {
            $ildnRepo->createILDN($user_id, $need_text, $description);
            $message = "Development need added successfully!";
            $messageType = "success";
        }
    } elseif (isset($_POST['delete_ildn'])) {
        $ildn_id = (int) $_POST['ildn_id'];
        $ildnRepo->deleteILDN($ildn_id, $user_id);
        $message = "Development need removed.";
        $messageType = "success";
    } elseif (isset($_POST['edit_ildn'])) {
        $ildn_id = (int) $_POST['ildn_id'];
        $need_text = trim($_POST['need_text']);
        $description = trim($_POST['description'] ?? '');
        if (!empty($need_text)) {
            $ildnRepo->updateILDN($ildn_id, $user_id, [
                'need_text' => $need_text,
                'description' => $description
            ]);
            $message = "Development need updated successfully!";
            $messageType = "success";
        }
    }
}

// Fetch user's ILDNs with usage count
$user_ildns = $ildnRepo->getILDNsByUser($user_id);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - LDP</title>
    <?php require '../includes/admin_head.php'; ?>
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        /* Redesign Styles for Admin/Immediate Head */
        :root {
            --card-border: #f1f5f9;
            --hub-bg: #f8fafc;
        }

        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .profile-hero {
            background: var(--primary-gradient);
            padding: 24px 30px;
            border-radius: 16px;
            display: flex;
            gap: 24px;
            align-items: center;
            margin-bottom: 24px;
            color: white;
            box-shadow: 0 4px 12px -2px rgba(15, 76, 117, 0.2);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 40px;
            transform: rotate(15deg);
        }

        .hero-avatar {
            width: 90px;
            height: 90px;
            border-radius: 14px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 800;
        }

        .hero-info h2 {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0 0 2px 0;
            letter-spacing: -0.5px;
            color: white !important;
        }

        .hero-info p {
            opacity: 0.85;
            font-weight: 600;
            margin: 0;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white !important;
        }

        .stats-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .profile-main-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: stretch;
            margin-bottom: 40px;
        }

        .ildn-column,
        .stats-column {
            display: flex;
            flex-direction: column;
        }

        .ildn-column .dashboard-card,
        .stats-column .stats-grid {
            flex-grow: 1;
        }

        .ildn-list-scroll {
            max-height: 320px;
            overflow-y: auto;
            padding-right: 10px;
            margin-right: -10px;
        }

        .ildn-list-scroll::-webkit-scrollbar,
        .submissions-list-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .ildn-list-scroll::-webkit-scrollbar-track,
        .submissions-list-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .ildn-list-scroll::-webkit-scrollbar-thumb,
        .submissions-list-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .ildn-list-scroll::-webkit-scrollbar-thumb:hover,
        .submissions-list-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .submissions-list-scroll {
            max-height: 520px;
            overflow-y: auto;
            padding-right: 12px;
            margin-right: -12px;
        }

        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #eef2f6;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            border-color: var(--primary-light);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            display: block;
            line-height: 1.1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .section-header {
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 16px 16px 0 0;
            border: 1px solid #eef2f6;
            border-bottom: none;
            margin-bottom: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #eef2f6;
            border-radius: 0 0 16px 16px;
            padding: 20px;
        }

        .activity-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #eef2f6;
            padding: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 0 4px 12px -2px rgba(15, 76, 117, 0.08);
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .activity-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -5px rgba(15, 76, 117, 0.15);
            border-color: #F57C00;
        }

        .activity-type {
            font-size: 0.55rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #ffffff;
            background: var(--primary);
            padding: 2px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 8px;
            letter-spacing: 0.8px;
            width: fit-content;
        }

        .activity-title {
            font-weight: 800;
            color: var(--primary);
            font-size: 0.85rem;
            margin-bottom: 8px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.6em;
        }

        .activity-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.72rem;
            color: #64748b;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #f1f5f9;
        }

        .activity-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }

        .cert-upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            background: #f8fafc;
            color: #64748b;
        }

        .cert-upload-zone:hover {
            background: #fff7ed;
            border-color: #F57C00;
            color: #F57C00;
        }

        .has-cert {
            background: #fff7ed;
            border: 1px solid #ffedd5;
            padding: 10px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Account Settings Toggle */
        .account-settings-card {
            display: none;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .toggle-settings-btn {
            background: white;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-settings-btn:hover,
        .toggle-settings-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .form-grid-profile {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        /* Custom Modal Styles */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeIn 0.2s ease;
        }

        .custom-modal {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            text-align: center;
            transform: translateY(20px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .custom-modal.show {
            transform: translateY(0);
        }

        .modal-icon-container {
            width: 60px;
            height: 60px;
            background: #fee2e2;
            color: #dc2626;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.75rem;
            margin: 0 auto 20px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
        }

        .modal-btn-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .modal-btn-delete {
            background: #dc2626;
            color: white;
        }

        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1.5px solid #eef2f6;
            border-radius: 12px;
            color: #1e293b;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-control[readonly] {
            background: #f8fafc;
            border-color: #eef2f6;
            color: #475569;
            cursor: default;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(15, 76, 117, 0.1);
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #dbeafe;
            padding: 12px 16px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-info i {
            font-size: 1.1rem;
            color: #3b82f6;
        }

        @media (max-width: 768px) {
            .form-grid-profile {
                grid-template-columns: 1fr;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Super Admin Specifics - Keep Integrated */
        .ts-control {
            border: 1px solid var(--border-color) !important;
            border-radius: var(--radius-md) !important;
            padding: 12px 14px 12px 42px !important;
            background: white !important;
            color: var(--text-primary) !important;
            height: 48px !important;
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
                        <h1 class="page-title"><?php echo $is_super_admin ? 'Admin Profile' : 'My Profile'; ?></h1>
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
                    <?php if (!$is_super_admin): ?>
                        <button id="toggleSettings" class="toggle-settings-btn">
                            <i class="bi bi-person-gear"></i> Account Information
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <main class="content-wrapper">
                <?php if ($is_super_admin): ?>
                    <div style="max-width: 900px; margin: 0 auto;">
                        <div class="dashboard-card hover-elevate">
                            <div class="card-header">
                                <h2><i class="bi bi-person-vcard text-gradient"></i> Core Identification</h2>
                            </div>
                            <div class="card-body" style="padding: 30px;">
                                <form method="POST" action="">
                                    <input type="hidden" name="update_profile_admin" value="1">
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
                <?php else: ?>
                    <!-- Redesign for Admin/Immediate Head -->
                    <div class="profile-container">

                        <!-- Hero Section -->
                        <div class="profile-hero">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" class="hero-avatar">
                            <?php else: ?>
                                <div class="hero-avatar">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="hero-info">
                                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                                <p>
                                    <i class="bi bi-person-badge"></i>
                                    <?php echo htmlspecialchars($user['position'] ?: 'Administrative Professional'); ?>
                                    <span style="opacity: 0.5; margin: 0 4px;">•</span>
                                    <i class="bi bi-building"></i>
                                    <?php echo htmlspecialchars($user['office_station']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Account Information (Hidden by default) -->
                        <div id="accountSettings" class="account-settings-card">
                            <div class="dashboard-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0;">
                                <div class="card-header"
                                    style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 15px 25px;">
                                    <h2 style="font-size: 1.1rem; margin: 0;"><i class="bi bi-shield-lock"></i> Account
                                        Settings</h2>
                                </div>
                                <div class="card-body" style="padding: 30px;">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="update_profile_user" value="1">
                                        <div class="form-group mb-4">
                                            <label class="form-label"
                                                style="display: block; margin-bottom: 15px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">Personal
                                                Avatar</label>
                                            <div
                                                style="display: flex; align-items: center; gap: 25px; background: #f8fafc; padding: 20px; border-radius: 20px; border: 1.5px solid #eef2f6;">
                                                <div id="avatarPreviewContainer"
                                                    style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 4px solid white; box-shadow: 0 10px 20px rgba(0,0,0,0.08); flex-shrink: 0; background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                                                    <?php if (!empty($user['profile_picture'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                                            id="currentAvatar"
                                                            style="width: 100%; height: 100%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div
                                                            style="font-size: 2.5rem; font-weight: 800; color: var(--primary); opacity: 0.3;">
                                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="flex: 1;">
                                                    <div style="margin-bottom: 12px;">
                                                        <button type="button"
                                                            onclick="document.getElementById('profile_pic_input').click()"
                                                            class="btn btn-outline-primary"
                                                            style="height: 42px; padding: 0 20px; border-radius: 12px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                                            <i class="bi bi-camera"></i> Update Photo
                                                        </button>
                                                        <input type="file" name="profile_picture" id="profile_pic_input"
                                                            style="display: none;" accept="image/*"
                                                            onchange="updateFileName(this)">
                                                    </div>
                                                    <div id="fileNameDisplay"
                                                        style="font-size: 0.82rem; color: #94a3b8; font-weight: 500;">
                                                        Recommended: Square image, max 2MB (JPG, PNG)
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <script>
                                            function updateFileName(input) {
                                                const display = document.getElementById('fileNameDisplay');
                                                if (input.files && input.files[0]) {
                                                    display.innerHTML = `<i class="bi bi-file-earmark-check"></i> Selected: <strong>${input.files[0].name}</strong>`;
                                                    display.style.color = "var(--primary)";
                                                }
                                            }
                                        </script>
                                        <div class="form-grid-profile">
                                            <div>
                                                <div class="form-group">
                                                    <label class="form-label">Full Name</label>
                                                    <input type="text" name="full_name" class="form-control" required
                                                        value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Position / Designation</label>
                                                    <input type="text" name="position" class="form-control"
                                                        value="<?php echo htmlspecialchars($user['position'] ?: ''); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Office / Station</label>
                                                    <input type="text" name="office_station" class="form-control" required
                                                        value="<?php echo htmlspecialchars($user['office_station']); ?>">
                                                </div>
                                            </div>
                                            <div>
                                                <div class="form-group">
                                                    <label class="form-label">Rating Period</label>
                                                    <input type="text" name="rating_period" class="form-control"
                                                        value="<?php echo htmlspecialchars($user['rating_period'] ?: ''); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Area of Specialization</label>
                                                    <input type="text" name="area_of_specialization" class="form-control"
                                                        value="<?php echo htmlspecialchars($user['area_of_specialization'] ?: ''); ?>">
                                                </div>
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                                    <div class="form-group">
                                                        <label class="form-label">Age</label>
                                                        <input type="number" name="age" class="form-control"
                                                            value="<?php echo htmlspecialchars($user['age']); ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Sex</label>
                                                        <select name="sex" class="form-control">
                                                            <option value="">Select...</option>
                                                            <option value="Male" <?php echo $user['sex'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                            <option value="Female" <?php echo $user['sex'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label class="form-label">New Password (Leave blank to keep current)</label>
                                            <input type="password" name="password" class="form-control"
                                                placeholder="••••••••">
                                        </div>
                                        <div style="text-align: right; margin-top: 20px;">
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <?php
                        $total_activities = count($activities);
                        $with_certs = 0;
                        foreach ($activities as $act)
                            if ($act['certificate_path'])
                                $with_certs++;

                        $unaddressed_ildns_count = 0;
                        foreach ($user_ildns as $ildn)
                            if ($ildn['usage_count'] == 0)
                                $unaddressed_ildns_count++;
                        ?>

                        <div class="profile-main-grid">
                            <div class="ildn-column">
                                <div class="section-header">
                                    <h2 class="section-title"><i class="bi bi-lightbulb" style="color: #F57C00;"></i>
                                        Individual Learning Needs</h2>
                                </div>
                                <div class="dashboard-card" style="margin-bottom: 0;">
                                    <div class="card-body" style="padding: 25px;">
                                        <form method="POST" class="form-group"
                                            style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                                <input type="text" name="need_text" class="form-control"
                                                    placeholder="Enter a learning need..." required
                                                    style="height: 50px; border-radius: 12px; background: #f8fafc; border: 1.5px solid #eef2f6;">
                                                <textarea name="description" class="form-control"
                                                    placeholder="What is this all about? (Optional)"
                                                    style="height: 100px; border-radius: 12px; background: #f8fafc; border: 1.5px solid #eef2f6; resize: none; padding-top: 12px;"></textarea>
                                            </div>
                                            <button type="submit" name="add_ildn" class="btn btn-primary"
                                                style="width: 100%; height: 48px; border-radius: 12px; font-weight: 700; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(15, 76, 117, 0.15);">
                                                <i class="bi bi-plus-lg"></i> Add
                                            </button>
                                        </form>

                                        <?php if (empty($user_ildns)): ?>
                                            <div
                                                style="text-align: center; padding: 20px; color: #64748b; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1;">
                                                <i class="bi bi-info-circle"
                                                    style="font-size: 1.2rem; display: block; margin-bottom: 8px; color: #cbd5e1;"></i>
                                                You haven't set any development needs yet.
                                            </div>
                                        <?php else: ?>
                                            <div class="ildn-list-scroll">
                                                <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                                                    <?php foreach ($user_ildns as $ildn):
                                                        $is_addressed = $ildn['usage_count'] > 0;
                                                        $card_style = $is_addressed
                                                            ? "background: #f0fdf4; border: 1px solid #bbf7d0; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.08);"
                                                            : "background: white; border: 1px solid #eef2f6; box-shadow: 0 1px 3px rgba(0,0,0,0.05);";
                                                        ?>
                                                        <div style="<?php echo $card_style; ?> border-radius: 12px; padding: 16px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease; cursor: pointer;"
                                                            onclick="showILDNDescription(<?php echo $ildn['id']; ?>, '<?php echo addslashes(htmlspecialchars($ildn['need_text'])); ?>', '<?php echo addslashes(htmlspecialchars($ildn['description'] ?: 'No description provided.')); ?>')">
                                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                                <div
                                                                    style="font-weight: 700; color: <?php echo $is_addressed ? '#15803d' : 'var(--primary)'; ?>; font-size: 0.95rem;">
                                                                    <?php echo htmlspecialchars($ildn['need_text']); ?>
                                                                </div>
                                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                                    <?php if ($is_addressed): ?>
                                                                        <span
                                                                            style="background: #16a34a; color: white; font-size: 0.65rem; font-weight: 800; padding: 2px 8px; border-radius: 20px; text-transform: uppercase;">
                                                                            <i class="bi bi-check-circle-fill"></i> Addressed
                                                                            <?php echo $ildn['usage_count']; ?>x
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span
                                                                            style="color: #94a3b8; font-size: 0.7rem; font-weight: 600;">
                                                                            <i class="bi bi-clock"></i> Not yet addressed
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn btn-sm"
                                                                onclick="event.stopPropagation(); confirmDeleteILDN(<?php echo $ildn['id']; ?>)"
                                                                style="padding: 6px; color: #dc2626; background: <?php echo $is_addressed ? '#fecaca' : '#fef2f2'; ?>; border: none; border-radius: 8px; width: 32px; height: 32px;">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="stats-column">
                                <div class="section-header">
                                    <h2 class="section-title"><i class="bi bi-graph-up-arrow" style="color: #F57C00;"></i>
                                        Stats</h2>
                                </div>
                                <div class="stats-grid">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;"><i
                                                class="bi bi-journal-bookmark"></i></div>
                                        <div>
                                            <span class="stat-value"><?php echo $total_activities; ?></span>
                                            <span class="stat-label">Total Activities</span>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;"><i
                                                class="bi bi-patch-check"></i></div>
                                        <div>
                                            <span class="stat-value"><?php echo $with_certs; ?></span>
                                            <span class="stat-label">With Certificates</span>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon"
                                            style="<?php echo $unaddressed_ildns_count > 0 ? 'background: #fef2f2; color: #dc2626;' : 'background: #f0fdf4; color: #16a34a;'; ?>">
                                            <i
                                                class="bi <?php echo $unaddressed_ildns_count > 0 ? 'bi-exclamation-octagon-fill' : 'bi-check-all'; ?>"></i>
                                        </div>
                                        <div>
                                            <span class="stat-value"><?php echo $unaddressed_ildns_count; ?></span>
                                            <span class="stat-label">Unaddressed Needs</span>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: #fef2f2; color: #dc2626;"><i
                                                class="bi bi-clock-history"></i></div>
                                        <div>
                                            <span class="stat-value"><?php echo $total_activities - $with_certs; ?></span>
                                            <span class="stat-label">Pending Certs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Certificate Hub Section -->
                        <div class="section-header">
                            <h2 class="section-title"><i class="bi bi-trophy"></i> Activity Certificates</h2>
                        </div>

                        <div class="submissions-list-scroll">
                            <div class="certificate-grid">
                                <?php if (empty($activities)): ?>
                                    <div class="empty-state" style="grid-column: 1 / -1;">
                                        <div style="font-size: 3rem; color: #e2e8f0; margin-bottom: 20px;"><i
                                                class="bi bi-file-earmark-x"></i></div>
                                        <h3 style="color: #64748b; font-weight: 700;">No activities found</h3>
                                        <p style="color: #94a3b8; max-width: 400px; margin: 0 auto;">No records yet to manage
                                            certificates.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($activities as $act): ?>
                                        <div class="activity-card">
                                            <div onclick="location.href='../pages/view_activity.php?id=<?php echo $act['id']; ?>'"
                                                style="cursor: pointer;">
                                                <span
                                                    class="activity-type"><?php echo htmlspecialchars($act['type_ld'] ?: 'Professional Development'); ?></span>
                                                <h3 class="activity-title" title="<?php echo htmlspecialchars($act['title']); ?>">
                                                    <?php echo htmlspecialchars($act['title']); ?>
                                                </h3>
                                                <div class="activity-meta">
                                                    <span><i class="bi bi-calendar-event"></i>
                                                        <?php echo date('M d, Y', strtotime($act['date_attended'])); ?></span>
                                                    <span><i class="bi bi-geo-alt"></i>
                                                        <?php echo htmlspecialchars(substr($act['venue'], 0, 20)); ?>...</span>
                                                </div>
                                            </div>

                                            <?php if ($act['certificate_path']): ?>
                                                <div class="has-cert">
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <i class="bi bi-patch-check-fill"
                                                            style="font-size: 1.5rem; color: #F57C00;"></i>
                                                        <div style="font-size: 0.8rem; font-weight: 800; color: var(--primary);">
                                                            CERTIFICATE READY
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 8px;">
                                                        <a href="../<?php echo $act['certificate_path']; ?>" target="_blank"
                                                            class="btn btn-primary btn-sm"
                                                            style="padding: 4px 10px; font-size: 0.7rem; font-weight: 700;">View</a>
                                                        <button
                                                            onclick="document.getElementById('file-input-<?php echo $act['id']; ?>').click()"
                                                            class="btn btn-outline-primary btn-sm"
                                                            style="padding: 4px 10px; font-size: 0.7rem; font-weight: 700;">Change</button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="cert-upload-zone"
                                                    onclick="document.getElementById('file-input-<?php echo $act['id']; ?>').click()">
                                                    <i class="bi bi-cloud-arrow-up-fill" style="color: #F57C00;"></i>
                                                    <div
                                                        style="font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase;">
                                                        Upload Certificate
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Hidden Upload Form -->
                                            <form method="POST" enctype="multipart/form-data"
                                                id="upload-form-<?php echo $act['id']; ?>" style="display: none;">
                                                <input type="hidden" name="upload_certificate" value="1">
                                                <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                                <input type="file" name="certificate" id="file-input-<?php echo $act['id']; ?>"
                                                    onchange="this.form.submit()" accept=".pdf,.jpg,.jpeg,.png">
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
            </footer>
        </div>
    </div>
    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <!-- Custom Delete Confirmation Modal -->
    <div id="deleteModalOverlay" class="custom-modal-overlay">
        <div class="custom-modal">
            <div class="modal-icon-container">
                <i class="bi bi-trash3-fill"></i>
            </div>
            <h3 class="modal-title">Delete Learning Need?</h3>
            <p class="modal-text">Are you sure you want to remove this learning need? this action cannot be undone.</p>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <form id="deleteILDNForm" method="POST" style="flex: 1; margin: 0;">
                    <input type="hidden" name="ildn_id" id="modal_ildn_id">
                    <input type="hidden" name="delete_ildn" value="1">
                    <button type="submit" class="modal-btn modal-btn-delete">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Description Modal -->
    <div id="descriptionModalOverlay" class="custom-modal-overlay">
        <div class="custom-modal" style="max-width: 500px;">
            <div class="modal-icon-container" style="background: #e0f2fe; color: #0284c7;">
                <i class="bi bi-info-circle-fill"></i>
            </div>

            <!-- View Mode -->
            <div id="desc_view_mode">
                <h3 class="modal-title" id="desc_modal_title">Learning Need</h3>
                <p class="modal-text" id="desc_modal_text"
                    style="text-align: left; white-space: pre-wrap; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #eef2f6; max-height: 300px; overflow-y: auto;">
                    Description goes here...
                </p>
                <div class="modal-actions" style="margin-top: 20px;">
                    <button type="button" class="modal-btn modal-btn-cancel"
                        onclick="closeDescriptionModal()">Close</button>
                    <button type="button" class="modal-btn" style="background: var(--primary); color: white;"
                        onclick="toggleEditMode(true)">Edit</button>
                </div>
            </div>

            <!-- Edit Mode -->
            <div id="desc_edit_mode" style="display: none;">
                <h3 class="modal-title">Edit Learning Need</h3>
                <form method="POST">
                    <input type="hidden" name="edit_ildn" value="1">
                    <input type="hidden" name="ildn_id" id="edit_ildn_id">
                    <div class="form-group" style="text-align: left;">
                        <label class="form-label">Learning Need</label>
                        <input type="text" name="need_text" id="edit_need_text" class="form-control" required
                            style="background: #f8fafc;">
                    </div>
                    <div class="form-group" style="text-align: left;">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control"
                            style="height: 150px; background: #f8fafc; resize: none;"></textarea>
                    </div>
                    <div class="modal-actions" style="margin-top: 20px;">
                        <button type="button" class="modal-btn modal-btn-cancel"
                            onclick="toggleEditMode(false)">Cancel</button>
                        <button type="submit" class="modal-btn" style="background: #10b981; color: white;">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/profile-actions.js"></script>
    <script>
        function showILDNDescription(id, title, description) {
            document.getElementById('desc_modal_title').innerText = title;
            document.getElementById('desc_modal_text').innerText = description;

            // Populate edit fields
            document.getElementById('edit_ildn_id').value = id;
            document.getElementById('edit_need_text').value = title;
            document.getElementById('edit_description').value = description === 'No description provided.' ? '' : description;

            toggleEditMode(false); // Ensure we start in view mode

            const modal = document.getElementById('descriptionModalOverlay');
            modal.style.display = 'flex';
            setTimeout(() => modal.querySelector('.custom-modal').classList.add('show'), 10);
        }

        function toggleEditMode(isEdit) {
            document.getElementById('desc_view_mode').style.display = isEdit ? 'none' : 'block';
            document.getElementById('desc_edit_mode').style.display = isEdit ? 'block' : 'none';
        }

        function closeDescriptionModal() {
            const modal = document.getElementById('descriptionModalOverlay');
            modal.querySelector('.custom-modal').classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const deleteModal = document.getElementById('deleteModalOverlay');
            const descModal = document.getElementById('descriptionModalOverlay');
            if (event.target == deleteModal) closeDeleteModal();
            if (event.target == descModal) closeDescriptionModal();
        }
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($is_super_admin): ?>
                new TomSelect('#office_select', {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    },
                    placeholder: "Type to search office...",
                    maxOptions: 50
                });
            <?php endif; ?>
        });

        <?php if ($message || isset($_SESSION['toast'])): ?>
            <?php
            $t_title = $messageType === 'success' ? 'Success' : 'Notice';
            $t_msg = $message;
            $t_type = $messageType;

            if (isset($_SESSION['toast'])) {
                $t_title = $_SESSION['toast']['title'];
                $t_msg = $_SESSION['toast']['message'];
                $t_type = $_SESSION['toast']['type'];
                unset($_SESSION['toast']);
            }
            ?>
            if (typeof showToast === 'function') {
                showToast("<?php echo $t_title; ?>", "<?php echo $t_msg; ?>", "<?php echo $t_type; ?>");
            }
        <?php endif; ?>
    </script>
</body>

</html>