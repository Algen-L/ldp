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

        $logRepo->logAction($_SESSION['user_id'], 'Profile Updated', 'Personnel updated their own profile information and/or profile picture.');
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
    <title>My Profile & Certificates - LDP</title>
    <?php require 'includes/user_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/pages/profile.css?v=<?php echo time(); ?>">
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
                        <div class="hero-main">
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
                                    <?php echo htmlspecialchars($user['position'] ?: 'Educational Professional'); ?>
                                    <span class="text-muted mx-1"
                                        style="color: rgba(255,255,255,0.5) !important;">•</span>
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
                        <div class="dashboard-card profile-settings-card">
                            <div class="card-header profile-settings-header">
                                <h2><i class="bi bi-shield-lock"></i> Account Settings</h2>
                            </div>
                            <div class="card-body profile-settings-body">
                                <?php $can_edit = ($_SESSION['role'] === 'hr' || $_SESSION['role'] === 'super_admin'); ?>
                                <?php if (!$can_edit): ?>
                                    <div class="alert alert-info alert-res-info">
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
                                        <div class="avatar-edit-container">
                                            <div id="avatarPreviewContainer" class="avatar-preview-box">
                                                <?php if (!empty($user['profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                                        id="currentAvatar" class="avatar-img">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder-text">
                                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="flex: 1;">
                                                <div style="margin-bottom: 12px;">
                                                    <button type="button"
                                                        onclick="document.getElementById('profile_pic_input').click()"
                                                        class="btn btn-outline-primary btn-upload-photo">
                                                        <i class="bi bi-camera"></i> Update Photo
                                                    </button>
                                                    <input type="file" name="profile_picture" id="profile_pic_input"
                                                        style="display: none;" accept="image/*"
                                                        onchange="updateFileName(this)">
                                                </div>
                                                <div id="fileNameDisplay" class="avatar-controls-text">
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
                                    <div class="form-actions text-end mt-4">
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
                            <div class="dashboard-card">
                                <div class="card-body" style="padding: 25px;">
                                    <form method="POST" class="form-group ildn-form">
                                        <div class="ildn-main-row">
                                            <input type="text" name="need_text" class="form-control ildn-input"
                                                placeholder="Enter a learning need..." required>
                                            <button type="submit" name="add_ildn" class="btn btn-primary ildn-add-btn">
                                                <i class="bi bi-plus-lg"></i> Add
                                            </button>
                                        </div>
                                        <textarea name="description" class="form-control ildn-textarea"
                                            placeholder="What is this all about? (Optional)"></textarea>
                                    </form>

                                    <?php if (empty($user_ildns)): ?>
                                        <div class="ildn-empty-msg">
                                            <i class="bi bi-info-circle"
                                                style="font-size: 1.2rem; display: block; margin-bottom: 8px;"></i>
                                            You haven't set any individual learning and development needs yet.
                                        </div>
                                    <?php else: ?>
                                        <div class="ildn-list-scroll" style="max-height: 250px;">
                                            <div class="ildn-list-container">
                                                <?php foreach ($user_ildns as $ildn):
                                                    $is_addressed = $ildn['usage_count'] > 0;
                                                    ?>
                                                    <div class="ildn-item-chip <?php echo $is_addressed ? 'addressed' : 'pending'; ?>"
                                                        onclick="showILDNDescription(<?php echo $ildn['id']; ?>, '<?php echo addslashes(htmlspecialchars($ildn['need_text'])); ?>', '<?php echo addslashes(htmlspecialchars($ildn['description'] ?: 'No description provided.')); ?>')">
                                                        <i
                                                            class="bi <?php echo $is_addressed ? 'bi-check-circle-fill' : 'bi-clock-fill'; ?>"></i>
                                                        <span class="ildn-chip-title">
                                                            <?php echo htmlspecialchars($ildn['need_text']); ?>
                                                        </span>
                                                        <?php if ($is_addressed): ?>
                                                            <span class="ildn-chip-count">
                                                                <?php echo $ildn['usage_count']; ?>x
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="ildn-chip-delete"
                                                            onclick="event.stopPropagation(); confirmDeleteILDN(<?php echo $ildn['id']; ?>)">
                                                            &times;
                                                        </span>
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
                                        <span class="stat-value">
                                            <?php echo $total_activities; ?>
                                        </span>
                                        <span class="stat-label">Total Activities</span>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;"><i
                                            class="bi bi-patch-check"></i></div>
                                    <div>
                                        <span class="stat-value">
                                            <?php echo $with_certs; ?>
                                        </span>
                                        <span class="stat-label">With Certificates</span>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div
                                        class="stat-icon <?php echo $unaddressed_ildns_count > 0 ? 'bg-danger-light text-danger' : 'bg-success-light text-success'; ?>">
                                        <i
                                            class="bi <?php echo $unaddressed_ildns_count > 0 ? 'bi-exclamation-octagon-fill' : 'bi-check-all'; ?>"></i>
                                    </div>
                                    <div>
                                        <span class="stat-value">
                                            <?php echo $unaddressed_ildns_count; ?>
                                        </span>
                                        <span class="stat-label">Unaddressed Needs</span>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: #fef2f2; color: #dc2626;"><i
                                            class="bi bi-clock-history"></i></div>
                                    <div>
                                        <span class="stat-value">
                                            <?php echo $total_activities - $with_certs; ?>
                                        </span>
                                        <span class="stat-label">Pending Certs</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Certificate Hub Section -->
                    <div class="section-header"
                        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <h2 class="section-title"><i class="bi bi-trophy"></i> Activity Certificates</h2>

                        <div class="cert-filter-bar">
                            <div class="cert-search-wrapper">
                                <i class="bi bi-search"></i>
                                <input type="text" id="certSearchInput" placeholder="Search certificates..."
                                    class="cert-filter-input">
                            </div>
                            <div class="cert-select-wrapper">
                                <select id="certStatusSelect" class="cert-filter-select">
                                    <option value="all">All Status</option>
                                    <option value="ready">Certificate Ready</option>
                                    <option value="upload">Needs Upload</option>
                                </select>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="submissions-list-scroll">
                        <div class="certificate-grid"
                            style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                            <?php if (empty($activities)): ?>
                                <div class="empty-state">
                                    <div style="font-size: 3rem; color: #e2e8f0; margin-bottom: 20px;"><i
                                            class="bi bi-file-earmark-x"></i></div>
                                    <h3 style="color: #64748b; font-weight: 700;">No activities found</h3>
                                    <p style="color: #94a3b8; max-width: 400px; margin: 0 auto;">Record your attended
                                        activities first to start managing your certificates.</p>
                                    <a href="../pages/add_activity.php" class="btn btn-primary" style="margin-top: 24px;">
                                        <i class="bi bi-plus-lg"></i> Record Now
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activities as $act): ?>
                                    <div class="activity-card">
                                        <div onclick="location.href='../pages/view_activity.php?id=<?php echo $act['id']; ?>'"
                                            style="cursor: pointer;">
                                            <span class="activity-type">
                                                <?php echo htmlspecialchars($act['type_ld'] ?: 'Professional Development'); ?>
                                            </span>
                                            <h3 class="activity-title" title="<?php echo htmlspecialchars($act['title']); ?>">
                                                <?php echo htmlspecialchars($act['title']); ?>
                                            </h3>
                                            <div class="activity-meta">
                                                <span><i class="bi bi-calendar-event"></i>
                                                    <?php echo date('M d, Y', strtotime($act['date_attended'])); ?>
                                                </span>
                                                <span><i class="bi bi-geo-alt"></i>
                                                    <?php echo htmlspecialchars(substr($act['venue'], 0, 20)); ?>...
                                                </span>
                                            </div>
                                        </div>

                                        <?php if ($act['certificate_path']): ?>
                                            <div class="has-cert">
                                                <div class="cert-ready-badge">
                                                    <i class="bi bi-patch-check-fill"
                                                        style="color: #F57C00; font-size: 1.5rem;"></i>
                                                    <div class="cert-ready-text">CERTIFICATE READY</div>
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
            </main>

            <footer class="user-footer">
                <p>&copy;
                    <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span>
                </p>
            </footer>
        </div>
    </div>

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
                    <div class="form-group text-start">
                        <label class="form-label">Learning Need</label>
                        <input type="text" name="need_text" id="edit_need_text" class="form-control" required>
                    </div>
                    <div class="form-group text-start">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description"
                            class="form-control ildn-edit-textarea"></textarea>
                    </div>
                    <div class="modal-actions mt-4">
                        <button type="button" class="modal-btn modal-btn-cancel"
                            onclick="toggleEditMode(false)">Cancel</button>
                        <button type="submit" class="modal-btn btn-success-fixed">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/profile-actions.js?v=<?php echo time(); ?>"></script>
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
        <?php if ($message): ?>
                showToast("<?php echo ($messageType === 'success') ? 'Success' : 'Notice'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
        <?php endif; ?>
    </script>
</body>

</html>