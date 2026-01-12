<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$activity_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$activity_id) {
    die("Invalid Activity ID");
}

// Fetch Activity Data
$stmt = $pdo->prepare("SELECT * FROM ld_activities WHERE id = ?");
$stmt->execute([$activity_id]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

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

    // Function to handle file saving (supports 1 or many files)
    function saveUpload($fileKey, $prefix, $subDir = 'signatures')
    {
        if (!isset($_FILES[$fileKey]) || empty($_FILES[$fileKey]['name'][0]))
            return null;

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
                $fileName = uniqid() . '_' . $prefix . '_' . basename($originalName);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $paths[] = 'uploads/' . $subDir . '/' . $fileName;
                }
            }
        }
        if (empty($paths))
            return null;
        return $isMultiple ? json_encode($paths) : $paths[0];
    }

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
            if (!is_dir(dirname($filePath)))
                mkdir(dirname($filePath), 0777, true);
            if (file_put_contents($filePath, $decodedData))
                return 'uploads/signatures/' . $fileName;
        }
        return null;
    }

    $new_sig = saveSignature('signature_file', 'signature_data', 'attest');
    $new_org_sig = saveSignature('organizer_signature_file', 'organizer_signature_data', 'org');
    $new_work_images = saveUpload('workplace_image', 'work', 'workplace');

    $sig_path = $new_sig ?: $activity['signature_path'];
    $org_sig_path = $new_org_sig ?: $activity['organizer_signature_path'];
    $work_image_path = $new_work_images ?: $activity['workplace_image_path'];

    $sql = "UPDATE ld_activities SET 
            title = ?, date_attended = ?, venue = ?, modality = ?, competency = ?, 
            type_ld = ?, type_ld_others = ?, conducted_by = ?, organizer_signature_path = ?, 
            approved_by = ?, workplace_application = ?, workplace_image_path = ?, 
            reflection = ?, signature_path = ? 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if (
        $stmt->execute([
            $title,
            $date_attended,
            $venue,
            $modality,
            $competency,
            $type_ld,
            $type_ld_others,
            $conducted_by,
            $org_sig_path,
            $approved_by,
            $workplace_application,
            $work_image_path,
            $reflection,
            $sig_path,
            $activity_id
        ])
    ) {
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$_SESSION['user_id'], 'Updated Activity', "Activity ID: $activity_id, Title: $title", $_SERVER['REMOTE_ADDR']]);

        $message = "Activity updated successfully!";
        $messageType = "success";

        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM ld_activities WHERE id = ?");
        $stmt->execute([$activity_id]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $message = "Error updating activity.";
        $messageType = "error";
    }
}

