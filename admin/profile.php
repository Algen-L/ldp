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

// Handle sending notification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_notification'])) {
    $recipient_id = (int) $_POST['recipient_id'];
    $notif_message = trim($_POST['notif_message']);

    if ($recipient_id > 0 && !empty($notif_message)) {
        if ($notifRepo->sendNotification($user_id, $recipient_id, $notif_message)) {
            $_SESSION['toast'] = [
                'title' => 'Message Sent',
                'message' => 'Your notification has been successfully delivered.',
                'type' => 'success'
            ];
            $logRepo->logAction($user_id, 'Sent Notification', "Recipient ID: $recipient_id");
        } else {
            $_SESSION['toast'] = ['title' => 'Error', 'message' => 'Failed to send notification.', 'type' => 'error'];
        }
    } else {
        $_SESSION['toast'] = ['title' => 'Incomplete', 'message' => 'Please select a recipient and enter a message.', 'type' => 'warning'];
    }
    header("Location: profile.php");
    exit;
}

// Fetch all users for the notification dropdown
$all_users = $userRepo->getAllUsers();

// Fetch current user data
$user = $userRepo->getUserById($user_id);

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
    <link rel="stylesheet" href="../css/pages/profile.css?v=<?php echo time(); ?>">
    <style>
        /* Redesign Styles for Admin/Immediate Head */

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

        /* Certificate Filter Styles */
        .cert-filter-bar {
            display: flex;
            gap: 12px;
        }

        .cert-search-wrapper {
            position: relative;
            width: 240px;
        }

        .cert-search-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .cert-filter-input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #334155;
            background: #f8fafc;
            transition: all 0.2s;
            outline: none;
            height: 38px;
        }

        .cert-filter-input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(15, 76, 117, 0.1);
        }

        .cert-select-wrapper {
            position: relative;
            width: 160px;
        }

        .cert-select-wrapper i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.75rem;
            pointer-events: none;
        }

        .cert-filter-select {
            width: 100%;
            padding: 8px 32px 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #334155;
            background: #f8fafc;
            transition: all 0.2s;
            outline: none;
            height: 38px;
            appearance: none;
            cursor: pointer;
            font-weight: 600;
        }

        .cert-filter-select:focus {
            border-color: var(--primary);
            background: white;
        }

        @media (max-width: 600px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .cert-filter-bar {
                width: 100%;
                flex-direction: column;
            }

            .cert-search-wrapper,
            .cert-select-wrapper {
                width: 100%;
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

                        <!-- System Notification Card (Super Admin) -->
                        <div class="dashboard-card" style="margin-top: 24px; border-left: 4px solid #f59e0b;">
                            <div class="card-header">
                                <h2><i class="bi bi-megaphone text-warning"></i> Send System Notification</h2>
                            </div>
                            <div class="card-body" style="padding: 24px;">
                                <form method="POST" action="">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Recipient <span class="text-danger">*</span></label>
                                        <div style="position: relative;">
                                            <i class="bi bi-people"
                                                style="position: absolute; left: 14px; top: 18px; transform: translateY(-50%); color: var(--text-muted); z-index: 10;"></i>
                                            <select name="recipient_id" id="recipient_select_super" class="form-control"
                                                required style="padding-left: 42px;">
                                                <option value="">Search for a user...</option>
                                                <?php foreach ($all_users as $u): ?>
                                                    <?php if ($u['id'] != $user_id): ?>
                                                        <option value="<?php echo $u['id']; ?>">
                                                            <?php echo htmlspecialchars($u['full_name']); ?>
                                                            (<?php echo htmlspecialchars($u['office_station']); ?>)
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="form-label">Message <span class="text-danger">*</span></label>
                                        <textarea name="notif_message" class="form-control" rows="3"
                                            placeholder="Type your notification message here..." required
                                            style="min-height: 100px; padding: 15px; border-radius: 12px;"></textarea>
                                    </div>
                                    <div style="text-align: right;">
                                        <button type="submit" name="send_notification" class="btn btn-primary"
                                            style="background: #f59e0b; border-color: #f59e0b; border-radius: 10px; font-weight: 700; height: 48px; padding: 0 25px;">
                                            <i class="bi bi-send"></i> Send Notification
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Redesign for Admin/Immediate Head -->
                    <div class="profile-container">


                        <!-- Hero Section -->
                        <div class="profile-hero">
                            <div class="hero-main">
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
                                        <span
                                            style="opacity: 0.5; margin: 0 4px; color: rgba(255,255,255,0.5) !important;">•</span>
                                        <i class="bi bi-building"></i>
                                        <?php echo htmlspecialchars($user['office_station']); ?>
                                    </p>
                                </div>
                            </div>

                            <?php if (empty($user['rating_period'])): ?>
                                <div class="rating-period-alert" id="ratingPeriodAlert">
                                    <div class="alert-content">
                                        <div class="alert-icon-box">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                        </div>
                                        <div class="alert-text">
                                            <strong>Rating Period Missing</strong>
                                            <p>Please set your current Rating Period.</p>
                                        </div>
                                    </div>
                                    <button type="button" class="alert-action-btn"
                                        onclick="document.getElementById('toggleSettings').click(); document.getElementById('accountSettings').scrollIntoView({behavior: 'smooth'});">
                                        FIX NOW
                                    </button>
                                </div>
                            <?php endif; ?>
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
                                                    <label class="form-label">Employee Number</label>
                                                    <input type="text" name="employee_number" class="form-control"
                                                        value="<?php echo htmlspecialchars($user['employee_number'] ?: ''); ?>">
                                                </div>
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

                        <!-- System Notification Card (Admin/Head) -->
                        <div class="dashboard-card" style="margin-top: 24px; border-left: 4px solid #f59e0b;">
                            <div class="card-header">
                                <h2><i class="bi bi-megaphone text-warning"></i> Send System Notification</h2>
                            </div>
                            <div class="card-body" style="padding: 24px;">
                                <form method="POST" action="">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Recipient <span class="text-danger">*</span></label>
                                        <div style="position: relative;">
                                            <i class="bi bi-people"
                                                style="position: absolute; left: 14px; top: 18px; transform: translateY(-50%); color: var(--text-muted); z-index: 10;"></i>
                                            <select name="recipient_id" id="recipient_select_admin" class="form-control"
                                                required style="padding-left: 42px;">
                                                <option value="">Search for a user...</option>
                                                <?php foreach ($all_users as $u): ?>
                                                    <?php if ($u['id'] != $user_id): ?>
                                                        <option value="<?php echo $u['id']; ?>">
                                                            <?php echo htmlspecialchars($u['full_name']); ?>
                                                            (<?php echo htmlspecialchars($u['office_station']); ?>)
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="form-label">Message <span class="text-danger">*</span></label>
                                        <textarea name="notif_message" class="form-control" rows="3"
                                            placeholder="Type your notification message here..." required
                                            style="min-height: 100px; border-radius: 12px; padding: 15px;"></textarea>
                                    </div>
                                    <div style="text-align: right;">
                                        <button type="submit" name="send_notification" class="btn btn-primary"
                                            style="background: #f59e0b; border-color: #f59e0b; border-radius: 10px; font-weight: 700; height: 48px; padding: 0 25px;">
                                            <i class="bi bi-send"></i> Send Notification
                                        </button>
                                    </div>
                                </form>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($is_super_admin): ?>
                new TomSelect('#office_select', {
                    create: false,
                    sortField: { field: "text", direction: "asc" },
                    placeholder: "Type to search office...",
                    maxOptions: 50
                });

                if (document.getElementById('recipient_select_super')) {
                    new TomSelect('#recipient_select_super', {
                        create: false,
                        sortField: { field: "text", direction: "asc" },
                        placeholder: "Search for a user...",
                        maxOptions: 50
                    });
                }
            <?php else: ?>
                if (document.getElementById('recipient_select_admin')) {
                    new TomSelect('#recipient_select_admin', {
                        create: false,
                        sortField: { field: "text", direction: "asc" },
                        placeholder: "Search for a user...",
                        maxOptions: 50
                    });
                }
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
    <script src="../js/profile-actions.js?v=<?php echo time(); ?>"></script>
</body>

</html>