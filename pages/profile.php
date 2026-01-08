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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station']);
    $position = trim($_POST['position']);
    $rating_period = trim($_POST['rating_period']);
    $area_of_specialization = trim($_POST['area_of_specialization']);
    $age = (int) $_POST['age'];
    $sex = trim($_POST['sex']);
    $password = trim($_POST['password']);

    if (empty($full_name)) {
        $message = "Name is required.";
        $messageType = "error";
    } else {
        // Prepare update query
        $sql = "UPDATE users SET full_name = ?, office_station = ?, position = ?, rating_period = ?, area_of_specialization = ?, age = ?, sex = ?";
        $params = [$full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex];

        // Update password if provided
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $hashed_password;
        }

        // Handle Profile Picture Upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profile_pics/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                $dbPath = 'uploads/profile_pics/' . $fileName;
                $sql .= ", profile_picture = ?";
                $params[] = $dbPath;
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $_SESSION['user_id'];

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            // Log profile update
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Updated Profile', $_SERVER['REMOTE_ADDR']]);

            $message = "Profile updated successfully.";
            $messageType = "success";
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['full_name'] = $user['full_name'];
        } else {
            $message = "Error updating profile.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - LDP Passbook</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/passbook.css">
</head>

<body>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php require '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="passbook-container">
                <div class="header">
                    <h1>My Profile</h1>
                    <p>Update your profile information</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="card-grid">
                        <div class="photo-section">
                            <div class="photo-box">
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <img id="preview" src="../<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                        alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <img id="preview" src="../uploads/profile_pics/default.png" alt="Profile"
                                        style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                    <span id="placeholder-text"
                                        style="<?php echo !empty($user['profile_picture']) ? 'display:none;' : ''; ?>">Photo</span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group" style="margin-top: 10px;">
                                <input type="file" name="profile_picture" class="form-control" accept="image/*"
                                    onchange="previewImage(this)">
                            </div>
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="full_name" class="form-control" required
                                    value="<?php echo htmlspecialchars($user['full_name']); ?>">
                            </div>
                            <div class="form-group" style="margin-top: 20px;">
                                <label>Change Password</label>
                                <input type="password" name="password" class="form-control"
                                    placeholder="Leave blank to keep current">
                            </div>
                        </div>

                        <div class="details-section">
                            <div class="form-group">
                                <label>Office/ Station</label>
                                <input type="text" name="office_station" class="form-control"
                                    value="<?php echo htmlspecialchars($user['office_station']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" name="position" class="form-control"
                                    value="<?php echo htmlspecialchars($user['position']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Rating Period</label>
                                <input type="text" name="rating_period" class="form-control"
                                    value="<?php echo htmlspecialchars($user['rating_period']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Area of Specialization</label>
                                <input type="text" name="area_of_specialization" class="form-control"
                                    value="<?php echo htmlspecialchars($user['area_of_specialization']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Age & Sex</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="number" name="age" class="form-control" placeholder="Age"
                                        style="width: 30%;" value="<?php echo htmlspecialchars($user['age']); ?>">
                                    <select name="sex" class="form-control">
                                        <option value="">Select Sex</option>
                                        <option value="Male" <?php if ($user['sex'] == 'Male')
                                            echo 'selected'; ?>>Male
                                        </option>
                                        <option value="Female" <?php if ($user['sex'] == 'Female')
                                            echo 'selected'; ?>>
                                            Female</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var img = document.getElementById('preview');
                    img.src = e.target.result;
                    img.style.display = 'block';

                    var placeholder = document.getElementById('placeholder-text');
                    if (placeholder) placeholder.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>