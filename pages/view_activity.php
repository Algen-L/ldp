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
// Allow Admins to see ANY activity.
// Allow Regular Users to see ONLY their own activity.
$sql = "SELECT ld.*, u.full_name, u.office_station, u.profile_picture FROM ld_activities ld JOIN users u ON ld.user_id = u.id WHERE ld.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$activity_id]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    die("Activity not found.");
}

// Access Control
if ($_SESSION['role'] !== 'admin' && $activity['user_id'] != $_SESSION['user_id']) {
    die("Unauthorized access.");
}

// Log View Activity
$view_action = "Viewed Specific Activity: " . $activity['title'];
// Check if already logged in this session/window to avoid double logs on refresh? 
// For now, simple log is fine as requested.
$stmt_log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'Viewed Specific Activity', ?, ?)");
$stmt_log->execute([$_SESSION['user_id'], $activity['title'], $_SERVER['REMOTE_ADDR']]);

// Update Status to 'Viewed' if it is 'Pending' and user is Admin
if ($_SESSION['role'] === 'admin' && $activity['status'] === 'Pending') {
    $stmt_update = $pdo->prepare("UPDATE ld_activities SET status = 'Viewed' WHERE id = ?");
    $stmt_update->execute([$activity_id]);

    // Refresh the $activity variable to reflect the change visually if needed (optional)
    $activity['status'] = 'Viewed';
}

