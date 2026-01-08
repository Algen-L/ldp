<?php
require 'includes/db.php';

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station']);
    $position = trim($_POST['position']);
    $rating_period = trim($_POST['rating_period']);
    $area_of_specialization = trim($_POST['area_of_specialization']);
    $age = (int) $_POST['age'];
    $sex = trim($_POST['sex']);

    // Basic validation
    if (empty($username) || empty($password) || empty($full_name)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $message = "Username already exists.";
            $messageType = "error";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $sql = "INSERT INTO users (username, password, full_name, office_station, position, rating_period, area_of_specialization, age, sex) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$username, $hashed_password, $full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex])) {
                $message = "Registration successful! <a href='index.php'>Login here</a>";
                $messageType = "success";
            } else {
                $message = "Something went wrong. Please try again.";
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
    <title>Register - LDP Passbook</title>
    <?php require 'includes/head.php'; ?>
    <link rel="stylesheet" href="css/pages/auth.css">
</head>

<body class="auth-page">

    <div class="passbook-container" style="margin-top: 50px; margin-bottom: 50px;">
        <div class="header">
            <h1>Learning & Development Passbook</h1>
            <p>Register your account</p>
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
                        <img id="preview" src="uploads/profile_pics/default.png" alt="Profile"
                            style="width: 100%; height: 100%; object-fit: cover; display: none;">
                        <span id="placeholder-text">Photo</span>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <input type="file" name="profile_picture" class="form-control" accept="image/*"
                            onchange="previewImage(this)">
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Full Name">
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Login Credentials</label>
                        <input type="text" name="username" class="form-control" placeholder="Username" required
                            style="margin-bottom: 10px;">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                </div>

                <div class="details-section">
                    <div class="form-group">
                        <label>Office/ Station</label>
                        <input type="text" name="office_station" class="form-control" list="office_list"
                            placeholder="Type to search...">
                        <datalist id="office_list">
                            <option value="ADMINISTRATIVE (PERSONEL)">
                            <option value="ADMINISTRATIVE (PROPERTY AND SUPPLY)">
                            <option value="ADMINISTRATIVE (RECORDS)">
                            <option value="ADMINISTRATIVE (CASH)">
                            <option value="ADMINISTRATIVE (GENERAL SERVICES)">
                            <option value="FINANCE (ACCOUNTING)">
                            <option value="FINANCE (BUDGET)">
                            <option value="LEGAL">
                            <option value="ICT">
                            <option value="SCHOOL GOVERNANCE AND OPERATION DIVISION">
                            <option value="SCHOOL MANAGEMENT MONITORING & EVALUATION">
                            <option value="HUMAN RESOURCES DEVELOPMENT">
                            <option value="DISASTER RISK REDUCTION AND MANAGEMENT">
                            <option value="EDUCATION FACILITIES">
                            <option value="SCHOOL HEALTH AND NUTRITION">
                            <option value="SCHOOL HEALTH AND NUTRITION (DENTAL)">
                            <option value="SCHOOL HEALTH AND NUTRITION (MEDICAL)">
                            <option value="CURRICULUM IMPLEMENTATION DIVISION">
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)">
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)">
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)">
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Rating Period</label>
                        <input type="text" name="rating_period" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Area of Specialization</label>
                        <input type="text" name="area_of_specialization" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Age & Sex</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="number" name="age" class="form-control" placeholder="Age" style="width: 30%;">
                            <select name="sex" class="form-control">
                                <option value="">Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn">Register</button>
            <div class="link-text">
                Already have an account? <a href="index.php">Login</a>
            </div>
        </form>

        <script>
            function previewImage(input) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        document.getElementById('preview').src = e.target.result;
                        document.getElementById('preview').style.display = 'block';
                        document.getElementById('placeholder-text').style.display = 'none';
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }
        </script>
    </div>

</body>

</html>