// Helper for checkboxes
function isChecked($value, $storedValue)
{
    if (empty($storedValue))
        return '';
    $arr = explode(', ', $storedValue);
    return in_array($value, $arr) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Activity - LDP</title>
    <?php require '../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/pages/add-activity.css">
    <link rel="stylesheet" href="../css/pages/passbook-view.css">
    <style>
        .existing-file-preview {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <?php require '../includes/sidebar.php'; ?>
        </div>
        <div class="main-content">
            <div class="passbook-container" style="width: 800px;">
                <div class="header">
                    <h1>Edit Activity</h1>
                    <p>
                        <?php echo htmlspecialchars($activity['title']); ?>
                    </p>
                </div>

                <!-- Progress Tracker -->
                <div class="progress-tracker" style="margin-top: 20px; background: white; border: 1px solid #e2e8f0;">
                    <div class="progress-step <?php echo $activity['reviewed_by_supervisor'] ? 'active' : ''; ?>">
                        <div class="step-icon">
                            <?php echo $activity['reviewed_by_supervisor'] ? '✓' : '1'; ?>
                        </div>
                        <div class="step-label">Reviewed</div>
                    </div>
                    <div class="progress-step <?php echo $activity['recommending_asds'] ? 'active' : ''; ?>">
                        <div class="step-icon">
                            <?php echo $activity['recommending_asds'] ? '✓' : '2'; ?>
                        </div>
                        <div class="step-label">Recommending</div>
                    </div>
                    <div class="progress-step <?php echo $activity['approved_sds'] ? 'active' : ''; ?>">
                        <div class="step-icon">
                            <?php echo $activity['approved_sds'] ? '✓' : '3'; ?>
                        </div>
                        <div class="step-label">Approved</div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <script>
                        window.addEventListener('DOMContentLoaded', function () {
                            showToast("<?php echo ($messageType === 'success') ? 'Success' : 'Error'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
                        });
                    </script>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-section-title">L&D Activity Details</div>

                    <div class="form-group">
                        <label>Title of L&D Activity:</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($activity['title']); ?>"
                            class="form-control" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Date:</label>
                            <input type="date" name="date_attended" value="<?php echo $activity['date_attended']; ?>"
                                class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Venue:</label>
                            <input type="text" name="venue" value="<?php echo htmlspecialchars($activity['venue']); ?>"
                                class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Competency: <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="competency"
                                value="<?php echo htmlspecialchars($activity['competency']); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Conducted by:</label>
                            <input type="text" name="conducted_by"
                                value="<?php echo htmlspecialchars($activity['conducted_by']); ?>" class="form-control"
                                required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Modality: <span style="color: #ef4444;">*</span></label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="modality[]" value="Formal Training" <?php echo isChecked('Formal Training', $activity['modality']); ?>> Formal Training</label>
                                <label><input type="checkbox" name="modality[]" value="Job-Embedded Learning" <?php echo isChecked('Job-Embedded Learning', $activity['modality']); ?>> Job-Embedded
                                    Learning</label>
                                <label><input type="checkbox" name="modality[]" value="Relationship Discussion Learning"
                                        <?php echo isChecked('Relationship Discussion Learning', $activity['modality']); ?>> Relationship Discussion Learning</label>
                                <label><input type="checkbox" name="modality[]" value="Learning Action Cell" <?php echo isChecked('Learning Action Cell', $activity['modality']); ?>> Learning Action
                                    Cell</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Type of L&D: <span style="color: #ef4444;">*</span></label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="type_ld[]" value="Supervisory" <?php echo isChecked('Supervisory', $activity['type_ld']); ?>> Supervisory</label>
                                <label><input type="checkbox" name="type_ld[]" value="Managerial" <?php echo isChecked('Managerial', $activity['type_ld']); ?>> Managerial</label>
                                <label><input type="checkbox" name="type_ld[]" value="Technical" <?php echo isChecked('Technical', $activity['type_ld']); ?>> Technical</label>
                                <label><input type="checkbox" name="type_ld[]" value="Others" <?php echo isChecked('Others', $activity['type_ld']); ?>> Others</label>
                            </div>
                            <input type="text" name="type_ld_others"
                                value="<?php echo htmlspecialchars($activity['type_ld_others']); ?>"
                                class="form-control" style="margin-top: 5px; font-size: 0.8em;"
                                placeholder="Specify if others">
                        </div>
                    </div>

                    <div class="form-section-title" style="margin-top: 30px;">Workplace Application</div>
                    <textarea name="workplace_application" class="form-control"
                        style="min-height: 100px;"><?php echo htmlspecialchars($activity['workplace_application']); ?></textarea>

                    <div style="margin-top: 15px;">
                        <label style="font-size: 0.85em; font-weight: 600; color: #64748b;">Update Attachments (Leave
                            empty to keep current):</label>
                        <input type="file" name="workplace_image[]" class="form-control" multiple>
                    </div>

                    <div class="form-section-title" style="margin-top: 30px;">Reflection</div>
                    <textarea name="reflection" class="form-control"
                        style="min-height: 100px;"><?php echo htmlspecialchars($activity['reflection']); ?></textarea>

                    <div style="margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                        <div class="form-group">
                            <label>Immediate Head:</label>
                            <input type="text" name="approved_by"
                                value="<?php echo htmlspecialchars($activity['approved_by']); ?>" class="form-control"
                                required>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" class="btn" style="flex: 1;">Update Activity</button>
                        <a href="view_activity.php?id=<?php echo $activity_id; ?>" class="btn"
                            style="flex: 1; background: #64748b; text-decoration: none; text-align: center; color: white; display: flex; align-items: center; justify-content: center;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>