// Handle Admin Approval Actions
if ($_SESSION['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_approval'])) {
    $stage = $_POST['stage']; // supervisor, asds, sds
    $current_time = date('Y-m-d H:i:s');

    if ($stage === 'supervisor') {
        $stmt = $pdo->prepare("UPDATE ld_activities SET reviewed_by_supervisor = 1, reviewed_at = ? WHERE id = ?");
        $stmt->execute([$current_time, $activity_id]);
    } elseif ($stage === 'asds') {
        $stmt = $pdo->prepare("UPDATE ld_activities SET recommending_asds = 1, recommended_at = ? WHERE id = ?");
        $stmt->execute([$current_time, $activity_id]);
    } elseif ($stage === 'sds') {
        $stmt = $pdo->prepare("UPDATE ld_activities SET approved_sds = 1, approved_at = ?, status = 'Approved' WHERE id = ?");
        $stmt->execute([$current_time, $activity_id]);
    }

    // Refresh activity data
    $stmt = $pdo->prepare("SELECT ld.*, u.full_name, u.office_station FROM ld_activities ld JOIN users u ON ld.user_id = u.id WHERE ld.id = ?");
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
    <title>View Activity - LDP</title>
    <?php require '../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/pages/passbook-view.css">
</head>

<body>

    <div class="dashboard-container">
        <div class="sidebar">
            <?php require '../includes/sidebar.php'; ?>
        </div>

        <div class="main-content">
            <div class="passbook-container" style="width: 800px;">
                <div class="header" style="position: relative;">
                    <h1>Learning & Development Passbook</h1>
                    <p>View Activity Details</p>
                    
                </div>

                <!-- Progress Tracker -->
                <div class="progress-tracker">
                    <div class="progress-step <?php echo $activity['reviewed_by_supervisor'] ? 'active' : ''; ?>">
                        <div class="step-icon"><?php echo $activity['reviewed_by_supervisor'] ? '✓' : '1'; ?></div>
                        <div class="step-label">Reviewed</div>
                        <span class="step-date"><?php echo $activity['reviewed_at'] ? date('M d, Y', strtotime($activity['reviewed_at'])) : 'Pending'; ?></span>
                    </div>
                    <div class="progress-step <?php echo $activity['recommending_asds'] ? 'active' : ''; ?>">
                        <div class="step-icon"><?php echo $activity['recommending_asds'] ? '✓' : '2'; ?></div>
                        <div class="step-label">Recommending</div>
                        <span class="step-date"><?php echo $activity['recommended_at'] ? date('M d, Y', strtotime($activity['recommended_at'])) : 'Pending'; ?></span>
                    </div>
                    <div class="progress-step <?php echo $activity['approved_sds'] ? 'active' : ''; ?>">
                        <div class="step-icon"><?php echo $activity['approved_sds'] ? '✓' : '3'; ?></div>
                        <div class="step-label">Approved</div>
                        <span class="step-date"><?php echo $activity['approved_at'] ? date('M d, Y', strtotime($activity['approved_at'])) : 'Pending'; ?></span>
                    </div>
                </div>

                <!-- Admin Approval Panel -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="admin-approval-panel">
                        <div style="font-weight: 700; color: #1e40af; margin-bottom: 15px; font-size: 0.9em; text-transform: uppercase;">
                            Admin Approval Controls
                        </div>
                        <div class="approval-grid">
                            <form method="POST">
                                <input type="hidden" name="action_approval" value="1">
                                <input type="hidden" name="stage" value="supervisor">
                                <button type="submit" class="approval-btn <?php echo $activity['reviewed_by_supervisor'] ? 'approved' : 'pending'; ?>" 
                                    <?php echo $activity['reviewed_by_supervisor'] ? 'disabled' : ''; ?>>
                                    <?php echo $activity['reviewed_by_supervisor'] ? '✓ Reviewed' : 'Mark as Reviewed'; ?>
                                </button>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="action_approval" value="1">
                                <input type="hidden" name="stage" value="asds">
                                <button type="submit" class="approval-btn <?php echo $activity['recommending_asds'] ? 'approved' : 'pending'; ?>"
                                    <?php echo $activity['recommending_asds'] ? 'disabled' : ''; ?>
                                    <?php echo !$activity['reviewed_by_supervisor'] ? 'style="opacity: 0.5; cursor: not-allowed;" title="Must be reviewed first" disabled' : ''; ?>>
                                    <?php echo $activity['recommending_asds'] ? '✓ Recommended' : 'Mark as Recommended'; ?>
                                </button>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="action_approval" value="1">
                                <input type="hidden" name="stage" value="sds">
                                <button type="submit" class="approval-btn <?php echo $activity['approved_sds'] ? 'approved' : 'pending'; ?>"
                                    <?php echo $activity['approved_sds'] ? 'disabled' : ''; ?>
                                    <?php echo !$activity['recommending_asds'] ? 'style="opacity: 0.5; cursor: not-allowed;" title="Must be recommended first" disabled' : ''; ?>>
                                    <?php echo $activity['approved_sds'] ? '✓ Approved' : 'Mark as Approved'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="submitter-card">
                    <div class="submitter-avatar">
                        <?php if (!empty($activity['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($activity['profile_picture']); ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">
                        <?php else: ?>
                            <?php
                            $initials = "";
                            $names = explode(" ", $activity['full_name']);
                            foreach ($names as $n)
                                $initials .= strtoupper(substr($n, 0, 1));
                            echo substr($initials, 0, 2);
                            ?>
                        <?php endif; ?>
                    </div>
                    <div class="submitter-details">
                        <span class="submitter-label">Passbook Holder</span>
                        <h3 class="submitter-name"><?php echo htmlspecialchars($activity['full_name']); ?></h3>
                        <div class="submitter-meta">
                            <span class="meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                <?php echo htmlspecialchars($activity['office_station']); ?>
                            </span>
                            <span class="meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                Submitted on <?php echo date('F d, Y', strtotime($activity['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <form>
                    <!-- Removed method/action to prevent submission -->

                    <div class="form-section-title">Learning and Development Attended</div>

                    <div class="form-group">
                        <label>Title of L&D Activity:</label>
                        <input type="text" class="form-control"
                            value="<?php echo htmlspecialchars($activity['title']); ?>" readonly>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Date:</label>
                            <input type="text" class="form-control"
                                value="<?php echo htmlspecialchars($activity['date_attended']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Venue:</label>
                            <input type="text" class="form-control"
                                value="<?php echo htmlspecialchars($activity['venue']); ?>" readonly>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div>
                            <div class="form-group">
                                <label>Addressed Competency/ies:</label>
                                <input type="text" class="form-control"
                                    value="<?php echo htmlspecialchars($activity['competency']); ?>" readonly>
                            </div>
                            <div class="form-group" style="margin-top: 15px;">
                                <label>Conducted by:</label>
                                <input type="text" class="form-control"
                                    value="<?php echo htmlspecialchars($activity['conducted_by']); ?>" readonly>
                            </div>
                            <!-- Organizer Signature -->
                            <?php if (!empty($activity['organizer_signature_path'])): ?>
                                        <div style="text-align: center; margin-top: 15px;">
                                            <label
                                                style="font-size: 0.85em; color: var(--primary-blue); font-weight: 600; display: block; margin-bottom: 5px;">Organizer's
                                                Signature:</label>
                                            <div
                                                style="border: 2px solid #e2e8f0; border-radius: 8px; display: inline-block; width: 100%; height: 160px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: white; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                                                <img src="../<?php echo htmlspecialchars($activity['organizer_signature_path']); ?>"
                                                    style="max-width: 90%; max-height: 90%; object-fit: contain;">
                                            </div>
                                        </div>
                            <?php endif; ?>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Modality:</label>
                                <div class="checkbox-group"
                                    style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    <label><input type="checkbox" disabled <?php echo isChecked('Formal Training', $activity['modality']); ?>> Formal Training</label>
                                    <label><input type="checkbox" disabled <?php echo isChecked('Job-Embedded Learning', $activity['modality']); ?>> Job-Embedded Learning</label>
                                    <label><input type="checkbox" disabled <?php echo isChecked('Relationship Discussion Learning', $activity['modality']); ?>> Relationship Discussion</label>
                                    <label><input type="checkbox" disabled <?php echo isChecked('Learning Action Cell', $activity['modality']); ?>> Learning Action Cell</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Type of L&D:</label>
                                <div class="checkbox-group"
                                    style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    <label><input type="checkbox" disabled <?php echo isChecked('Supervisory', $activity['type_ld']); ?>> Supervisory</label>
                                    <label><input type="checkbox" disabled <?php echo isChecked('Managerial', $activity['type_ld']); ?>> Managerial</label>
                                    <label><input type="checkbox" disabled <?php echo isChecked('Technical', $activity['type_ld']); ?>> Technical</label>
                                    <label><input type="checkbox" disabled <?php echo isChecked('Others', $activity['type_ld']); ?>> Others</label>
                                    <?php if (!empty($activity['type_ld_others'])): ?>
                                                <div style="font-size: 0.85em; color: #555; margin-left: 20px; font-style: italic;">
                                                    (<?php echo htmlspecialchars($activity['type_ld_others']); ?>)
                                                </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="form-section-title print-break-before" style="margin-top: 30px;">Workplace Application
                    </div>

                    <div class="workplace-box"
                        style="border: 2px solid var(--primary-blue); padding: 20px; border-radius: 5px; min-height: 100px; display: flex; flex-direction: column; position: relative;">
                        <?php if (!empty($activity['workplace_application'])): ?>
                                    <!-- Text Area -->
                                    <textarea class="form-control" id="workplace-textarea"
                                        style="width: 100%; border: none; resize: none; min-height: 20px; font-family: inherit; font-size: 1em; outline: none; flex: 1; box-sizing: border-box; overflow: hidden; background: #fdfdfd; padding: 10px; border-radius: 8px;"
                                        readonly><?php echo htmlspecialchars($activity['workplace_application']); ?></textarea>
                                    <script>
                                        window.addEventListener('DOMContentLoaded', function () {
                                            const tx = document.getElementById('workplace-textarea');
                                            if (tx) {
                                                tx.style.height = 'auto';
                                                tx.style.height = tx.scrollHeight + 'px';
                                            }
                                        });
                                    </script>
                        <?php endif; ?>

                        <?php if (!empty($activity['workplace_application']) && !empty($activity['workplace_image_path'])): ?>
                                    <div style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>
                        <?php endif; ?>

                        <?php
                        if (!empty($activity['workplace_image_path'])):
                            $paths = [];
                            $isJson = false;

                            // Check if it's a JSON array or single path
                            $trimmed = trim($activity['workplace_image_path']);
                            if (strpos($trimmed, '[') === 0) {
                                $decoded = json_decode($trimmed, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $paths = $decoded;
                                    $isJson = true;
                                } else {
                                    // It looks like JSON but is broken (likely truncated)
                                    $paths = [];
                                }
                            } else {
                                $paths = [$activity['workplace_image_path']];
                            }
                            ?>
                                    <div style="padding: 10px;">
                                        <div
                                            style="font-size: 0.8em; color: #64748b; margin-bottom: 12px; font-weight: 600; text-transform: uppercase; text-align: center;">
                                            <?php echo $isJson ? "Attached Files (" . count($paths) . ")" : "Attached Image"; ?>
                                        </div>
                                        <?php if (empty($paths) && !empty($activity['workplace_image_path'])): ?>
                                                    <p style="text-align: center; color: #ef4444; font-size: 0.9em; font-style: italic;">
                                                        Error: Attachment data is corrupted/truncated. Please re-upload.
                                                    </p>
                                        <?php endif; ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center;">
                                            <?php foreach ($paths as $path):
                                                if (empty($path))
                                                    continue;
                                                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif']);
                                                $fileName = basename($path);
                                                $shortName = strlen($fileName) > 20 ? substr($fileName, 0, 17) . '...' : $fileName;
                                                ?>
                                                        <div style="text-align: center; width: 140px;">
                                                            <?php if ($isImage): ?>
                                                                        <a href="../<?php echo htmlspecialchars($path); ?>" target="_blank"
                                                                            style="text-decoration: none;">
                                                                            <img src="../<?php echo htmlspecialchars($path); ?>"
                                                                                style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
                                                                        </a>
                                                            <?php else: ?>
                                                                        <a href="../<?php echo htmlspecialchars($path); ?>" target="_blank"
                                                                            style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 120px; height: 120px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: #475569; position: relative; margin: 0 auto;">
                                                                            <svg style="width: 48px; height: 48px; color: #3b82f6;" fill="none"
                                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                                                </path>
                                                                            </svg>
                                                                            <span
                                                                                style="font-size: 10px; font-weight: 600; color: #2563eb; background: #dbeafe; padding: 2px 6px; border-radius: 4px; margin-top: 5px;"><?php echo strtoupper($extension); ?></span>
                                                                        </a>
                                                            <?php endif; ?>
                                                            <div style="font-size: 11px; color: #64748b; margin-top: 5px; word-break: break-all; line-height: 1.2;"
                                                                title="<?php echo htmlspecialchars($fileName); ?>">
                                                                <?php echo htmlspecialchars($shortName); ?>
                                                            </div>
                                                        </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                        <?php endif; ?>

                        <?php if (empty($activity['workplace_application']) && empty($activity['workplace_image_path'])): ?>
                                    <p style="text-align: center; color: #94a3b8; font-style: italic; margin: 20px 0;">No workplace
                                        application provided.</p>
                        <?php endif; ?>

                        <!-- Attestation Section (Clearly Separated) -->
                        <div
                            style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px; display: flex; justify-content: flex-end;">
                            <div style="width: 350px; text-align: center;">
                                <div class="form-section-title"
                                    style="text-align: center; border: none; margin-bottom: 5px; font-size: 1.1em; color: var(--primary-blue);">
                                    Attestation</div>

                                <!-- Display Signature Image if available -->
                                <div
                                    style="border: 2px solid #e2e8f0; border-radius: 8px; display: inline-block; width: 100%; height: 160px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: 0 auto; background: white; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                                    <?php if (!empty($activity['signature_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($activity['signature_path']); ?>"
                                                    style="max-width: 90%; max-height: 90%; object-fit: contain;">
                                    <?php else: ?>
                                                <span style="color: #cbd5e1;">No Signature</span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group" style="margin-top: 10px;">
                                    <input type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($activity['approved_by']); ?>"
                                        style="text-align: center;" readonly>
                                    <label
                                        style="font-weight: normal; font-size: 0.8em; margin-top: 5px; text-align: center; display: block;">Signature
                                        overprinted name of the Immediate Head</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section-title" style="margin-top: 30px;">Reflection</div>
                        <div class="form-group" style="margin-bottom: 30px;">
                            <textarea class="form-control" readonly
                                style="min-height: 120px;"><?php echo htmlspecialchars($activity['reflection']); ?></textarea>
                        </div>

                        <div
                            style="margin-top: 40px; padding-bottom: 20px; border-top: 1px solid #eee; text-align: center;">
                            <button type="button" class="btn" onclick="window.print()"
                                style="width: auto; min-width: 250px; background-color: #17a2b8; margin-right: 10px;">Print
                                Activity</button>

                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <a href="../admin/dashboard.php" class="btn"
                                            style="width: auto; min-width: 250px; text-align: center; display: inline-block; text-decoration: none;">Back
                                            to
                                            Dashboard</a>
                            <?php else: ?>
                                        <a href="home.php" class="btn"
                                            style="width: auto; min-width: 250px; text-align: center; display: inline-block; text-decoration: none;">Back
                                            to
                                            Dashboard</a>
                            <?php endif; ?>
                        </div>
                </form>

            </div> <!-- End Passbook Container -->
        </div>
    </div>

</body>

</html>