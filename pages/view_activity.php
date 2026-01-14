<?php
session_start();
require '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Get Activity ID
$activity_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$activity_id) {
    die("Invalid Activity ID");
}

// Fetch Activity Logic
$sql = "SELECT ld.*, u.full_name, u.office_station, u.position as user_position, u.profile_picture, ld.certificate_path FROM ld_activities ld JOIN users u ON ld.user_id = u.id WHERE ld.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$activity_id]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    die("Activity not found.");
}

// Access Control
if (($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head') && $activity['user_id'] != $_SESSION['user_id']) {
    die("Unauthorized access.");
}

// Log View Activity
$stmt_log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'Viewed Specific Activity', ?, ?)");
$stmt_log->execute([$_SESSION['user_id'], $activity['title'], $_SERVER['REMOTE_ADDR']]);

// Update Status to 'Viewed' if it is 'Pending' and user is Admin
if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'immediate_head') && $activity['status'] === 'Pending') {
    $stmt_update = $pdo->prepare("UPDATE ld_activities SET status = 'Viewed' WHERE id = ?");
    $stmt_update->execute([$activity_id]);
    $activity['status'] = 'Viewed';
}

// Function to handle signature saving (copied for admin use)
function saveAdminSignature($postDataKey, $prefix)
{
    if (!empty($_POST[$postDataKey])) {
        $data = $_POST[$postDataKey];
        $data = str_replace('data:image/png;base64,', '', $data);
        $data = str_replace(' ', '+', $data);
        $decodedData = base64_decode($data);
        $fileName = uniqid() . '_' . $prefix . '_signature.png';
        $filePath = '../uploads/signatures/' . $fileName;
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        if (file_put_contents($filePath, $decodedData)) {
            return 'uploads/signatures/' . $fileName;
        }
    }
    return '';
}

