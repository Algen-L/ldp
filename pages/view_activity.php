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
$sql = "SELECT ld.*, u.full_name, u.office_station FROM ld_activities ld JOIN users u ON ld.user_id = u.id WHERE ld.id = ?";
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
                <div class="header">
                    <h1>Learning & Development Passbook</h1>
                    <p>View Activity Details</p>
                </div>

                <div class="submitter-info">
                    <div class="submitter-avatar">
                        <?php
                        $initials = "";
                        $names = explode(" ", $activity['full_name']);
                        foreach ($names as $n)
                            $initials .= strtoupper(substr($n, 0, 1));
                        echo substr($initials, 0, 2);
                        ?>
                    </div>
                    <div class="submitter-details">
                        <span>Submitted By</span>
                        <h3><?php echo htmlspecialchars($activity['full_name']); ?></h3>
                        <p><?php echo htmlspecialchars($activity['office_station']); ?></p>
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


                    <div class="form-section-title" style="margin-top: 30px;">Workplace Application</div>

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

                        <?php if (!empty($activity['workplace_image_path'])): ?>
                            <div style="text-align: center; padding: 10px;">
                                <div
                                    style="font-size: 0.8em; color: #64748b; margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">
                                    Attached Image</div>
                                <img src="../<?php echo htmlspecialchars($activity['workplace_image_path']); ?>"
                                    style="max-width: 100%; max-height: 400px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
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