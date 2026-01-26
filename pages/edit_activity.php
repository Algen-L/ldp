<?php
session_start();
require '../includes/init_repos.php';
require '../includes/functions/file-functions.php';
require '../includes/functions/activity-functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$activity_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$activity_id) {
    die("Invalid Activity ID");
}

// Fetch Activity Data
$activity = $activityRepo->getActivityById($activity_id);

if (!$activity) {
    die("Activity not found.");
}

// Access Control: Only owner or admin
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $activity['user_id'] != $_SESSION['user_id']) {
    die("Unauthorized access.");
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect data
    $title = trim($_POST['title']);
    $date_attended = isset($_POST['date_attended']) ? trim($_POST['date_attended']) : '';
    $venue = trim($_POST['venue']);
    $modality = isset($_POST['modality']) ? implode(', ', $_POST['modality']) : '';
    // Handle multiple competencies
    $competency = isset($_POST['competency']) ? (is_array($_POST['competency']) ? implode(', ', $_POST['competency']) : trim($_POST['competency'])) : '';
    $type_ld = isset($_POST['type_ld']) ? implode(', ', $_POST['type_ld']) : '';
    $type_ld_others = isset($_POST['type_ld_others']) ? trim($_POST['type_ld_others']) : '';
    $conducted_by = trim($_POST['conducted_by']);
    $reflection = trim($_POST['reflection']);

    $new_work_images = saveUpload('workplace_image', 'work', 'workplace');
    $work_image_path = $new_work_images ?: $activity['workplace_image_path'];

    $updateData = [
        'title' => $title,
        'date_attended' => $date_attended,
        'venue' => $venue,
        'modality' => $modality,
        'competency' => $competency,
        'type_ld' => $type_ld,
        'type_ld_others' => $type_ld_others,
        'conducted_by' => $conducted_by,
        'workplace_image_path' => $work_image_path,
        'reflection' => $reflection,
        'rating_period' => $activity['rating_period']
    ];

    if ($activityRepo->updateActivity($activity_id, $_SESSION['user_id'], $updateData)) {
        $logRepo->logAction($_SESSION['user_id'], 'Updated Activity', "Activity ID: $activity_id, Title: $title");

        $message = "Activity updated successfully!";
        $messageType = "success";

        // Refresh data
        $activity = $activityRepo->getActivityById($activity_id);
    } else {
        $message = "Error updating activity.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Activity Record - LDP</title>
    <?php require '../includes/head.php'; ?>
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        .form-section {
            margin-bottom: 40px;
        }

        .form-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-light);
        }

        .form-section-header i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .form-section-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            text-transform: uppercase;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
            border: 1.5px solid transparent;
        }

        .checkbox-item:hover {
            background: var(--bg-tertiary);
        }

        .checkbox-item input:checked+span {
            color: var(--primary);
            font-weight: 700;
        }

        .prog-preview {
            margin-bottom: 32px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .view-prog-track {
            margin-bottom: 24px;
            padding: 12px;
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
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .view-prog-step.active .view-prog-icon {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }

        .view-prog-label {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        /* Flatpickr Custom Styling */
        .flatpickr-calendar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-lg);
            border-radius: var(--radius-lg);
        }

        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange,
        .flatpickr-day.selected.inRange,
        .flatpickr-day.startRange.inRange,
        .flatpickr-day.endRange.inRange,
        .flatpickr-day.selected:focus,
        .flatpickr-day.startRange:focus,
        .flatpickr-day.endRange:focus,
        .flatpickr-day.selected:hover,
        .flatpickr-day.startRange:hover,
        .flatpickr-day.endRange:hover,
        .flatpickr-day.selected.prevMonthDay,
        .flatpickr-day.startRange.prevMonthDay,
        .flatpickr-day.endRange.prevMonthDay,
        .flatpickr-day.selected.nextMonthDay,
        .flatpickr-day.startRange.nextMonthDay,
        .flatpickr-day.endRange.nextMonthDay {
            background: var(--primary);
            border-color: var(--primary);
        }

        /* Tom Select Custom Styling */
        .ts-control {
            border-radius: var(--radius-md);
            padding: 10px 14px;
            border-color: var(--border-color);
            transition: all var(--transition-fast);
        }

        .ts-wrapper.focus .ts-control {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 76, 117, 0.1);
        }

        .ts-dropdown .active {
            background-color: var(--primary);
            color: white;
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
                        <h1 class="page-title">Update Activity</h1>
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
                <?php if ($message): ?>
                    <script>
                        window.addEventListener('DOMContentLoaded', function () {
                            showToast("<?php echo ($messageType === 'success') ? 'Success' : 'Error'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
                        });
                    </script>
                <?php endif; ?>
                <!-- Flatpickr JS -->
                <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        flatpickr("#date_picker", {
                            mode: "multiple",
                            dateFormat: "Y-m-d",
                            defaultDate: <?php echo json_encode(explode(', ', $activity['date_attended'])); ?>,
                            conjunction: ", ",
                            altInput: true,
                            altFormat: "M j, Y",
                            allowInput: false
                        });
                    });
                </script>

                <div class="dashboard-card" style="max-width: 900px; margin: 0 auto; overflow: visible;">
                    <div class="card-body" style="padding: 40px;">

                        <!-- Progress Visualization Tooltip -->
                        <div class="prog-preview">
                            <div class="view-prog-track">
                                <div class="view-prog-steps">
                                    <div class="view-prog-line"></div>
                                    <?php
                                    $stages = [
                                        ['label' => 'Submitted', 'active' => true],
                                        ['label' => 'Reviewed', 'active' => (bool) $activity['reviewed_by_supervisor']],
                                        ['label' => 'Recommended', 'active' => (bool) $activity['recommending_asds']],
                                        ['label' => 'Approved', 'active' => (bool) $activity['approved_sds']]
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
                                            <div class="view-prog-icon"><i
                                                    class="bi <?php echo $stage['active'] ? 'bi-check2' : 'bi-circle'; ?>"></i>
                                            </div>
                                            <span class="view-prog-label"><?php echo $stage['label']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php if ($activity['reviewed_by_supervisor']): ?>
                                <div
                                    style="display: flex; align-items: center; gap: 8px; color: var(--warning); font-size: 0.8rem; font-weight: 700; margin-top: 12px; justify-content: center;">
                                    <i class="bi bi-exclamation-triangle-fill"></i> This activity has already been reviewed.
                                    Some changes might require re-approval.
                                </div>
                            <?php endif; ?>
                        </div>

                        <form method="POST" enctype="multipart/form-data">

                            <!-- Section 1: Basic Information -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="bi bi-info-circle"></i>
                                    <h3>Basic Information</h3>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Title of L&D Activity <span
                                            style="color: var(--danger);">*</span></label>
                                    <input type="text" name="title" class="form-control" required
                                        value="<?php echo htmlspecialchars($activity['title']); ?>">
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                                    <div class="form-group">
                                        <label class="form-label">Date(s) Attended <span
                                                style="color: var(--danger);">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"
                                                style="background: var(--bg-secondary); border-right: none;">
                                                <i class="bi bi-calendar3"></i>
                                            </span>
                                            <input type="text" name="date_attended" id="date_picker"
                                                class="form-control"
                                                value="<?php echo htmlspecialchars($activity['date_attended']); ?>"
                                                placeholder="Click to select dates" required style="border-left: none;">
                                        </div>
                                        <small class="text-muted"
                                            style="font-size: 0.75rem; margin-top: 4px; display: block;">
                                            <i class="bi bi-info-circle"></i> Click dates on the calendar to
                                            select/deselect them.
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Venue</label>
                                        <input type="text" name="venue" class="form-control"
                                            value="<?php echo htmlspecialchars($activity['venue']); ?>"
                                            placeholder="e.g. SDO Conference Hall">
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                                    <div class="form-group">
                                        <label class="form-label">Addressed Competency/ies <span
                                                style="color: var(--danger);">*</span></label>
                                        <?php
                                        $user_ildns = $ildnRepo->getILDNList($_SESSION['user_id']);
                                        ?>
                                        $current_competencies = explode(', ', $activity['competency']);
                                        ?>
                                        <select id="competency-select" name="competency[]" class="form-control"
                                            placeholder="Select or type competency..." required multiple>
                                            <?php
                                            // Ensure current competencies that aren't in ILDNs are still options
                                            $ildn_texts = array_column($user_ildns, 'need_text');
                                            foreach ($current_competencies as $comp):
                                                if (!empty($comp) && !in_array($comp, $ildn_texts)): ?>
                                                    <option value="<?php echo htmlspecialchars($comp); ?>" selected>
                                                        <?php echo htmlspecialchars($comp); ?>
                                                    </option>
                                                <?php endif;
                                            endforeach; ?>

                                            <?php foreach ($user_ildns as $ildn): ?>
                                                <option value="<?php echo htmlspecialchars($ildn['need_text']); ?>" <?php echo in_array($ildn['need_text'], $current_competencies) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($ildn['need_text']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Conducted By <span
                                                style="color: var(--danger);">*</span></label>
                                        <input type="text" name="conducted_by" class="form-control" required
                                            value="<?php echo htmlspecialchars($activity['conducted_by']); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Modality & Type -->
                            <div class="form-section">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                                    <div>
                                        <div class="form-section-header">
                                            <i class="bi bi-diagram-3"></i>
                                            <h3>Modality</h3>
                                        </div>
                                        <div class="checkbox-grid" style="grid-template-columns: 1fr; gap: 8px;">
                                            <?php
                                            $modalities = ["Formal Training", "Job-Embedded Learning", "Relationship Discussion Learning", "Learning Action Cell"];
                                            foreach ($modalities as $m): ?>
                                                <label class="checkbox-item"
                                                    style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                    <input type="checkbox" name="modality[]" value="<?php echo $m; ?>" <?php echo isChecked($m, $activity['modality']); ?>
                                                        style="margin-top: 4px;">
                                                    <span
                                                        style="font-size: 0.85rem; line-height: 1.4;"><?php echo $m; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="form-section-header">
                                            <i class="bi bi-tags"></i>
                                            <h3>Type of L&D</h3>
                                        </div>
                                        <div class="checkbox-grid" style="grid-template-columns: 1fr; gap: 8px;">
                                            <?php
                                            $types = ["Supervisory", "Managerial", "Technical", "Others"];
                                            foreach ($types as $t): ?>
                                                <label class="checkbox-item"
                                                    style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                    <input type="checkbox" name="type_ld[]" value="<?php echo $t; ?>" <?php echo isChecked($t, $activity['type_ld']); ?>
                                                        style="margin-top: 4px;">
                                                    <span
                                                        style="font-size: 0.85rem; line-height: 1.4;"><?php echo $t; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="margin-top: 12px;">
                                            <input type="text" name="type_ld_others" class="form-control"
                                                placeholder="Specify if others..."
                                                value="<?php echo htmlspecialchars($activity['type_ld_others']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Workplace Application Plan -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="bi bi-rocket-takeoff"></i>
                                    <h3>Workplace Application Plan</h3>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Update Evidence / Attachments (Optional)</label>
                                    <input type="file" name="workplace_image[]" class="form-control" multiple>
                                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px;">Leave
                                        empty to keep your existing attachments.</p>
                                </div>
                                <div class="form-group" style="margin-top: 24px;">
                                    <label class="form-label">Reflection <span
                                            style="color: var(--danger);">*</span></label>
                                    <textarea name="reflection" class="form-control"
                                        style="min-height: 100px;"><?php echo htmlspecialchars($activity['reflection']); ?></textarea>
                                </div>
                            </div>

                            <!-- Section 4: Heads -->
                            <div class="form-section"
                                style="border-top: 1px solid var(--border-light); padding-top: 32px; background: var(--bg-secondary); margin: 0 -40px -40px -40px; padding: 40px;">
                                <div style="max-width: 400px; margin: 0 auto;">
                                    <div style="margin-top: 20px; display: flex; gap: 16px;">
                                        <button type="submit" class="btn btn-primary btn-lg" style="flex: 2;">
                                            <i class="bi bi-save"></i> UPDATE RECORD
                                        </button>
                                        <a href="view_activity.php?id=<?php echo $activity_id; ?>"
                                            class="btn btn-secondary btn-lg" style="flex: 1;">CANCEL</a>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </main>

            <footer class="user-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
            </footer>
        </div>
    </div>

    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="../js/active-forms.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Tom Select for Competencies
            new TomSelect("#competency-select", {
                create: true,
                sortField: {
                    field: "text",
                    direction: "asc"
                },
                maxItems: 5,
                placeholder: "Select or type competency..."
            });
        });
    </script>
</body>

</html>