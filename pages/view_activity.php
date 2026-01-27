<?php
session_start();
require '../includes/init_repos.php';
require '../includes/functions/file-functions.php';
require '../includes/functions/activity-functions.php';

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
$activity = $activityRepo->getActivityById($activity_id);

if (!$activity) {
    die("Activity not found.");
}

// Access Control
if (($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head' && $_SESSION['role'] !== 'head_hr') && $activity['user_id'] != $_SESSION['user_id']) {
    $_SESSION['toast'] = [
        'title' => 'Access Restricted',
        'message' => 'You do not have permission to view this specific activity record.',
        'type' => 'warning'
    ];

    // Redirect back to referring page or dashboard fallback
    $fallback = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'head_hr') ? '../admin/dashboard.php' : '../user/home.php';
    $redirect = $_SERVER['HTTP_REFERER'] ?? $fallback;

    header("Location: $redirect");
    exit;
}

// Log View Activity
$logRepo->logAction($_SESSION['user_id'], 'Viewed Specific Activity', $activity['title']);

// Update Status to 'Viewed' if it is 'Pending' and user is Admin
if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'immediate_head') && $activity['status'] === 'Pending') {
    $activityRepo->updateStatus($activity_id, 'Viewed');
    $activity['status'] = 'Viewed';
}

