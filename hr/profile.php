<?php
session_start();
require '../includes/init_repos.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageType = '';

// Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station']);
    $position = trim($_POST['position']);
    $password = $_POST['password'];

    $updateData = [
        'full_name' => $full_name,
        'office_station' => $office_station,
        'position' => $position,
        'rating_period' => trim($_POST['rating_period']),
        'area_of_specialization' => trim($_POST['area_of_specialization']),
        'age' => (int) $_POST['age'],
        'sex' => trim($_POST['sex'])
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

    if ($userRepo->updateUserProfile($_SESSION['user_id'], $updateData)) {
        $_SESSION['toast'] = ['title' => 'Profile Updated', 'message' => 'Your profile has been successfully updated.', 'type' => 'success'];
        $_SESSION['full_name'] = $full_name;

        if (isset($updateData['profile_picture'])) {
            $_SESSION['profile_picture'] = $updateData['profile_picture'];
        }

        $logRepo->logAction($_SESSION['user_id'], 'Profile Updated', 'HR updated their own profile information and/or profile picture.');
        header("Location: profile.php");
        exit;
    } else {
        $message = "Error updating profile.";
        $messageType = "error";
    }
}

// Fetch current user data
$user = $userRepo->getUserById($_SESSION['user_id']);

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
                if ($activityRepo->updateActivity($activity_id, $_SESSION['user_id'], ['certificate_path' => $dbPath])) {
                    $logRepo->logAction($_SESSION['user_id'], 'Updated Certificate', "Activity ID: $activity_id");

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

// Fetch activities for certificate hub
$activities = $activityRepo->getActivitiesByUser($_SESSION['user_id']);

// Handle ILDN Management
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_ildn'])) {
        $need_text = trim($_POST['need_text']);
        $description = trim($_POST['description'] ?? '');
        if (!empty($need_text)) {
            $ildnRepo->createILDN($_SESSION['user_id'], $need_text, $description);
            $message = "Development need added successfully!";
            $messageType = "success";
        }
    } elseif (isset($_POST['delete_ildn'])) {
        $ildn_id = (int) $_POST['ildn_id'];
        $ildnRepo->deleteILDN($ildn_id, $_SESSION['user_id']);
        $message = "Development need removed.";
        $messageType = "success";
    } elseif (isset($_POST['edit_ildn'])) {
        $ildn_id = (int) $_POST['ildn_id'];
        $need_text = trim($_POST['need_text']);
        $description = trim($_POST['description'] ?? '');
        if (!empty($need_text)) {
            $ildnRepo->updateILDN($ildn_id, $_SESSION['user_id'], [
                'need_text' => $need_text,
                'description' => $description
            ]);
            $message = "Development need updated successfully!";
            $messageType = "success";
        }
    }
}

