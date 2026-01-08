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
    // Collect data
    $title = trim($_POST['title']);
    $date_attended = $_POST['date_attended'];
    $venue = trim($_POST['venue']);
    $modality = isset($_POST['modality']) ? implode(', ', $_POST['modality']) : '';
    $competency = trim($_POST['competency']);
    $type_ld = isset($_POST['type_ld']) ? implode(', ', $_POST['type_ld']) : '';
    $type_ld_others = isset($_POST['type_ld_others']) ? trim($_POST['type_ld_others']) : '';
    $conducted_by = trim($_POST['conducted_by']);
    $approved_by = trim($_POST['approved_by']);
    $workplace_application = trim($_POST['workplace_application']);
    $reflection = trim($_POST['reflection']);

    if (empty($title) || empty($date_attended)) {
        $message = "Title and Date are required.";
        $messageType = "error";
    } else {
        // Handle Signatures
        $signature_path = '';
        $organizer_signature_path = '';

        // Function to handle file saving
        function saveUpload($fileKey, $prefix, $subDir = 'signatures')
        {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/' . $subDir . '/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);
                $fileName = uniqid() . '_' . $prefix . '_' . basename($_FILES[$fileKey]['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
                    return 'uploads/' . $subDir . '/' . $fileName;
                }
            }
            return '';
        }

        // Function to handle signature saving (re-using upload for files)
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
        if (empty($signature_path)) {
            $message = "Attestation signature is required.";
            $messageType = "error";
        } elseif (empty($approved_by)) {
            $message = "Name of Immediate Head is required.";
            $messageType = "error";
        } else {
            $sql = "INSERT INTO ld_activities (user_id, title, date_attended, venue, modality, competency, type_ld, type_ld_others, conducted_by, organizer_signature_path, approved_by, workplace_application, workplace_image_path, reflection, signature_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
                    $approved_by,
                    $workplace_application,
                    $workplace_image_path,
                    $reflection,
                    $signature_path
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
    <title>Add Activity - LDP</title>
    <?php require '../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/pages/add-activity.css">
</head>

<body>

    <div class="dashboard-container">
        <div class="sidebar">
            <?php require '../includes/sidebar.php'; ?>
        </div>

        <div class="main-content">
            <div class="passbook-container" style="width: 800px;">
                <div class="header">
                    <h1>Learning & Development Passbook</h1>
                    <p>Add New L&D Activity</p>
                </div>

                <?php if ($message): ?>
                    <script>
                        window.addEventListener('DOMContentLoaded', function () {
                            showToast(
                                "<?php echo ($messageType === 'success') ? 'Success!' : 'Notice'; ?>",
                                "<?php echo $message; ?>",
                                "<?php echo $messageType; ?>"
                            );
                        });
                    </script>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">

                    <div class="form-section-title">Learning and Development Attended</div>

                    <div class="form-group">
                        <label>Title of L&D Activity:</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Date:</label>
                            <input type="date" name="date_attended" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Venue:</label>
                            <input type="text" name="venue" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div>
                            <div class="form-group">
                                <label>Addressed Competency/ies:</label>
                                <input type="text" name="competency" class="form-control">
                            </div>
                            <div class="form-group" style="margin-top: 15px;">
                                <label>Conducted by:</label>
                                <input type="text" name="conducted_by" id="conducted_by" class="form-control"
                                    placeholder="Name of organizer">
                            </div>
                            <!-- Organizer Signature Pad -->
                            <div id="organizer-signature-section"
                                style="display: none; margin-top: 15px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
                                <div
                                    style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                                    <span
                                        style="font-weight: 600; font-size: 0.9em; color: var(--primary-blue);">Organizer
                                        Signature:</span>
                                    <div style="display: flex; gap: 5px;">
                                        <button type="button" class="btn"
                                            style="width: auto; background-color: var(--primary-blue); padding: 5px 12px; font-size: 0.8em; margin: 0;"
                                            onclick="openSignatureModal('org')">
                                            Draw
                                        </button>
                                        <button type="button" class="btn"
                                            style="width: auto; background-color: #5bc0de; padding: 5px 12px; font-size: 0.8em; margin: 0;"
                                            onclick="triggerSignatureUpload('org')">
                                            Upload
                                        </button>
                                    </div>
                                </div>

                                <div style="text-align: center;">
                                    <span id="org-sig-status" class="sig-status" style="display: none;">No signature
                                        captured</span>
                                    <div id="org-upload-status"
                                        style="font-size: 0.85em; color: var(--primary-blue); font-weight: 600; margin-top: 5px;">
                                    </div>
                                    <div id="org-sig-preview-container" style="margin-top: 10px; display: none;">
                                        <img id="org-sig-preview" src=""
                                            style="max-height: 80px; border: 1px solid #ccc; border-radius: 4px; padding: 2px;">
                                    </div>
                                    <input type="file" id="org-sig-file" accept="image/*" style="display: none;">
                                </div>
                                <input type="hidden" name="organizer_signature_data" id="organizer_signature_data">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="form-group">
                                <label>Modality:</label>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="modality[]" value="Formal Training"> Formal
                                        Training</label>
                                    <label><input type="checkbox" name="modality[]" value="Job-Embedded Learning">
                                        Job-Embedded Learning</label>
                                    <label><input type="checkbox" name="modality[]"
                                            value="Relationship Discussion Learning"> Relationship Discussion
                                        Learning</label>
                                    <label><input type="checkbox" name="modality[]" value="Learning Action Cell">
                                        Learning Action Cell</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Type of L&D:</label>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="type_ld[]" value="Supervisory">
                                        Supervisory</label>
                                    <label><input type="checkbox" name="type_ld[]" value="Managerial">
                                        Managerial</label>
                                    <label><input type="checkbox" name="type_ld[]" value="Technical"> Technical</label>
                                    <label>
                                        <input type="checkbox" name="type_ld[]" value="Others"
                                            id="type-others-checkbox"> Others
                                    </label>
                                    <div id="type-others-input-container" style="display: none; margin-top: 5px;">
                                        <input type="text" name="type_ld_others" class="form-control"
                                            placeholder="Please specify..."
                                            style="font-size: 0.85em; padding: 5px 8px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <div class="form-section-title" style="margin-top: 30px;">Workplace Application</div>

                    <div class="workplace-box"
                        style="border: 2px solid var(--primary-blue); padding: 20px; border-radius: 12px; display: flex; flex-direction: column; margin-top: 15px; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">

                        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 10px;">
                            <label style="font-weight: bold; font-size: 0.9em; margin-bottom: 0;">Method:</label>
                            <label style="font-weight: normal; font-size: 0.9em; margin-bottom: 0; cursor: pointer;">
                                <input type="radio" name="workplace_method" value="text" checked
                                    onclick="toggleWorkplaceMethod('text')"> Text Application
                            </label>
                            <label style="font-weight: normal; font-size: 0.9em; margin-bottom: 0; cursor: pointer;">
                                <input type="radio" name="workplace_method" value="image"
                                    onclick="toggleWorkplaceMethod('image')"> Upload Picture
                            </label>
                        </div>

                        <!-- Text Area -->
                        <div id="workplace-text-container">
                            <textarea name="workplace_application" oninput="autoExpand(this)"
                                style="width: 100%; border: 1px solid #d1d9e6; border-radius: 8px; padding: 12px; resize: none; min-height: 100px; font-family: inherit; font-size: 1em; outline: none; transition: border-color 0.2s; box-sizing: border-box; overflow: hidden;"
                                placeholder="Describe how you will apply this learning..."></textarea>
                        </div>

                        <!-- Image Upload (Minimized) -->
                        <div id="workplace-image-container"
                            style="display: none; padding: 12px; border: 1px dashed #cbd5e1; border-radius: 8px; text-align: center; background: #f8fafc;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <span style="font-size: 0.85em; color: #64748b; font-weight: 500;">Select Image:</span>
                                <input type="file" name="workplace_image" id="workplace_image" accept="image/*"
                                    style="font-size: 0.8em; color: #475569; max-width: 250px;">
                            </div>
                            <div id="workplace-image-preview" style="margin-top: 8px; display: none;">
                                <img src="" style="max-height: 70px; border-radius: 4px; border: 1px solid #e2e8f0;">
                            </div>
                        </div>

                        <!-- Attestation Section (Clearly Separated) -->
                        <div
                            style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px; display: flex; justify-content: flex-end;">
                            <div style="width: 380px;">
                                <div
                                    style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                                    <div class="form-section-title"
                                        style="text-align: left; border: none; margin-bottom: 0; font-size: 1.1em; color: var(--primary-blue);">
                                        Attestation</div>
                                    <div style="display: flex; gap: 5px;">
                                        <button type="button" class="btn"
                                            style="width: auto; background-color: var(--primary-blue); padding: 5px 12px; font-size: 0.8em; margin: 0;"
                                            onclick="openSignatureModal('attest')">
                                            Draw
                                        </button>
                                        <button type="button" class="btn"
                                            style="width: auto; background-color: #5bc0de; padding: 5px 12px; font-size: 0.8em; margin: 0;"
                                            onclick="triggerSignatureUpload('attest')">
                                            Upload
                                        </button>
                                    </div>
                                </div>

                                <div style="text-align: center; margin-bottom: 10px;">
                                    <span id="attest-sig-status" class="sig-status" style="display: none;">No signature
                                        captured</span>
                                    <div id="attest-upload-status"
                                        style="font-size: 0.85em; color: var(--primary-blue); font-weight: 600; margin-top: 2px;">
                                    </div>
                                    <div id="attest-sig-preview-container" style="margin-top: 10px; display: none;">
                                        <img id="attest-sig-preview" src=""
                                            style="max-height: 80px; border: 1px solid #ccc; border-radius: 4px; padding: 2px;">
                                    </div>
                                    <input type="file" id="sig-file" accept="image/*" style="display: none;">
                                </div>

                                <div class="form-group" style="margin-top: 10px;">
                                    <input type="text" name="approved_by" class="form-control"
                                        placeholder="Name of Immediate Head" style="text-align: center;" required>
                                    <label
                                        style="font-weight: normal; font-size: 0.8em; margin-top: 5px; text-align: center; display: block;">Signature
                                        overprinted name of the Immediate Head</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section-title" style="margin-top: 30px;">Reflection</div>
                        <div class="form-group">
                            <textarea name="reflection" class="form-control"
                                placeholder="Your reflections..."></textarea>
                        </div>

                        <!-- Hidden inputs to store signature data -->
                        <input type="hidden" name="signature_data" id="signature_data">

                        <button type="button" class="btn" onclick="submitForm()" style="margin-top: 20px;">Submit
                            Activity</button>
                </form>

            </div> <!-- End Passbook Container -->
        </div>
    </div>

    <div id="signature-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-title">Draw Signature</div>
            <div style="border: 2px dashed #ccc; display: inline-block;">
                <canvas id="modal-sig-canvas" width="400" height="200"
                    style="background: white; touch-action: none; cursor: crosshair;"></canvas>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn" style="background-color: #777; width: auto;"
                    onclick="clearModalCanvas()">Clear</button>
                <button type="button" class="btn" style="background-color: var(--primary-blue); width: auto;"
                    onclick="saveModalSignature()">Done</button>
                <button type="button" class="btn" style="background-color: #d9534f; width: auto;"
                    onclick="closeSignatureModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Modal Elements
        const modal = document.getElementById('signature-modal');
        const modalCanvas = document.getElementById('modal-sig-canvas');
        const modalCtx = modalCanvas.getContext('2d');
        let currentGroup = ''; // 'org' or 'attest'
        let isDrawing = false;

        // Signature Pad Logic for Modal
        function initModalSignature() {
            // Mouse events
            modalCanvas.addEventListener("mousedown", (e) => { isDrawing = true; modalCtx.moveTo(e.offsetX, e.offsetY); });
            modalCanvas.addEventListener("mouseup", () => { isDrawing = false; modalCtx.beginPath(); });
            modalCanvas.addEventListener("mousemove", (e) => {
                if (isDrawing) {
                    modalCtx.lineTo(e.offsetX, e.offsetY);
                    modalCtx.stroke();
                }
            });

            // Touch events
            modalCanvas.addEventListener("touchstart", (e) => {
                e.preventDefault();
                isDrawing = true;
                const touch = e.touches[0];
                const rect = modalCanvas.getBoundingClientRect();
                modalCtx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
            }, { passive: false });

            modalCanvas.addEventListener("touchend", (e) => {
                e.preventDefault();
                isDrawing = false;
                modalCtx.beginPath();
            }, { passive: false });

            modalCanvas.addEventListener("touchmove", (e) => {
                e.preventDefault();
                if (isDrawing) {
                    const touch = e.touches[0];
                    const rect = modalCanvas.getBoundingClientRect();
                    modalCtx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
                    modalCtx.stroke();
                }
            }, { passive: false });
        }

        function openSignatureModal(group) {
            currentGroup = group;
            modal.style.display = 'flex';
            clearModalCanvas();
            // Re-initialize or ensure context is fresh
            modalCtx.lineWidth = 2;
            modalCtx.lineCap = 'round';
            modalCtx.strokeStyle = '#000';
        }

        function closeSignatureModal() {
            modal.style.display = 'none';
        }

        function clearModalCanvas() {
            modalCtx.clearRect(0, 0, modalCanvas.width, modalCanvas.height);
            modalCtx.beginPath();
        }

        function saveModalSignature() {
            const dataUrl = modalCanvas.toDataURL("image/png");

            if (currentGroup === 'org') {
                document.getElementById('organizer_signature_data').value = dataUrl;
                const status = document.getElementById('org-sig-status');
                status.textContent = '✅ Signature captured';
                status.style.color = 'green';
                status.style.display = 'inline-block';
                // Show Preview
                const previewKv = document.getElementById('org-sig-preview');
                previewKv.src = dataUrl;
                document.getElementById('org-sig-preview-container').style.display = 'block';

                // Clear upload status if drawing
                document.getElementById('org-upload-status').textContent = '';
                document.getElementById('org-sig-file').value = '';
            } else {
                document.getElementById('signature_data').value = dataUrl;
                const status = document.getElementById('attest-sig-status');
                status.textContent = '✅ Signature captured';
                status.style.color = 'green';
                status.style.display = 'inline-block';
                // Show Preview
                const previewKv = document.getElementById('attest-sig-preview');
                previewKv.src = dataUrl;
                document.getElementById('attest-sig-preview-container').style.display = 'block';

                // Clear upload status if drawing
                document.getElementById('attest-upload-status').textContent = '';
                document.getElementById('sig-file').value = '';
            }

            closeSignatureModal();
        }

        function submitForm() {
            var form = document.querySelector('form');

            // Basic field validation
            const title = form.querySelector('[name="title"]').value.trim();
            const date = form.querySelector('[name="date_attended"]').value.trim();

            if (!title || !date) {
                showToast("Required Fields", "Please fill in the Activity Title and Date.", "error");
                return;
            }

            // --- Workplace Application Validation ---
            const workplaceText = form.querySelector('[name="workplace_application"]').value.trim();
            const workplaceFile = document.getElementById('workplace_image');
            const method = form.querySelector('[name="workplace_method"]:checked').value;

            let hasWorkplace = false;
            // Check based on active method, but accept either if data exists
            if (workplaceText !== '' || workplaceFile.files.length > 0) {
                hasWorkplace = true;
            }

            if (!hasWorkplace) {
                showToast("Mandatory Input", "Please provide either a Text description or an Image for the Workplace Application.", "error");
                return;
            }

            // --- Attestation Validation ---
            const attestData = document.getElementById('signature_data').value;
            const attestFile = document.getElementById('sig-file');

            if (!attestData && attestFile.files.length === 0) {
                showToast("Missing Signature", "Attestation is required. Please draw or upload a signature.", "error");
                return;
            }

            // --- Handle Organizer Signature logic (existing) ---
            const orgData = document.getElementById('organizer_signature_data').value;
            const orgFile = document.getElementById('org-sig-file');

            if (!orgData && orgFile.files.length > 0) {
                const clone = orgFile.cloneNode(true);
                clone.name = 'organizer_signature_file';
                clone.style.display = 'none';
                form.appendChild(clone);
            }

            // --- Handle Attestation Signature logic (existing) ---
            if (!attestData && attestFile.files.length > 0) {
                const clone = attestFile.cloneNode(true);
                clone.name = 'signature_file';
                clone.style.display = 'none';
                form.appendChild(clone);
            }

            form.submit();
        }

        // Auto-expand Textarea Height
        function autoExpand(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        // Workplace Application Method Toggle
        function toggleWorkplaceMethod(method) {
            const textContainer = document.getElementById('workplace-text-container');
            const imageContainer = document.getElementById('workplace-image-container');

            if (method === 'text') {
                textContainer.style.display = 'block';
                imageContainer.style.display = 'none';
            } else {
                textContainer.style.display = 'none';
                imageContainer.style.display = 'block';
            }
        }

        // Image Selection Preview for Workplace Application
        const workplaceImageInput = document.getElementById('workplace_image');
        if (workplaceImageInput) {
            workplaceImageInput.addEventListener('change', function () {
                const preview = document.getElementById('workplace-image-preview');
                const previewImg = preview.querySelector('img');

                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        previewImg.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(this.files[0]);
                } else {
                    preview.style.display = 'none';
                }
            });
        }

        // Initialize Modal Pad
        initModalSignature();

        // Legacy/Utility support
        function openTab(id) { /* No longer used with dual buttons */ }

        // Conditional Visibility for Organizer Signature Pad
        const conductedByInput = document.getElementById('conducted_by');
        const organizerSigSection = document.getElementById('organizer-signature-section');

        if (conductedByInput && organizerSigSection) {
            conductedByInput.addEventListener('input', function () {
                if (this.value.trim() !== '') {
                    organizerSigSection.style.display = 'block';
                } else {
                    organizerSigSection.style.display = 'none';
                }
            });
        }

        // Trigger File Upload Directly
        function triggerSignatureUpload(group) {
            const fileId = group === 'org' ? 'org-sig-file' : 'sig-file';
            const statusId = group === 'org' ? 'org-upload-status' : 'attest-upload-status';
            const drawStatusId = group === 'org' ? 'org-sig-status' : 'attest-sig-status';
            const drawDataId = group === 'org' ? 'organizer_signature_data' : 'signature_data';
            const previewContainerId = group === 'org' ? 'org-sig-preview-container' : 'attest-sig-preview-container';
            const previewImgId = group === 'org' ? 'org-sig-preview' : 'attest-sig-preview';

            // Trigger the hidden file input
            const input = document.getElementById(fileId);
            input.click();

            // Handle file selection change
            input.onchange = function () {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    document.getElementById(statusId).textContent = "✅ Selected: " + file.name;
                    document.getElementById(statusId).style.display = "block";
                    
                    // Show Preview via FileReader
                    const reader = new FileReader();
                    reader.onload = function(e) {
                         document.getElementById(previewImgId).src = e.target.result;
                         document.getElementById(previewContainerId).style.display = 'block';
                    };
                    reader.readAsDataURL(file);

                    // Clear draw status if uploading
                    const drawStatus = document.getElementById(drawStatusId);
                    drawStatus.textContent = "No signature captured";
                    drawStatus.style.display = "none";
                    document.getElementById(drawDataId).value = "";
                }
            };
        }

        // Others Type specification
        const typeOthersCheckbox = document.getElementById('type-others-checkbox');
        const typeOthersContainer = document.getElementById('type-others-input-container');

        if (typeOthersCheckbox) {
            typeOthersCheckbox.addEventListener('change', function () {
                typeOthersContainer.style.display = this.checked ? 'block' : 'none';
            });
        }
    </script>
</body>

</html>