// Handle Approval Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_approval'])) {
    $stage = $_POST['stage'];
    $now = date('Y-m-d H:i:s');
    $success = false;

    if ($stage === 'supervisor') {
        $success = $activityRepo->updateApprovalStatus($activity_id, 'supervisor', $now);
        $actionDesc = "Reviewed Activity Submission";
    } elseif ($stage === 'asds') {
        $success = $activityRepo->updateApprovalStatus($activity_id, 'asds', $now);
        $actionDesc = "Recommended Activity Submission";
    } elseif ($stage === 'sds') {
        $approvedBy = $_POST['approved_by'] ?? '';
        $signaturePath = saveAdminSignature('signature_data', 'admin_sds');
        if ($signaturePath) {
            $success = $activityRepo->updateApprovalStatus($activity_id, 'sds', $now, [
                'approved_by' => $approvedBy,
                'signature_path' => $signaturePath
            ]);
            $actionDesc = "SDS Final Approval Given";
        }
    }

    if ($success) {
        $logRepo->logAction($_SESSION['user_id'], $actionDesc, $activity['title']);
        $_SESSION['toast'] = [
            'title' => 'Success',
            'message' => 'Submission status updated successfully.',
            'type' => 'success'
        ];
    } else {
        $_SESSION['toast'] = [
            'title' => 'Error',
            'message' => 'Failed to update submission status.',
            'type' => 'error'
        ];
    }
    header("Location: view_activity.php?id=" . $activity_id);
    exit;
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
            @page {
                size: A4;
                margin: 0.5cm;
            }

            body {
                background: white !important;
                color: black !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .sidebar,
            .top-bar,
            .admin-controls,
            .user-footer,
            .btn-print-hide,
            .view-prog-track,
            .data-section-title i {
                display: none !important;
            }

            .submitter-hero {
                display: flex !important;
                background: transparent !important;
                border: 1px solid #000 !important;
                box-shadow: none !important;
                padding: 15px !important;
                margin-bottom: 0 !important;
                border-bottom: none !important;
                border-bottom-left-radius: 0 !important;
                border-bottom-right-radius: 0 !important;
                page-break-inside: avoid;
                page-break-after: avoid !important;
            }

            .app-layout {
                display: block !important;
                padding: 0 !important;
                background: white !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                background: white !important;
            }

            .content-wrapper {
                padding: 0 !important;
                margin: 0 !important;
                background: white !important;
            }

            .view-layout-container {
                max-width: 100% !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .dashboard-card {
                border: 1px solid #000 !important;
                border-top: none !important;
                border-top-left-radius: 0 !important;
                border-top-right-radius: 0 !important;
                box-shadow: none !important;
                padding: 20px !important;
                background: white !important;
                margin: 0 !important;
                page-break-before: avoid !important;
            }

            .card-body {
                padding: 0 !important;
            }

            /* Ensure form groups don't break awkwardly */
            .form-group {
                page-break-inside: avoid;
                margin-bottom: 15px !important;
            }

            /* Clean up input look */
            .form-control {
                background: none !important;
                border: 1px solid #ccc !important;
                color: black !important;
                padding: 5px 0 !important;
                border: none !important;
                border-bottom: 1px solid #ddd !important;
                border-radius: 0 !important;
                min-height: auto !important;
            }

            .image-attachment a {
                border: 1px solid #eee !important;
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

        .print-status-header {
            display: none;
        }

        /* --- New Print Header --- */
        .print-only-header {
            display: none;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary);
        }

        .print-logo {
            width: 80px;
            height: auto;
        }

        .print-title-group h1 {
            font-size: 2.2rem;
            color: #1a1a1b;
            /* Darker, more professional color */
            margin: 0;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .print-title-group p {
            font-size: 1rem;
            color: #5b9bd5;
            /* Professional light blue for the subtitle */
            margin: 2px 0 0;
            font-weight: 600;
            font-family: 'Plus Jakarta Sans', sans-serif;
            letter-spacing: 0.2px;
        }

        @media print {
            .print-only-header {
                display: flex !important;
                border-bottom-color: black !important;
            }

            .print-title-group h1 {
                color: black !important;
            }

            .print-status-header {
                display: block;
                font-size: 1.2rem;
                font-weight: 800;
                text-transform: uppercase;
                text-align: right;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid black;
            }

            .print-page-break {
                page-break-before: always !important;
                break-before: page !important;
                margin-top: 1cm !important;
                display: block !important;
            }

            .signatures-grid {
                display: flex !important;
                justify-content: space-between !important;
                gap: 20px !important;
                margin-top: 50px !important;
                page-break-inside: avoid !important;
            }

            .signature-box {
                flex: 1 !important;
                text-align: center !important;
                border: none !important;
            }

            .signature-line {
                border-bottom: 1px solid black !important;
                margin-bottom: 8px !important;
                height: 100px !important;
                display: flex !important;
                align-items: flex-end !important;
                justify-content: center !important;
            }

            .signature-img {
                max-height: 80px !important;
                filter: contrast(1.5) !important;
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
                        <h1 class="page-title">Activity Details</h1>
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
                <!-- Branded Header for Print -->
                <div class="print-only-header">
                    <img src="../assets/LogoLDP.png" alt="LDP Logo" class="print-logo">
                    <div class="print-title-group">
                        <h1>Learning & Development Passbook</h1>
                        <p>Schools Division Office</p>
                    </div>
                </div>

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
                            <?php
                            $printStatus = 'PENDING';
                            if ($activity['approved_sds'])
                                $printStatus = 'APPROVED';
                            elseif ($activity['recommending_asds'])
                                $printStatus = 'RECOMMENDED';
                            elseif ($activity['reviewed_by_supervisor'])
                                $printStatus = 'REVIEWED';
                            ?>
                            <div class="print-status-header">
                                STATUS: <?php echo $printStatus; ?>
                            </div>

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



                            <?php if (!empty($activity['workplace_image_path'])): ?>
                                <div class="form-group" style="margin-bottom: 40px;">
                                    <label class="form-label">Evidence / Attachments</label>
                                    <div style="display: flex; flex-wrap: wrap; gap: 16px;">

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

                            <div class="print-page-break">
                                <div class="data-section-title"><i class="bi bi-journal-text"></i> Personal Reflection
                                </div>

                                <div class="form-group" style="margin-bottom: 32px;">
                                    <label class="form-label">Personal Reflection</label>
                                    <div
                                        style="line-height: 1.7; color: var(--text-secondary); background: var(--bg-secondary); padding: 24px; border-radius: var(--radius-lg);">
                                        <?php echo htmlspecialchars($activity['reflection']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="data-section-title"><i class="bi bi-award"></i> Certificate of Participation
                            </div>

                            <div class="form-group" style="margin-bottom: 32px;">
                                <?php if ($activity['certificate_path']): ?>
                                    <div class="image-attachment" style="display: inline-block;">
                                        <a href="../<?php echo htmlspecialchars($activity['certificate_path']); ?>"
                                            target="_blank"
                                            style="width: 160px; height: 160px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: var(--radius-md); text-decoration: none; color: #16a34a; transition: transform 0.2s;">
                                            <i class="bi bi-patch-check-fill" style="font-size: 3rem;"></i>
                                            <span style="font-size: 0.8rem; font-weight: 700; margin-top: 12px;">View
                                                Certificate</span>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div
                                        style="padding: 24px; background: var(--bg-secondary); border-radius: var(--radius-md); color: var(--text-muted); font-style: italic;">
                                        No certificate attached for this activity.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="data-section-title"><i class="bi bi-pen"></i> Signatures & Authorization</div>

                            <div class="signatures-grid"
                                style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 32px;">
                                <div class="signature-box" style="text-align: center;">
                                    <div class="signature-line"
                                        style="height: 120px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--text-primary); margin-bottom: 12px;">
                                        <?php if (!empty($activity['organizer_signature_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($activity['organizer_signature_path']); ?>"
                                                class="signature-img" style="max-height: 100px; filter: contrast(1.2);">
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
                                <div class="signature-box" style="text-align: center;">
                                    <div class="signature-line"
                                        style="height: 120px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--text-primary); margin-bottom: 12px;">
                                        <?php if (!empty($activity['signature_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($activity['signature_path']); ?>"
                                                class="signature-img" style="max-height: 100px; filter: contrast(1.2);">
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

                    <!-- Print Button at Bottom -->
                    <div style="text-align: center; margin-top: 40px; margin-bottom: 60px;" class="btn-print-hide">
                        <button onclick="window.print()" class="btn btn-primary btn-lg"
                            style="padding: 12px 32px; font-weight: 800; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 6px rgba(15, 76, 117, 0.2);">
                            <i class="bi bi-printer-fill"></i> PRINT ACTIVITY RECORD
                        </button>
                    </div>
                </div>
            </main>

            <footer class="user-footer btn-print-hide">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
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