// Fetch user's ILDNs with usage count
$user_ildns = $ildnRepo->getILDNsByUser($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Certificates - HR Panel</title>
    <?php require 'includes/hr_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
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
            /* Squircle for sharp look */
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
        }

        .hero-info p {
            opacity: 0.85;
            font-weight: 600;
            margin: 0;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
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
        .stats-column .stats-grid-container {
            flex-grow: 1;
        }

        .ildn-list-scroll {
            max-height: 320px;
            overflow-y: auto;
            padding-right: 10px;
            margin-right: -10px;
        }

        .ildn-list-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .ildn-list-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .ildn-list-scroll::-webkit-scrollbar-thumb {
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

        .submissions-list-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .submissions-list-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .submissions-list-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        @media (max-width: 992px) {
            .profile-main-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
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
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
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

        .modal-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .modal-text {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 25px;
            line-height: 1.5;
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
            font-size: 0.9rem;
            border: none;
            transition: all 0.2s ease;
        }

        .modal-btn-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .modal-btn-cancel:hover {
            background: #e2e8f0;
        }

        .modal-btn-delete {
            background: #dc2626;
            color: white;
        }

        .modal-btn-delete:hover {
            background: #b91c1c;
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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

        .section-title i {
            color: #F57C00;
        }

        .scrollable-cert-container {
            background: #f8fafc;
            border: 1px solid #eef2f6;
            border-radius: 0 0 16px 16px;
            padding: 20px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
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

        .activity-meta i {
            color: #F57C00;
            font-size: 0.85rem;
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

        .cert-upload-zone i {
            font-size: 1.4rem;
            display: block;
            margin-bottom: 4px;
        }

        .has-cert {
            background: #fff7ed;
            border: 1px solid #ffedd5;
            padding: 10px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(245, 124, 0, 0.05);
        }

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

        .toggle-settings-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .toggle-settings-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
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
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            color: #1e293b;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.2s;
            outline: none;
        }

        .form-control[readonly] {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #334155;
            cursor: default;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 76, 117, 0.1);
            background: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            grid-column: 1 / -1;
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
                        <h1 class="page-title">My Profile</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <div class="time-section">
                            <span id="real-time-clock">
                                <?php echo date('h:i:s A'); ?>
                            </span>
                        </div>
                        <div class="date-section">
                            <i class="bi bi-calendar3"></i>
                            <span>
                                <?php echo date('F j, Y'); ?>
                            </span>
                        </div>
                    </div>
                    <button id="toggleSettings" class="toggle-settings-btn">
                        <i class="bi bi-person-gear"></i> Account Information
                    </button>
                </div>
            </header>

            <main class="content-wrapper">
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
                            <h2>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </h2>
                            <p>
                                <i class="bi bi-person-badge"></i>
                                <?php echo htmlspecialchars($user['position'] ?: 'HR Personnel'); ?>
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
                                <?php $can_edit = ($_SESSION['role'] === 'hr' || $_SESSION['role'] === 'super_admin'); ?>
                                <?php if (!$can_edit): ?>
                                    <div class="alert alert-info" style="margin-bottom: 20px; font-size: 0.9rem;">
                                        <i class="bi bi-info-circle"></i> Profile editing is restricted. Contact HR to
                                        update system-level fields.
                                    </div>
                                <?php endif; ?>


                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update_profile" value="1">
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
                                    <div class="form-grid">
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
                            <!-- Individual Learning and Development Needs -->
                            <div class="section-header">
                                <h2 class="section-title"><i class="bi bi-lightbulb" style="color: #F57C00;"></i>
                                    Individual Learning and Development Needs</h2>
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
                                                placeholder="Optional: Description or specific details..." rows="2"
                                                style="border-radius: 12px; background: #f8fafc; border: 1.5px solid #eef2f6;"></textarea>
                                        </div>
                                        <button type="submit" name="add_ildn" class="btn btn-primary"
                                            style="border-radius: 10px; font-weight: 700; height: 44px;">
                                            <i class="bi bi-plus-circle"></i> Add Competency Gap
                                        </button>
                                    </form>

                                    <div class="ildn-list-scroll">
                                        <div class="ildn-list"
                                            style="display: flex; flex-direction: column; gap: 12px;">
                                            <?php if (count($user_ildns) > 0): ?>
                                                <?php foreach ($user_ildns as $ildn): ?>
                                                    <div class="ildn-item"
                                                        style="background: white; border: 1px solid #eef2f6; padding: 16px; border-radius: 12px; position: relative; transition: all 0.2s;">
                                                        <div style="padding-right: 40px;">
                                                            <div
                                                                style="font-weight: 700; color: #1e293b; margin-bottom: 4px; font-size: 0.95rem;">
                                                                <?php echo htmlspecialchars($ildn['need_text']); ?>
                                                            </div>
                                                            <?php if ($ildn['description']): ?>
                                                                <div
                                                                    style="font-size: 0.85rem; color: #64748b; line-height: 1.4; margin-bottom: 8px;">
                                                                    <?php echo htmlspecialchars($ildn['description']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($ildn['usage_count'] > 0): ?>
                                                                <span class="badge badge-success"
                                                                    style="font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; background: #dcfce7; color: #15803d;">
                                                                    <i class="bi bi-check-circle-fill"></i> Addressed in
                                                                    <?php echo $ildn['usage_count']; ?> activity
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge badge-warning"
                                                                    style="font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; background: #fef9c3; color: #a16207;">
                                                                    <i class="bi bi-exclamation-circle"></i> Not yet addressed
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <form method="POST"
                                                            style="position: absolute; top: 12px; right: 12px; margin: 0;"
                                                            onsubmit="return confirm('Remove this item?');">
                                                            <input type="hidden" name="ildn_id"
                                                                value="<?php echo $ildn['id']; ?>">
                                                            <button type="submit" name="delete_ildn"
                                                                style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 4px; transition: color 0.2s;">
                                                                <i class="bi bi-trash3-fill" style="font-size: 1.1rem;"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="empty-state" style="padding: 40px 20px;">
                                                    <i class="bi bi-list-check"
                                                        style="font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block;"></i>
                                                    <p style="color: #64748b; font-weight: 500;">No development needs listed
                                                        yet.
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="stats-column">
                            <!-- Certificate Vault -->
                            <div class="section-header" style="margin-top: 0;">
                                <h2 class="section-title"><i class="bi bi-patch-check-fill" style="color: #F57C00;"></i>
                                    Certificate Vault</h2>
                            </div>
                            <div class="scrollable-cert-container dashboard-card" style="border-radius: 0 0 16px 16px;">
                                <div class="submissions-list-scroll">
                                    <div class="certificate-grid" style="grid-template-columns: 1fr;">
                                        <?php
                                        // Filter only activities with certificates or ready for upload (reviewed/approved)
                                        $cert_ready_activities = array_filter($activities, function ($a) {
                                            return $a['reviewed_by_supervisor'] || $a['approved_sds'];
                                        });
                                        ?>

                                        <?php if (count($cert_ready_activities) > 0): ?>
                                            <?php foreach ($cert_ready_activities as $act): ?>
                                                <div class="activity-card">
                                                    <span class="activity-type">
                                                        <?php echo htmlspecialchars($act['type_ld']); ?>
                                                    </span>
                                                    <div class="activity-title">
                                                        <?php echo htmlspecialchars($act['title']); ?>
                                                    </div>
                                                    <div class="activity-meta">
                                                        <span><i class="bi bi-calendar3"></i>
                                                            <?php echo date('M d, Y', strtotime($act['created_at'])); ?>
                                                        </span>
                                                    </div>

                                                    <?php if ($act['certificate_path']): ?>
                                                        <div class="has-cert">
                                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                                <i class="bi bi-file-earmark-pdf-fill"
                                                                    style="color: #F57C00; font-size: 1.5rem;"></i>
                                                                <div
                                                                    style="display: flex; flex-direction: column; line-height: 1.2;">
                                                                    <span
                                                                        style="font-size: 0.8rem; font-weight: 700; color: #c2410c;">Certificate
                                                                        Uploaded</span>
                                                                    <a href="../<?php echo htmlspecialchars($act['certificate_path']); ?>"
                                                                        target="_blank"
                                                                        style="font-size: 0.72rem; color: #ea580c; text-decoration: underline; font-weight: 600;">View
                                                                        File</a>
                                                                </div>
                                                            </div>
                                                            <button
                                                                onclick="document.getElementById('upload-<?php echo $act['id']; ?>').click()"
                                                                title="Replace File"
                                                                style="border: none; background: none; color: #fb923c; cursor: pointer;">
                                                                <i class="bi bi-arrow-repeat"></i>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="cert-upload-zone"
                                                            onclick="document.getElementById('upload-<?php echo $act['id']; ?>').click()">
                                                            <i class="bi bi-cloud-upload"></i>
                                                            <span style="font-size: 0.8rem; font-weight: 600;">Upload
                                                                Certificate</span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <form method="POST" enctype="multipart/form-data" style="display: none;">
                                                        <input type="hidden" name="activity_id"
                                                            value="<?php echo $act['id']; ?>">
                                                        <input type="file" name="certificate"
                                                            id="upload-<?php echo $act['id']; ?>" accept=".pdf,.jpg,.jpeg,.png"
                                                            onchange="if(confirm('Upload this certificate?')) this.form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true})); if(window.submitForm) window.submitForm(this.form) else this.form.submit();">
                                                        <input type="hidden" name="upload_certificate" value="1">
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-state">
                                                <i class="bi bi-file-earmark-lock2"
                                                    style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                                                <p style="font-size: 0.9rem; color: #64748b;">No approved activities
                                                    available for
                                                    certificate uploads yet.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>

            <footer class="user-footer">
                <p>&copy;
                    <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span>
                </p>
            </footer>
        </div>
    </div>

    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        // Settings Toggle
        const toggleBtn = document.getElementById('toggleSettings');
        const settingsCard = document.getElementById('accountSettings');

        toggleBtn.addEventListener('click', () => {
            const isHidden = settingsCard.style.display === 'none' || settingsCard.style.display === '';
            settingsCard.style.display = isHidden ? 'block' : 'none';
            toggleBtn.classList.toggle('active');
        });

        <?php if ($message): ?>
            showToast("<?php echo ($messageType === 'success') ? 'Success' : 'Notice'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
        <?php endif; ?>
    </script>
</body>

</html>