// Handle Admin Approval Actions
if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'immediate_head') && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_approval'])) {
    $stage = $_POST['stage'];
    $current_time = date('Y-m-d H:i:s');

    if ($stage === 'supervisor') {
        $stmt = $pdo->prepare("UPDATE ld_activities SET reviewed_by_supervisor = 1, reviewed_at = ? WHERE id = ?");
        $stmt->execute([$current_time, $activity_id]);
    } elseif ($stage === 'asds') {
        $stmt = $pdo->prepare("UPDATE ld_activities SET recommending_asds = 1, recommended_at = ? WHERE id = ?");
        $stmt->execute([$current_time, $activity_id]);
    } elseif ($stage === 'sds') {
        if ($_SESSION['role'] !== 'immediate_head') {
            die("Unauthorized final approval.");
        }
        $head_name = trim($_POST['approved_by'] ?? '');
        $sig_data = $_POST['signature_data'] ?? '';

        if (empty($head_name)) {
            $_SESSION['toast'] = ['title' => 'Missing Information', 'message' => 'Immediate Head Name is required.', 'type' => 'error'];
            header("Location: view_activity.php?id=" . $activity_id);
            exit;
        }

        // Simple check if signature data is too short
        if (empty($sig_data) || strlen($sig_data) < 100) {
            $_SESSION['toast'] = ['title' => 'Missing Signature', 'message' => 'Please provide a valid signature.', 'type' => 'error'];
            header("Location: view_activity.php?id=" . $activity_id);
            exit;
        }

        $sig_path = saveAdminSignature('signature_data', 'head');
        if (empty($sig_path)) {
            $_SESSION['toast'] = ['title' => 'Upload Failed', 'message' => 'Could not save the signature.', 'type' => 'error'];
            header("Location: view_activity.php?id=" . $activity_id);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE ld_activities SET approved_sds = 1, approved_at = ?, status = 'Approved', approved_by = ?, signature_path = ? WHERE id = ?");
        $stmt->execute([$current_time, $head_name, $sig_path, $activity_id]);

        $_SESSION['toast'] = ['title' => 'Success', 'message' => 'Activity successfully approved!', 'type' => 'success'];
    }

    // Refresh activity data
    $stmt = $pdo->prepare("SELECT ld.*, u.full_name, u.office_station, u.position as user_position, u.profile_picture, ld.certificate_path FROM ld_activities ld JOIN users u ON ld.user_id = u.id WHERE ld.id = ?");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper for checkboxes
function isChecked($value, $arrayString)
{
    if (!$arrayString)
        return '';
    $array = explode(', ', $arrayString);
    return in_array($value, $array) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Activity Details - LDP</title>
    <?php require '../includes/head.php'; ?>
    <style>
        .view-layout-container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Submission Header / Submitter Card */
        .submitter-hero {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            padding: 32px;
            margin-bottom: 32px;
            border: 1.5px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .submitter-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow-sm);
        }

        .submitter-info h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .submitter-info p {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Progress Tracker Styles */
        .view-prog-track {
            margin-bottom: 40px;
            padding: 24px;
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }

        .view-prog-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .view-prog-line {
            position: absolute;
            top: 18px;
            left: 40px;
            right: 40px;
            height: 3px;
            background: var(--bg-tertiary);
            z-index: 1;
        }

        .view-prog-fill {
            position: absolute;
            top: 18px;
            left: 40px;
            height: 3px;
            background: var(--success);
            z-index: 2;
            transition: width 0.5s ease;
        }

        .view-prog-step {
            position: relative;
            z-index: 3;
            text-align: center;
            flex: 1;
        }

        .view-prog-icon {
            width: 36px;
            height: 36px;
            background: white;
            border: 3px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .view-prog-step.active .view-prog-icon {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }

        .view-prog-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .view-prog-date {
            font-size: 0.65rem;
            color: var(--text-muted);
            display: block;
        }

        /* Admin Controls */
        .admin-controls {
            background: var(--primary-bg);
            border: 1px solid var(--primary-light);
            padding: 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 32px;
        }

        .data-section-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-section-title::after {
            content: "";
            flex: 1;
            height: 1.5px;
            background: var(--border-light);
        }

        .image-attachment {
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border-color);
            padding: 8px;
            transition: transform 0.2s;
        }

        .image-attachment:hover {
            transform: scale(1.02);
        }

        @media print {

            .sidebar,
            .top-bar,
            .admin-controls,
            .user-footer,
            .btn-print-hide {
                display: none !important;
            }

            .user-layout {
                display: block !important;
                padding: 0 !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .content-wrapper {
                padding: 0 !important;
            }

            .dashboard-card {
                border: none !important;
                box-shadow: none !important;
            }
        }

        .admin-controls .form-group {
            margin-bottom: 16px;
        }

        .admin-controls label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: block;
        }

        .signature-pad-container {
            background: #f8fafc;
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 10px;
            text-align: center;
            margin-bottom: 12px;
        }

        #sig-canvas {
            background: white;
            border: 1px solid var(--border-light);
            cursor: crosshair;
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
                        <h1 class="page-title">Activity Details</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print Record
                    </button>
                </div>
            </header>

            <main class="content-wrapper">
                <div class="view-layout-container">

                    <!-- Progress Timeline -->
                    <div class="view-prog-track">
                        <div class="view-prog-steps">
                            <div class="view-prog-line"></div>
                            <?php
                            $stages = [
                                ['label' => 'Submitted', 'field' => 'created_at', 'active' => true],
                                ['label' => 'Reviewed', 'field' => 'reviewed_at', 'active' => (bool) $activity['reviewed_by_supervisor']],
                                ['label' => 'Recommended', 'field' => 'recommended_at', 'active' => (bool) $activity['recommending_asds']],
                                ['label' => 'Approved', 'field' => 'approved_at', 'active' => (bool) $activity['approved_sds']]
                            ];
                            $active_count = 0;
                            foreach ($stages as $s)
                                if ($s['active'])
                                    $active_count++;
                            $fill_pct = ($active_count - 1) / (count($stages) - 1) * 100;
                            ?>
                            <div class="view-prog-fill" style="width: <?php echo $fill_pct; ?>%;"></div>

                            <?php foreach ($stages as $stage): ?>
                                <div class="view-prog-step <?php echo $stage['active'] ? 'active' : ''; ?>">
                                    <div class="view-prog-icon">
                                        <i class="bi <?php echo $stage['active'] ? 'bi-check2' : 'bi-circle'; ?>"></i>
                                    </div>
                                    <span class="view-prog-label"><?php echo $stage['label']; ?></span>
                                    <span
                                        class="view-prog-date"><?php echo $activity[$stage['field']] ? date('M d, Y', strtotime($activity[$stage['field']])) : 'Pending'; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Admin Controls Panel -->
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'immediate_head'): ?>
                        <div class="admin-controls">
                            <h3
                                style="font-size: 0.85rem; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 16px;">
                                <i class="bi bi-shield-lock"></i> Administration Controls
                            </h3>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action_approval" value="1">
                                    <input type="hidden" name="stage" value="supervisor">
                                    <button type="submit"
                                        class="btn btn-sm <?php echo $activity['reviewed_by_supervisor'] ? 'btn-success' : 'btn-secondary'; ?>"
                                        style="width: 100%;" <?php echo $activity['reviewed_by_supervisor'] ? 'disabled' : ''; ?>>
                                        <?php echo $activity['reviewed_by_supervisor'] ? '<i class="bi bi-check-all"></i> Reviewed' : 'Review Activity'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action_approval" value="1">
                                    <input type="hidden" name="stage" value="asds">
                                    <button type="submit"
                                        class="btn btn-sm <?php echo $activity['recommending_asds'] ? 'btn-success' : 'btn-secondary'; ?>"
                                        style="width: 100%;" <?php echo ($activity['recommending_asds'] || !$activity['reviewed_by_supervisor']) ? 'disabled' : ''; ?>>
                                        <?php echo $activity['recommending_asds'] ? '<i class="bi bi-check-all"></i> Recommended' : 'Recommend Activity'; ?>
                                    </button>
                                </form>
                                <?php if ($_SESSION['role'] === 'immediate_head'): ?>
                                    <form method="POST" id="final-approval-form"
                                        style="flex: 1; display: flex; flex-direction: column; gap: 12px;">
                                        <input type="hidden" name="action_approval" value="1">
                                        <input type="hidden" name="stage" value="sds">
                                        <input type="hidden" name="signature_data" id="signature_data">

                                        <?php if (!$activity['approved_sds'] && $activity['recommending_asds']): ?>
                                            <div class="form-group"
                                                style="background: white; padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--primary-light);">
                                                <label>Immediate Head Name</label>
                                                <input type="text" name="approved_by" class="form-control form-control-sm" required
                                                    placeholder="Enter name for signature">

                                                <label style="margin-top: 12px;">Immediate Head Signature</label>
                                                <div class="signature-pad-container">
                                                    <canvas id="sig-canvas" width="300" height="100"></canvas>
                                                    <div style="margin-top: 8px;">
                                                        <button type="button" class="btn btn-xs btn-secondary"
                                                            onclick="clearCanvas()">Clear</button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <button type="button" onclick="submitFinalApproval()"
                                            class="btn btn-sm <?php echo $activity['approved_sds'] ? 'btn-success' : 'btn-secondary'; ?>"
                                            style="width: 100%;" <?php echo ($activity['approved_sds'] || !$activity['recommending_asds']) ? 'disabled' : ''; ?>>
                                            <?php echo $activity['approved_sds'] ? '<i class="bi bi-trophy"></i> Approved' : 'Final Approval'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Submitter Details -->
                    <div class="submitter-hero">
                        <?php if (!empty($activity['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($activity['profile_picture']); ?>"
                                class="submitter-avatar">
                        <?php else: ?>
                            <div class="submitter-avatar"
                                style="background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; color: var(--text-muted);">
                                <?php echo strtoupper(substr($activity['full_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="submitter-info">
                            <p>Activity Submitted By</p>
                            <h2><?php echo htmlspecialchars($activity['full_name']); ?></h2>
                            <div
                                style="display: flex; gap: 16px; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">
                                <span><i class="bi bi-building"></i>
                                    <?php echo htmlspecialchars($activity['office_station']); ?></span>
                                <span><i class="bi bi-briefcase"></i>
                                    <?php echo htmlspecialchars($activity['user_position'] ?: 'Employee'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Main Activity Details Card -->
                    <div class="dashboard-card" style="margin-bottom: 40px;">
                        <div class="card-body" style="padding: 40px;">

                            <div class="data-section-title"><i class="bi bi-book"></i> Activity Details</div>

                            <h2
                                style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin-bottom: 24px;">
                                <?php echo htmlspecialchars($activity['title']); ?>
                            </h2>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 40px;">
                                <div class="form-group">
                                    <label class="form-label">Date(s) of Attendance</label>
                                    <div class="form-control"
                                        style="background: var(--bg-secondary); font-weight: 600; height: auto; min-height: 48px;">
                                        <?php
                                        $dates = explode(', ', $activity['date_attended']);
                                        $formattedDates = array_map(function ($d) {
                                            return date('M d, Y', strtotime($d));
                                        }, $dates);
                                        echo implode(' | ', $formattedDates);
                                        ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Venue</label>
                                    <div class="form-control"
                                        style="background: var(--bg-secondary); font-weight: 600;">
                                        <?php echo htmlspecialchars($activity['venue'] ?: 'Not Specified'); ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Competencies Addressed</label>
                                    <div class="form-control"
                                        style="background: var(--bg-secondary); font-weight: 600;">
                                        <?php echo htmlspecialchars($activity['competency']); ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Conducted By</label>
                                    <div class="form-control"
                                        style="background: var(--bg-secondary); font-weight: 600;">
                                        <?php echo htmlspecialchars($activity['conducted_by']); ?>
                                    </div>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 40px;">
                                <div>
                                    <label class="form-label">Modalities</label>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php
                                        $mods = explode(', ', $activity['modality']);
                                        foreach ($mods as $m):
                                            if (!$m)
                                                continue; ?>
                                            <span class="activity-status-badge status-recommending"><?php echo $m; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Type of L&D</label>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php
                                        $types = explode(', ', $activity['type_ld']);
                                        foreach ($types as $t):
                                            if (!$t)
                                                continue; ?>
                                            <span class="activity-status-badge status-reviewed"><?php echo $t; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="data-section-title"><i class="bi bi-rocket-takeoff"></i> Workplace Application
                                Plan</div>



                            <?php if (!empty($activity['workplace_image_path']) || !empty($activity['certificate_path'])): ?>
                                <div class="form-group" style="margin-bottom: 40px;">
                                    <label class="form-label">Evidence / Attachments</label>
                                    <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                        <?php if ($activity['certificate_path']): ?>
                                            <div class="image-attachment">
                                                <a href="../<?php echo htmlspecialchars($activity['certificate_path']); ?>"
                                                    target="_blank"
                                                    style="width: 140px; height: 140px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: var(--radius-sm); text-decoration: none; color: #16a34a;">
                                                    <i class="bi bi-patch-check-fill" style="font-size: 2.5rem;"></i>
                                                    <span style="font-size: 0.75rem; font-weight: 700; margin-top: 8px;">View
                                                        Certificate</span>
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <?php
                                        $paths = [];
                                        $trimmed = trim($activity['workplace_image_path'] ?? '');
                                        if (strpos($trimmed, '[') === 0)
                                            $paths = json_decode($trimmed, true) ?: [];
                                        elseif (!empty($trimmed))
                                            $paths = [$trimmed];

                                        foreach ($paths as $path):
                                            if (empty($path))
                                                continue;
                                            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                            $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']); ?>
                                            <div class="image-attachment">
                                                <?php if ($isImg): ?>
                                                    <a href="../<?php echo htmlspecialchars($path); ?>" target="_blank">
                                                        <img src="../<?php echo htmlspecialchars($path); ?>"
                                                            style="width: 140px; height: 140px; object-fit: cover; border-radius: var(--radius-sm);">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../<?php echo htmlspecialchars($path); ?>" target="_blank"
                                                        style="width: 140px; height: 140px; display: flex; align-items: center; justify-content: center; background: var(--bg-tertiary); border-radius: var(--radius-sm); text-decoration: none; color: var(--primary);">
                                                        <i class="bi bi-file-earmark-pdf" style="font-size: 3rem;"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="data-section-title"><i class="bi bi-journal-text"></i> Personal Reflection</div>

                            <div class="form-group" style="margin-bottom: 32px;">
                                <label class="form-label">Personal Reflection</label>
                                <div
                                    style="line-height: 1.7; color: var(--text-secondary); background: var(--bg-secondary); padding: 24px; border-radius: var(--radius-lg);">
                                    <?php echo htmlspecialchars($activity['reflection']); ?>
                                </div>
                            </div>

                            <div class="data-section-title"><i class="bi bi-pen"></i> Signatures & Authorization</div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 32px;">
                                <div style="text-align: center;">
                                    <div
                                        style="height: 120px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--text-primary); margin-bottom: 12px;">
                                        <?php if (!empty($activity['organizer_signature_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($activity['organizer_signature_path']); ?>"
                                                style="max-height: 100px; filter: contrast(1.2);">
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">No signature
                                                provided</span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="font-weight: 800; text-transform: uppercase; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($activity['conducted_by']); ?>
                                    </p>
                                    <p style="font-size: 0.75rem; color: var(--text-muted);">ORGANIZER / CONDUCTOR</p>
                                </div>
                                <div style="text-align: center;">
                                    <div
                                        style="height: 120px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--text-primary); margin-bottom: 12px;">
                                        <?php if (!empty($activity['signature_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($activity['signature_path']); ?>"
                                                style="max-height: 100px; filter: contrast(1.2);">
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">No signature
                                                provided</span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="font-weight: 800; text-transform: uppercase; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($activity['approved_by']); ?>
                                    </p>
                                    <p style="font-size: 0.75rem; color: var(--text-muted);">IMMEDIATE HEAD</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </main>

            <footer class="user-footer btn-print-hide">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. All rights reserved.</p>
            </footer>
        </div>
    </div>

    <script>
        // Signature Pad Logic for Admin
        const canvas = document.getElementById('sig-canvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            let drawing = false;

            function getMousePos(canvasDom, touchOrMouseEvent) {
                var rect = canvasDom.getBoundingClientRect();
                return {
                    x: (touchOrMouseEvent.clientX || touchOrMouseEvent.touches[0].clientX) - rect.left,
                    y: (touchOrMouseEvent.clientY || touchOrMouseEvent.touches[0].clientY) - rect.top
                };
            }

            canvas.addEventListener("mousedown", function (e) {
                drawing = true;
                ctx.beginPath();
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#000';
                ctx.moveTo(getMousePos(canvas, e).x, getMousePos(canvas, e).y);
            }, false);

            canvas.addEventListener("mouseup", function (e) {
                drawing = false;
            }, false);

            canvas.addEventListener("mousemove", function (e) {
                if (!drawing) return;
                ctx.lineTo(getMousePos(canvas, e).x, getMousePos(canvas, e).y);
                ctx.stroke();
            }, false);

            // Touch support
            canvas.addEventListener("touchstart", function (e) {
                e.preventDefault();
                drawing = true;
                ctx.beginPath();
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#000';
                ctx.moveTo(getMousePos(canvas, e).x, getMousePos(canvas, e).y);
            }, false);
            canvas.addEventListener("touchend", function (e) {
                drawing = false;
            }, false);
            canvas.addEventListener("touchmove", function (e) {
                if (!drawing) return;
                ctx.lineTo(getMousePos(canvas, e).x, getMousePos(canvas, e).y);
                ctx.stroke();
            }, false);

            window.clearCanvas = function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            };

            // Check if canvas is empty using pixel data
            function isCanvasBlank(canvas) {
                const context = canvas.getContext('2d');
                const pixelBuffer = new Uint32Array(
                    context.getImageData(0, 0, canvas.width, canvas.height).data.buffer
                );
                return !pixelBuffer.some(color => color !== 0);
            }

            window.submitFinalApproval = function () {
                const nameInput = document.querySelector('input[name="approved_by"]');
                const nameVal = nameInput ? nameInput.value.trim() : '';

                if (nameVal === '') {
                    showToast('Missing Field', 'Please enter the Immediate Head Name.', 'error');
                    nameInput.focus();
                    return;
                }

                if (isCanvasBlank(canvas)) {
                    showToast('Missing Signature', 'Please provide a signature.', 'error');
                    return;
                }

                const signatureData = canvas.toDataURL();
                document.getElementById('signature_data').value = signatureData;

                // Confirm action
                if (confirm('Are you sure you want to approve this submission?')) {
                    document.getElementById('final-approval-form').submit();
                }
            };
        }
    </script>
</body>

</html>