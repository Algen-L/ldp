<?php
session_start();
require '../includes/init_repos.php';
require '../includes/functions/file-functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Fetch user's rating period for alignment
    $userData = $userRepo->getUserById($_SESSION['user_id']);
    $current_rating_period = $userData['rating_period'] ?? 'Not Set';

    // Collect data
    $title = trim($_POST['title']);
    $date_attended = isset($_POST['date_attended']) ? trim($_POST['date_attended']) : '';
    $venue = trim($_POST['venue']);
    $modality = isset($_POST['modality']) ? implode(', ', $_POST['modality']) : '';
    // Handle multiple competencies from Tom Select
    $competency = isset($_POST['competency']) ? (is_array($_POST['competency']) ? implode(', ', $_POST['competency']) : trim($_POST['competency'])) : '';
    $type_ld = isset($_POST['type_ld']) ? implode(', ', $_POST['type_ld']) : '';
    $type_ld_others = isset($_POST['type_ld_others']) ? trim($_POST['type_ld_others']) : '';
    $conducted_by = trim($_POST['conducted_by']);
    $reflection = trim($_POST['reflection']);

    if (empty($title) || empty($date_attended) || empty($conducted_by) || empty($venue) || empty($competency) || empty($modality) || empty($type_ld) || empty($reflection)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } else {
        $organizer_signature_path = saveSignature('organizer_signature_file', 'organizer_signature_data', 'org');
        $workplace_image_path = saveUpload('workplace_image', 'work', 'workplace');

        // Server-side validation for mandatory signature
        if (empty($organizer_signature_path)) {
            $message = "Organizer signature is required.";
            $messageType = "error";
        } else {
            $activityData = [
                'user_id' => $_SESSION['user_id'],
                'title' => $title,
                'date_attended' => $date_attended,
                'venue' => $venue,
                'modality' => $modality,
                'competency' => $competency,
                'type_ld' => $type_ld,
                'type_ld_others' => $type_ld_others,
                'conducted_by' => $conducted_by,
                'organizer_signature_path' => $organizer_signature_path,
                'workplace_application' => '',
                'workplace_image_path' => $workplace_image_path,
                'reflection' => $reflection,
                'rating_period' => $current_rating_period
            ];

            if ($activityRepo->createActivity($activityData)) {
                // Log activity submission
                $logRepo->logAction($_SESSION['user_id'], 'Submitted Activity', "Activity Title: $title");

                $message = "Activity submitted successfully!";
                $messageType = "success";
                echo "<script>localStorage.removeItem('add_activity_form');</script>";
            } else {
                $message = "Error submitting activity.";
                $messageType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record New Activity - LDP</title>
    <?php require '../includes/head.php'; ?>
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
</head>

<body>

    <div class="app-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Record Activity</h1>
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
                <div class="dashboard-card" style="max-width: 900px; margin: 0 auto; overflow: visible;">
                    <div class="card-body" style="padding: 40px;">
                        <form id="activity-form" method="POST" enctype="multipart/form-data">

                            <h2
                                style="font-size: 1.5rem; font-weight: 800; color: var(--primary); text-align: center; margin-bottom: 40px; text-transform: uppercase; letter-spacing: 1px;">
                                LEARNING AND DEVELOPMENT ATTENDED
                                <div
                                    style="width: 60px; height: 4px; background: var(--accent); margin: 12px auto 0; border-radius: 2px;">
                                </div>
                            </h2>

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
                                        placeholder="Enter the full title of the training or activity">
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
                                                class="form-control" placeholder="Click to select dates" required
                                                style="border-left: none;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Venue <span
                                                style="color: var(--danger);">*</span></label>
                                        <input type="text" name="venue" class="form-control" required
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
                                        <select id="competency_select" name="competency[]" class="form-control"
                                            placeholder="Select or type learning needs..." required multiple>
                                            <?php foreach ($user_ildns as $ildn): ?>
                                                <option value="<?php echo htmlspecialchars($ildn['need_text']); ?>">
                                                    <?php echo htmlspecialchars($ildn['need_text']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Conducted By <span
                                                style="color: var(--danger);">*</span></label>
                                        <input type="text" name="conducted_by" id="conducted_by" class="form-control"
                                            required placeholder="Organization or Agency">

                                        <!-- Nested Signature Box (Activates on input) -->
                                        <div id="organizer-signature-section" style="display: none; margin-top: 20px;">
                                            <div class="signature-box" id="org-sig-box" style="cursor: default;">
                                                <!-- Direct Canvas Drawing -->
                                                <div id="org-sig-canvas-container"
                                                    style="width: 100%; height: 100%; position: relative;">
                                                    <canvas id="org-sig-canvas"
                                                        style="width: 100%; height: 100%; cursor: crosshair; touch-action: none;"></canvas>

                                                    <div id="org-sig-hint"
                                                        style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #94a3b8; pointer-events: none; text-align: center;">
                                                        <i class="bi bi-pencil-square"
                                                            style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                        <p
                                                            style="font-size: 0.75rem; font-weight: 700; margin-top: 8px; text-transform: uppercase;">
                                                            Draw Signature Here</p>
                                                    </div>
                                                </div>

                                                <div id="org-sig-preview-container"
                                                    style="display:none; width: 100%; height: 100%; padding: 15px; background: white; position: absolute; top: 0; left: 0;">
                                                    <img id="org-sig-preview"
                                                        style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                                </div>

                                                <div class="sig-actions">
                                                    <button type="button" class="btn-sig"
                                                        onclick="triggerSignatureUpload()"
                                                        title="Upload Signature Image">
                                                        <i class="bi bi-upload"></i> Upload
                                                    </button>
                                                    <button type="button" class="btn-sig" onclick="clearOrgSignature()"
                                                        title="Clear and Redraw">
                                                        <i class="bi bi-eraser"></i> Clear
                                                    </button>
                                                </div>

                                                <input type="hidden" name="organizer_signature_data"
                                                    id="organizer_signature_data">
                                                <input type="file" name="organizer_signature_file" id="org-sig-file"
                                                    hidden accept="image/*">
                                            </div>

                                            <div
                                                style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 10px;">
                                                <div style="height: 1px; background: #e2e8f0; flex: 1;"></div>
                                                <p
                                                    style="font-size: 0.72rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <i class="bi bi-shield-check"></i> Digital Attestation
                                                </p>
                                                <div style="height: 1px; background: #e2e8f0; flex: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Modalities & Type -->
                            <div class="form-section">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                                    <div>
                                        <div class="form-section-header">
                                            <i class="bi bi-diagram-3"></i>
                                            <h3>Modality <span style="color: var(--danger);">*</span></h3>
                                        </div>
                                        <div class="checkbox-grid" style="grid-template-columns: 1fr; gap: 8px;">
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="modality[]" value="Formal Training"
                                                    style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Formal
                                                    Training</span>
                                            </label>
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="modality[]" value="Job-Embedded Learning"
                                                    style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Job-Embedded
                                                    Learning</span>
                                            </label>
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="modality[]"
                                                    value="Relationship Discussion Learning" style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Relationship
                                                    Discussion Learning</span>
                                            </label>
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="modality[]" value="Learning Action Cell"
                                                    style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Learning Action
                                                    Cell</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="form-section-header">
                                            <i class="bi bi-tags"></i>
                                            <h3>Type of L&D <span style="color: var(--danger);">*</span></h3>
                                        </div>
                                        <div class="checkbox-grid" style="grid-template-columns: 1fr; gap: 8px;">
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="type_ld[]" value="Supervisory"
                                                    style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Supervisory</span>
                                            </label>
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="type_ld[]" value="Managerial"
                                                    style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Managerial</span>
                                            </label>
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="type_ld[]" value="Technical"
                                                    style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Technical</span>
                                            </label>
                                            <label class="checkbox-item"
                                                style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px;">
                                                <input type="checkbox" name="type_ld[]" value="Others"
                                                    id="type-others-checkbox" style="margin-top: 4px;">
                                                <span style="font-size: 0.85rem; line-height: 1.4;">Others
                                                    (Specify)</span>
                                            </label>
                                        </div>
                                        <div id="type-others-input-container" style="display: none; margin-top: 12px;">
                                            <input type="text" name="type_ld_others" class="form-control"
                                                placeholder="Please specify type...">
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

                                <style>
                                    .premium-label {
                                        font-size: 0.75rem;
                                        font-weight: 800;
                                        color: var(--text-secondary);
                                        text-transform: uppercase;
                                        letter-spacing: 1px;
                                        margin-bottom: 12px;
                                        display: flex;
                                        align-items: center;
                                        gap: 4px;
                                    }

                                    .file-drop-zone {
                                        border: 2px dashed #cbd5e1;
                                        border-radius: 16px;
                                        padding: 40px 20px;
                                        text-align: center;
                                        background: #f8fafc;
                                        cursor: pointer;
                                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                        display: flex;
                                        flex-direction: column;
                                        align-items: center;
                                        gap: 12px;
                                        position: relative;
                                        overflow: hidden;
                                    }

                                    .file-drop-zone:hover,
                                    .file-drop-zone.drag-over {
                                        border-color: var(--primary);
                                        background: #eff6ff;
                                        transform: translateY(-2px);
                                        box-shadow: 0 10px 15px -3px rgba(15, 76, 117, 0.1);
                                    }

                                    .file-drop-zone i {
                                        font-size: 3rem;
                                        color: var(--primary);
                                        opacity: 0.8;
                                        transition: transform 0.3s ease;
                                    }

                                    .file-drop-zone:hover i,
                                    .file-drop-zone.drag-over i {
                                        transform: scale(1.1);
                                        opacity: 1;
                                    }

                                    .file-drop-zone p {
                                        font-size: 1rem;
                                        font-weight: 700;
                                        color: var(--text-primary);
                                        margin: 0;
                                    }

                                    .file-drop-zone .upload-hint {
                                        font-size: 0.8rem;
                                        color: #64748b;
                                        font-weight: 500;
                                    }

                                    #file-list .file-badge {
                                        background: white;
                                        padding: 8px 16px;
                                        border-radius: 10px;
                                        border: 1px solid #e2e8f0;
                                        display: flex;
                                        align-items: center;
                                        gap: 10px;
                                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                                        font-size: 0.85rem;
                                        font-weight: 600;
                                        color: var(--text-primary);
                                        animation: slideIn 0.3s ease-out forwards;
                                    }

                                    @keyframes slideIn {
                                        from {
                                            opacity: 0;
                                            transform: translateY(10px);
                                        }

                                        to {
                                            opacity: 1;
                                            transform: translateY(0);
                                        }
                                    }

                                    .privacy-notice-box {
                                        background: #f1f5f9;
                                        border-radius: 12px;
                                        padding: 24px;
                                        border: 1px solid #e2e8f0;
                                        margin-top: 40px;
                                        display: flex;
                                        gap: 20px;
                                        align-items: flex-start;
                                        text-align: left;
                                        transition: all 0.3s ease;
                                    }

                                    .privacy-notice-box:has(input:checked) {
                                        background: #f0fdf4;
                                        border-color: #bbf7d0;
                                    }

                                    .privacy-check-container {
                                        margin-top: 12px;
                                        display: flex;
                                        align-items: center;
                                        gap: 12px;
                                        padding-top: 15px;
                                        border-top: 1px solid rgba(0, 0, 0, 0.05);
                                        cursor: pointer;
                                    }

                                    .privacy-check-container input {
                                        width: 20px;
                                        height: 20px;
                                        cursor: pointer;
                                    }

                                    .privacy-check-text {
                                        font-size: 0.85rem;
                                        font-weight: 700;
                                        color: var(--text-primary);
                                    }

                                    .privacy-notice-box i {
                                        font-size: 1.5rem;
                                        color: var(--primary);
                                        margin-top: -2px;
                                    }

                                    .privacy-content h4 {
                                        font-size: 0.85rem;
                                        font-weight: 800;
                                        color: var(--text-primary);
                                        margin-bottom: 6px;
                                        text-transform: uppercase;
                                        letter-spacing: 0.5px;
                                    }

                                    .privacy-content p {
                                        font-size: 0.82rem;
                                        color: #64748b;
                                        line-height: 1.6;
                                        margin: 0;
                                        font-weight: 500;
                                    }

                                    /* Signature Box Enhancements */
                                    .signature-box {
                                        background: #f8fafc;
                                        border: 2px dashed #cbd5e1;
                                        border-radius: 16px;
                                        height: 160px;
                                        position: relative;
                                        display: flex;
                                        flex-direction: column;
                                        align-items: center;
                                        justify-content: center;
                                        transition: all 0.3s ease;
                                        cursor: pointer;
                                        overflow: hidden;
                                    }

                                    .signature-box:hover {
                                        border-color: var(--primary);
                                        background: #fdfdfd;
                                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                                    }

                                    .sig-placeholder {
                                        display: flex;
                                        flex-direction: column;
                                        align-items: center;
                                        gap: 10px;
                                        color: #64748b;
                                        transition: all 0.2s ease;
                                    }

                                    .signature-box:hover .sig-placeholder {
                                        color: var(--primary);
                                        transform: scale(1.05);
                                    }

                                    .sig-placeholder i {
                                        font-size: 2rem;
                                        opacity: 0.7;
                                    }

                                    .sig-placeholder span {
                                        font-size: 0.85rem;
                                        font-weight: 700;
                                        text-transform: uppercase;
                                        letter-spacing: 0.5px;
                                    }

                                    .sig-actions {
                                        position: absolute;
                                        bottom: 12px;
                                        right: 12px;
                                        display: flex;
                                        gap: 8px;
                                        z-index: 10;
                                    }

                                    .btn-sig {
                                        background: white;
                                        border: 1px solid #e2e8f0;
                                        padding: 6px 12px;
                                        border-radius: 8px;
                                        font-size: 0.7rem;
                                        font-weight: 700;
                                        color: var(--text-secondary);
                                        display: flex;
                                        align-items: center;
                                        gap: 6px;
                                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                                        transition: all 0.2s ease;
                                    }

                                    .btn-sig:hover {
                                        background: var(--bg-primary);
                                        border-color: var(--primary-light);
                                        color: var(--primary);
                                        transform: translateY(-1px);
                                    }
                                </style>

                                <div class="form-group">
                                    <label class="premium-label">Evidence / Attachments <span
                                            style="color: var(--danger);">*</span></label>
                                    <div class="file-drop-zone" id="drop-zone"
                                        onclick="document.getElementById('workplace_image').click()">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <p>Click to upload files (Images or Document)</p>
                                        <span class="upload-hint">Drag and drop your files here or click to
                                            browse</span>
                                        <input type="file" name="workplace_image[]" id="workplace_image" multiple
                                            hidden>
                                        <div id="file-list"
                                            style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 15px;">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-top: 24px;">
                                    <label class="form-label">Reflection <span
                                            style="color: var(--danger);">*</span></label>
                                    <textarea name="reflection" class="form-control" required style="min-height: 120px;"
                                        placeholder="Share your key takeaways and how this will improve your performance..."></textarea>
                                </div>
                            </div>


                            <!-- Privacy Notice -->
                            <div class="privacy-notice-box">
                                <i class="bi bi-shield-lock-fill"></i>
                                <div class="privacy-content">
                                    <h4>Privacy Notice</h4>
                                    <p>We collect personal and professional information (Name, Activity Details, and
                                        Evidence) when you submit this record. This data will be utilized solely for
                                        documentation and processing of your L&D progress within SDO DepEd. Only
                                        authorized personnel have access to this information, and it will be retained
                                        only as long as necessary for the fulfillment of its purpose.</p>

                                    <label class="privacy-check-container">
                                        <input type="checkbox" id="privacy-agree" name="privacy_agree" required>
                                        <span class="privacy-check-text">I have read and agree to the Privacy
                                            Notice</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div style="margin-top: 32px; text-align: center; padding-bottom: 40px;">
                                <button type="button" class="btn btn-primary btn-lg" onclick="validateAndSubmitForm()"
                                    style="width: 100%; max-width: 400px;">
                                    <i class="bi bi-check-circle-fill"></i> SUBMIT ACTIVITY RECORD
                                </button>
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
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../js/form-autosave.js"></script>
    <script src="../js/active-forms.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const datePicker = flatpickr("#date_picker", {
                mode: "multiple",
                dateFormat: "Y-m-d",
                conjunction: ", ",
                altInput: true,
                altFormat: "M j, Y",
                disableMobile: "true",
                onChange: function (selectedDates, dateStr, instance) {
                    // Manually trigger change on the underlying input for autosave
                    instance.element.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            const competencySelect = new TomSelect('#competency_select', {
                plugins: ['remove_button'],
                create: true,
                persist: false,
                placeholder: 'Select or type learning needs...',
                maxOptions: 50,
                onItemAdd: function (value, $item) {
                    this.setTextboxValue('');
                    this.refreshOptions();
                },
                onChange: function () {
                    // Manually trigger change on the underlying select for autosave
                    document.getElementById('competency_select').dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Initialize Auto-save
            const autosave = initFormAutosave('activity-form', 'add_activity_form');

            // Explicitly sync Tom Select and Flatpickr after restoration
            const savedData = localStorage.getItem('add_activity_form');
            if (savedData) {
                const data = JSON.parse(savedData);
                if (data['competency[]']) {
                    competencySelect.setValue(data['competency[]']);
                }
                if (data['date_attended']) {
                    datePicker.setDate(data['date_attended'].split(', '));
                }
            }
        });

        <?php if ($message): ?>
            showToast("<?php echo ($messageType === 'success') ? 'Success' : 'Notice'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
        <?php endif; ?>
    </script>
</body></html>
