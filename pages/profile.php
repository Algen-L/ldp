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
                $stmt = $pdo->prepare("UPDATE ld_activities SET certificate_path = ? WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$dbPath, $activity_id, $_SESSION['user_id']])) {
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
$stmt = $pdo->prepare("SELECT * FROM ld_activities WHERE user_id = ? ORDER BY date_attended DESC");
$stmt->execute([$_SESSION['user_id']]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Certificates - LDP</title>
    <?php require '../includes/head.php'; ?>
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
            padding: 30px;
            border-radius: 20px;
            display: flex;
            gap: 24px;
            align-items: center;
            margin-bottom: 24px;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(50, 130, 184, 0.3);
            position: relative;
            overflow: hidden;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .hero-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
        }

        .hero-info h2 {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 4px 0;
            letter-spacing: -0.02em;
        }

        .hero-info p {
            opacity: 0.9;
            font-weight: 500;
            margin: 0;
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid var(--card-border);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
            display: block;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }

        .activity-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--card-border);
            padding: 16px;
            transition: all 0.2s ease;
            position: relative;
        }

        .activity-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }

        .activity-type {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--primary);
            background: var(--primary-light);
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .activity-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
            margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.8em;
        }

        .activity-meta {
            display: flex;
            gap: 12px;
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 16px;
        }

        .activity-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .cert-upload-zone {
            border: 1.5px dashed #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .cert-upload-zone:hover {
            background: #f8fafc;
            border-color: var(--primary);
        }

        .cert-upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .has-cert {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
    <div class="user-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">My Profile</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <button id="toggleSettings" class="toggle-settings-btn">
                        <i class="bi bi-person-gear"></i> Account Information
                    </button>
                    <a href="home.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-house"></i> Home
                    </a>
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
                            <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <p><?php echo htmlspecialchars($user['position'] ?: 'Educational Professional'); ?></p>
                            <div
                                style="margin-top: 10px; font-size: 0.85rem; background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 99px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($user['office_station']); ?>
                            </div>
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


                                <div class="form-grid">
                                    <div>
                                        <div class="form-group">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" readonly
                                                value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Position / Designation</label>
                                            <input type="text" class="form-control" readonly
                                                value="<?php echo htmlspecialchars($user['position'] ?: 'Not Specified'); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Office / Station</label>
                                            <input type="text" class="form-control" readonly
                                                value="<?php echo htmlspecialchars($user['office_station']); ?>">
                                        </div>
                                    </div>
                                    <div>
                                        <div class="form-group">
                                            <label class="form-label">Rating Period</label>
                                            <input type="text" class="form-control" readonly
                                                value="<?php echo htmlspecialchars($user['rating_period'] ?: 'Not Specified'); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Area of Specialization</label>
                                            <input type="text" class="form-control" readonly
                                                value="<?php echo htmlspecialchars($user['area_of_specialization'] ?: 'Not Specified'); ?>">
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                            <div class="form-group">
                                                <label class="form-label">Age</label>
                                                <input type="number" class="form-control" readonly
                                                    value="<?php echo htmlspecialchars($user['age']); ?>">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Sex</label>
                                                <input type="text" class="form-control" readonly
                                                    value="<?php echo htmlspecialchars($user['sex'] ?: 'Not Specified'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <?php
                    $total_activities = count($activities);
                    $with_certs = 0;
                    foreach ($activities as $act)
                        if ($act['certificate_path'])
                            $with_certs++;
                    ?>
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
                                <span class="stat-label">Certificates Uploaded</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;"><i
                                    class="bi bi-clock-history"></i></div>
                            <div>
                                <span class="stat-value"><?php echo $total_activities - $with_certs; ?></span>
                                <span class="stat-label">Pending Certificates</span>
                            </div>
                        </div>
                    </div>

                    <!-- Certificate Hub Section -->
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-trophy"></i> Activity Certificates</h2>
                    </div>

                    <div class="certificate-grid">
                        <?php if (empty($activities)): ?>
                            <div class="empty-state">
                                <div style="font-size: 3rem; color: #e2e8f0; margin-bottom: 20px;"><i
                                        class="bi bi-file-earmark-x"></i></div>
                                <h3 style="color: #64748b; font-weight: 700;">No activities found</h3>
                                <p style="color: #94a3b8; max-width: 400px; margin: 0 auto;">Record your attended activities
                                    first to start managing your certificates.</p>
                                <a href="add_activity.php" class="btn btn-primary" style="margin-top: 24px;">
                                    <i class="bi bi-plus-lg"></i> Record Now
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $act): ?>
                                <div class="activity-card">
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

                                    <?php if ($act['certificate_path']): ?>
                                        <div class="has-cert">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <i class="bi bi-file-earmark-pdf-fill"
                                                    style="font-size: 1.5rem; color: #ef4444;"></i>
                                                <div style="font-size: 0.8rem; font-weight: 600; color: #16a34a;">Certificate Ready
                                                </div>
                                            </div>
                                            <div style="display: flex; gap: 8px;">
                                                <a href="../<?php echo $act['certificate_path']; ?>" target="_blank"
                                                    class="btn btn-secondary btn-sm"
                                                    style="padding: 4px 8px; font-size: 0.75rem;">View</a>
                                                <button
                                                    onclick="document.getElementById('file-input-<?php echo $act['id']; ?>').click()"
                                                    class="btn btn-secondary btn-sm"
                                                    style="padding: 4px 8px; font-size: 0.75rem;">Change</button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="cert-upload-zone"
                                            onclick="document.getElementById('file-input-<?php echo $act['id']; ?>').click()">
                                            <i class="bi bi-cloud-upload"
                                                style="font-size: 1.25rem; color: #94a3b8; display: block; margin-bottom: 4px;"></i>
                                            <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Upload
                                                Certificate</span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Hidden Upload Form -->
                                    <form method="POST" enctype="multipart/form-data" id="upload-form-<?php echo $act['id']; ?>"
                                        style="display: none;">
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
            </main>

            <footer class="user-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. All rights reserved.</p>
            </footer>
        </div>
    </div>

    <script>
        document.getElementById('toggleSettings').addEventListener('click', function () {
            const settings = document.getElementById('accountSettings');
            const btn = this;

            if (settings.style.display === 'block') {
                settings.style.display = 'none';
                btn.classList.remove('active');
            } else {
                settings.style.display = 'block';
                btn.classList.add('active');
                settings.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        <?php if ($message): ?>
            showToast("<?php echo ($messageType === 'success') ? 'Success' : 'Notice'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
        <?php endif; ?>
    </script>
</body>

</html>