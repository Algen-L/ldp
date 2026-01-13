<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Fetch user's rating period for alignment
    $userStmt = $pdo->prepare("SELECT rating_period FROM users WHERE id = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $userData = $userStmt->fetch();
    $current_rating_period = $userData['rating_period'] ?? 'Not Set';

    // Collect data
    $title = trim($_POST['title']);
    $date_attended = isset($_POST['date_attended']) ? trim($_POST['date_attended']) : '';
    $venue = trim($_POST['venue']);
    $modality = isset($_POST['modality']) ? implode(', ', $_POST['modality']) : '';
    $competency = trim($_POST['competency']);
    $type_ld = isset($_POST['type_ld']) ? implode(', ', $_POST['type_ld']) : '';
    $type_ld_others = isset($_POST['type_ld_others']) ? trim($_POST['type_ld_others']) : '';
    $conducted_by = trim($_POST['conducted_by']);
    $reflection = trim($_POST['reflection']);

    if (empty($title) || empty($date_attended) || empty($conducted_by) || empty($venue) || empty($competency) || empty($modality) || empty($type_ld) || empty($reflection)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } else {
        // Handle Signatures
        $signature_path = '';
        $organizer_signature_path = '';

        // Function to handle file saving
        function saveUpload($fileKey, $prefix, $subDir = 'signatures')
        {
            if (!isset($_FILES[$fileKey]))
                return '';

            $files = $_FILES[$fileKey];
            $isMultiple = is_array($files['name']);
            $count = $isMultiple ? count($files['name']) : 1;
            $paths = [];

            $uploadDir = '../uploads/' . $subDir . '/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);

            for ($i = 0; $i < $count; $i++) {
                $error = $isMultiple ? $files['error'][$i] : $files['error'];
                if ($error === UPLOAD_ERR_OK) {
                    $tmpName = $isMultiple ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $originalName = $isMultiple ? $files['name'][$i] : $files['name'];

                    $fileName = uniqid() . '_' . $prefix . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $originalName);
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $paths[] = 'uploads/' . $subDir . '/' . $fileName;
                    }
                }
            }

            if (empty($paths))
                return '';
            return $isMultiple ? json_encode($paths) : $paths[0];
        }

        // Function to handle signature saving
        function saveSignature($fileKey, $dataKey, $prefix)
        {
            $path = saveUpload($fileKey, $prefix, 'signatures');
            if ($path)
                return $path;

            if (!empty($_POST[$dataKey])) {
                $data = $_POST[$dataKey];
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

        $signature_path = saveSignature('signature_file', 'signature_data', 'attest');
        $organizer_signature_path = saveSignature('organizer_signature_file', 'organizer_signature_data', 'org');
        $workplace_image_path = saveUpload('workplace_image', 'work', 'workplace');

        // Server-side validation for mandatory signature
        if (empty($organizer_signature_path)) {
            $message = "Organizer signature is required.";
            $messageType = "error";
        } else {
            $sql = "INSERT INTO ld_activities (user_id, title, date_attended, venue, modality, competency, type_ld, type_ld_others, conducted_by, organizer_signature_path, workplace_application, workplace_image_path, reflection, rating_period) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if (
                $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $date_attended,
                    $venue,
                    $modality,
                    $competency,
                    $type_ld,
                    $type_ld_others,
                    $conducted_by,
                    $organizer_signature_path,
                    $workplace_image_path,
                    $reflection,
                    $current_rating_period
                ])
            ) {
                // Log activity submission
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Submitted Activity', "Activity Title: $title", $_SERVER['REMOTE_ADDR']]);

                $message = "Activity submitted successfully!";
                $messageType = "success";
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
            letter-spacing: 0.5px;
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

        .checkbox-item input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .signature-box {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-align: center;
            background: var(--bg-primary);
            transition: all var(--transition-fast);
        }

        .signature-box.has-content {
            border-color: var(--success);
            background: var(--success-bg);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 32px;
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-2xl);
        }

        canvas {
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #fff;
            cursor: crosshair;
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
    </style>
</head>

<body>

    <div class="user-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle" id="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="text-muted">User</span>
                        <i class="bi bi-chevron-right separator"></i>
                        <h1 class="page-title">Record Activity</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <a href="home.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Dashboard
                    </a>
                </div>
            </header>

            <main class="content-wrapper">
                <?php if ($message): ?>
                    <script>
                        window.addEventListener('DOMContentLoaded', function () {
                            showToast("<?php echo ($messageType === 'success') ? 'Success!' : 'Notice'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
                        });
                    </script>
                <?php endif; ?>

                <div class="dashboard-card" style="max-width: 900px; margin: 0 auto; overflow: visible;">
                    <div class="card-body" style="padding: 40px;">
                        <form method="POST" action="" enctype="multipart/form-data" id="activity-form">

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
                                        placeholder="e.g. Specialized Training on Digital Literacy">
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
                                        <small class="text-muted"
                                            style="font-size: 0.75rem; margin-top: 4px; display: block;">
                                            <i class="bi bi-info-circle"></i> Click multiple dates to select/deselect
                                            them.
                                        </small>
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
                                        <input type="text" name="competency" class="form-control"
                                            placeholder="e.g. Communication, Technical Skills">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Conducted By <span
                                                style="color: var(--danger);">*</span></label>
                                        <input type="text" name="conducted_by" id="conducted_by" class="form-control"
                                            required placeholder="Name of Organizer">

                                        <!-- Organizer Sig - Relocated here -->
                                        <div id="organizer-signature-section" style="display: none; margin-top: 16px;">
                                            <label class="form-label">Organizer Signature <span
                                                    style="color: var(--danger);">*</span></label>
                                            <div class="signature-box" id="org-sig-box" style="padding: 16px;">
                                                <div id="org-sig-preview-container"
                                                    style="display: none; margin-bottom: 12px;">
                                                    <img id="org-sig-preview" src=""
                                                        style="max-height: 80px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                                                </div>
                                                <div style="display: flex; gap: 8px; justify-content: center;">
                                                    <button type="button" class="btn btn-secondary btn-sm"
                                                        onclick="openSignatureModal('org')"><i class="bi bi-brush"></i>
                                                        Draw</button>
                                                    <button type="button" class="btn btn-secondary btn-sm"
                                                        onclick="triggerSignatureUpload('org')"><i
                                                            class="bi bi-upload"></i>
                                                        Upload</button>
                                                </div>
                                                <input type="file" id="org-sig-file" accept="image/*"
                                                    style="display: none;">
                                                <input type="hidden" name="organizer_signature_data"
                                                    id="organizer_signature_data">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Modality & Type -->
                            <div class="form-section">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                                    <div>
                                        <div class="form-section-header">
                                            <i class="bi bi-diagram-3"></i>
                                            <h3>Modality <span style="color: var(--danger);">*</span></h3>
                                        </div>
                                        <div class="checkbox-grid" style="grid-template-columns: 1fr;">
                                            <?php
                                            $modalities = ["Formal Training", "Job-Embedded Learning", "Relationship Discussion Learning", "Learning Action Cell"];
                                            foreach ($modalities as $m): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" name="modality[]" value="<?php echo $m; ?>">
                                                    <span><?php echo $m; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="form-section-header">
                                            <i class="bi bi-tags"></i>
                                            <h3>Type of L&D <span style="color: var(--danger);">*</span></h3>
                                        </div>
                                        <div class="checkbox-grid" style="grid-template-columns: 1fr;">
                                            <?php
                                            $types = ["Supervisory", "Managerial", "Technical"];
                                            foreach ($types as $t): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" name="type_ld[]" value="<?php echo $t; ?>">
                                                    <span><?php echo $t; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="type_ld[]" value="Others"
                                                    id="type-others-checkbox">
                                                <span>Others</span>
                                            </label>
                                        </div>
                                        <div id="type-others-input-container" style="display: none; margin-top: 12px;">
                                            <input type="text" name="type_ld_others" class="form-control"
                                                placeholder="Please specify...">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Workplace Application Plan & Reflection -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="bi bi-rocket-takeoff"></i>
                                    <h3>Workplace Application Plan</h3>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Evidence / Attachments <span
                                            style="color: var(--danger);">*</span></label>
                                    <div class="signature-box" id="drop-zone"
                                        style="cursor: pointer; position: relative;">
                                        <i class="bi bi-cloud-arrow-up"
                                            style="font-size: 2.5rem; color: var(--text-muted);"></i>
                                        <p style="margin-top: 12px; font-weight: 600; color: var(--text-secondary);">
                                            Drag & Drop or Click to Upload</p>
                                        <p style="font-size: 0.8rem; color: var(--text-muted);">Supports images, PDFs,
                                            and Word docs</p>
                                        <input type="file" name="workplace_image[]" id="workplace_image"
                                            accept="image/*,.pdf,.doc,.docx" multiple
                                            style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer;">
                                        <div id="file-list"
                                            style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 24px;">
                                    <label class="form-label">Reflection <span
                                            style="color: var(--danger);">*</span></label>
                                    <textarea name="reflection" class="form-control" style="min-height: 100px;" required
                                        placeholder="What are your key takeaways?"></textarea>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div style="margin-top: 48px; text-align: center; padding-bottom: 40px;">
                                <button type="button" class="btn btn-primary btn-lg" onclick="submitForm()"
                                    style="width: 100%; max-width: 400px;">
                                    <i class="bi bi-check-circle-fill"></i> SUBMIT ACTIVITY RECORD
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </main>

            <footer class="user-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. All rights reserved.</p>
            </footer>
        </div>
    </div>

    <!-- Signature Modal -->
    <div id="signature-modal" class="modal-overlay">
        <div class="modal-content">
            <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 24px; color: var(--text-primary);">Draw Your
                Signature</h2>
            <div style="background: #f1f5f9; padding: 10px; border-radius: var(--radius-md); margin-bottom: 24px;">
                <canvas id="modal-sig-canvas" width="436" height="200"></canvas>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="clearModalCanvas()">Clear</button>
                <button type="button" class="btn btn-primary" onclick="saveModalSignature()">Done</button>
                <button type="button" class="btn btn-secondary" style="border: none; color: var(--danger);"
                    onclick="closeSignatureModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr("#date_picker", {
                mode: "multiple",
                dateFormat: "Y-m-d",
                conjunction: ", ",
                altInput: true,
                altFormat: "M j, Y",
                allowInput: false
            });
        });

        // Modal Logic
        const modal = document.getElementById('signature-modal');
        const modalCanvas = document.getElementById('modal-sig-canvas');
        const modalCtx = modalCanvas.getContext('2d');
        let currentGroup = '';
        let isDrawing = false;

        function initModalSignature() {
            const startDraw = (e) => {
                isDrawing = true;
                const pos = getPos(e);
                modalCtx.beginPath();
                modalCtx.moveTo(pos.x, pos.y);
            };
            const draw = (e) => {
                if (!isDrawing) return;
                const pos = getPos(e);
                modalCtx.lineTo(pos.x, pos.y);
                modalCtx.stroke();
            };
            const stopDraw = () => { isDrawing = false; };

            const getPos = (e) => {
                const rect = modalCanvas.getBoundingClientRect();
                const clientX = e.clientX || e.touches[0].clientX;
                const clientY = e.clientY || e.touches[0].clientY;
                return { x: clientX - rect.left, y: clientY - rect.top };
            };

            modalCanvas.addEventListener('mousedown', startDraw);
            modalCanvas.addEventListener('mousemove', draw);
            modalCanvas.addEventListener('mouseup', stopDraw);
            modalCanvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDraw(e); }, { passive: false });
            modalCanvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e); }, { passive: false });
            modalCanvas.addEventListener('touchend', stopDraw);

            modalCtx.lineWidth = 2.5;
            modalCtx.lineCap = 'round';
            modalCtx.strokeStyle = '#000';
        }

        function openSignatureModal(group) {
            currentGroup = group;
            modal.style.display = 'flex';
            clearModalCanvas();
        }

        function closeSignatureModal() { modal.style.display = 'none'; }
        function clearModalCanvas() { modalCtx.clearRect(0, 0, modalCanvas.width, modalCanvas.height); }

        function saveModalSignature() {
            const dataUrl = modalCanvas.toDataURL("image/png");

            // Only handle organizer signature here as user signature is moved to admin approval
            document.getElementById('organizer_signature_data').value = dataUrl;
            document.getElementById('org-sig-preview').src = dataUrl;
            document.getElementById('org-sig-preview-container').style.display = 'block';
            document.getElementById('org-sig-box').classList.add('has-content');

            closeSignatureModal();
        }

        function triggerSignatureUpload() {
            const input = document.getElementById('org-sig-file');
            input.click();
            input.onchange = function () {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        document.getElementById('org-sig-preview').src = e.target.result;
                        document.getElementById('org-sig-preview-container').style.display = 'block';
                        document.getElementById('org-sig-box').classList.add('has-content');
                        document.getElementById('organizer_signature_data').value = "";
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            };
        }

        // Logic for Dynamic Sections
        document.getElementById('conducted_by').addEventListener('input', function () {
            const section = document.getElementById('organizer-signature-section');
            if (section) section.style.display = this.value.trim() ? 'block' : 'none';
        });

        document.getElementById('type-others-checkbox').addEventListener('change', function () {
            document.getElementById('type-others-input-container').style.display = this.checked ? 'block' : 'none';
        });

        // File List Preview
        document.getElementById('workplace_image').addEventListener('change', function () {
            const list = document.getElementById('file-list');
            list.innerHTML = '';
            Array.from(this.files).forEach(file => {
                const badge = document.createElement('span');
                badge.className = 'activity-status-badge status-recommending';
                badge.innerHTML = `<i class="bi bi-file-earmark"></i> ${file.name.substring(0, 15)}...`;
                list.appendChild(badge);
            });
            if (this.files.length > 0) document.getElementById('drop-zone').classList.add('has-content');
        });


        function submitForm() {
            const form = document.getElementById('activity-form');

            // Basic HTML5 validation
            if (!form.checkValidity()) {
                // Find first invalid element to show specific toast
                const invalidElem = form.querySelector(':invalid');
                if (invalidElem) {
                    let label = invalidElem.closest('.form-group')?.querySelector('.form-label')?.innerText.replace('*', '').trim() || "Required field";
                    if (invalidElem.id === 'workplace_image') label = "Evidence / Attachments";
                    showToast("Form Incomplete", `Please provide: ${label}`, "error");
                    invalidElem.focus();
                } else {
                    form.reportValidity();
                }
                return;
            }

            // Modality Check
            const modalityChecked = form.querySelectorAll('input[name="modality[]"]:checked').length > 0;
            if (!modalityChecked) {
                showToast("Missing Information", "Please select at least one Modality.", "error");
                document.querySelector('input[name="modality[]"]').focus();
                return;
            }

            // Type Check
            const typeChecked = form.querySelectorAll('input[name="type_ld[]"]:checked').length > 0;
            if (!typeChecked) {
                showToast("Missing Information", "Please select at least one Type of L&D.", "error");
                document.querySelector('input[name="type_ld[]"]').focus();
                return;
            }

            // Evidence Check
            const evidenceFiles = document.getElementById('workplace_image').files.length;
            if (evidenceFiles === 0) {
                showToast("Missing Evidence", "Please upload at least one supporting document or image.", "error");
                document.getElementById('workplace_image').scrollIntoView({ behavior: 'smooth' });
                return;
            }

            // Mandatory Signature Check
            const orgVal = document.getElementById('organizer_signature_data').value || document.getElementById('org-sig-file').files.length;

            if (!orgVal) {
                showToast("Missing Signature", "Please provide the Organizer's signature.", "error");
                document.getElementById('org-sig-box').scrollIntoView({ behavior: 'smooth' });
                return;
            }

            form.submit();
        }

        initModalSignature();
    </script>
</body>

</html>