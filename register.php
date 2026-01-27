<?php
require 'includes/db.php';

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    $office_station = trim($_POST['office_station'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $rating_period = trim($_POST['rating_period'] ?? '');
    $area_of_specialization = trim($_POST['area_of_specialization'] ?? '');
    $age = isset($_POST['age']) ? (int) $_POST['age'] : 0;
    $sex = trim($_POST['sex'] ?? '');

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

            // Handle Profile Picture Upload (Simplified for registration)
            $dbPath = NULL;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/profile_pics/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);
                $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['profile_picture']['name']));
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                    $dbPath = $uploadDir . $fileName;
                }
            }

            // Insert user
            $sql = "INSERT INTO users (username, password, full_name, office_station, position, rating_period, area_of_specialization, age, sex, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$username, $hashed_password, $full_name, $office_station, $position, $rating_period, $area_of_specialization, $age, $sex, $dbPath])) {
                $message = "Registration successful! You can now login.";
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
    <title>Create Account - LDP</title>
    <?php require 'includes/head.php'; ?>
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            width: 100%;
            max-width: 900px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            display: grid;
            grid-template-columns: 1fr 1.5fr;
        }

        .auth-sidebar {
            background: var(--primary-gradient);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-form-container {
            padding: 60px;
            background: white;
        }

        .logo-placeholder {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 992px) {
            .auth-card {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .auth-sidebar {
                display: none;
            }
        }

        /* Tom Select Custom Styling */
        .ts-control {
            border: 1.5px solid var(--border-color) !important;
            border-radius: var(--radius-md) !important;
            padding: 10px 14px !important;
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            font-family: inherit !important;
            font-size: 0.95rem !important;
            transition: all var(--transition-fast) !important;
        }

        .ts-control:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px var(--primary-light) !important;
            background: white !important;
        }

        .ts-dropdown {
            border-radius: var(--radius-lg) !important;
            border: 1px solid var(--border-light) !important;
            box-shadow: var(--shadow-lg) !important;
            margin-top: 8px !important;
            padding: 8px !important;
            z-index: 2000 !important;
        }

        .ts-dropdown .optgroup-header {
            font-weight: 800 !important;
            text-transform: uppercase !important;
            font-size: 0.7rem !important;
            color: var(--primary) !important;
            letter-spacing: 0.05em !important;
            padding: 12px 12px 6px !important;
            background: var(--bg-secondary) !important;
            border-radius: var(--radius-sm) !important;
        }

        .ts-dropdown .option {
            padding: 10px 12px !important;
            border-radius: var(--radius-sm) !important;
            font-size: 0.9rem !important;
            color: var(--text-secondary) !important;
        }

        .ts-dropdown .active {
            background: var(--primary) !important;
            color: white !important;
        }
    </style>
</head>

<body>

    <div class="auth-card">
        <div class="auth-sidebar">
            <div class="logo-placeholder">
                <i class="bi bi-journal-check"></i>
            </div>
            <h1 style="font-size: 2.5rem; font-weight: 800; line-height: 1.1; margin-bottom: 24px;">L&D Passbook System
            </h1>
            <p style="font-size: 1.1rem; opacity: 0.9; margin-bottom: 48px; font-weight: 500;">Record your professional
                growth and track your career achievements in one place.</p>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div style="display: flex; gap: 16px; align-items: center;">
                    <div
                        style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <span>Fast Activity Recording</span>
                </div>
                <div style="display: flex; gap: 16px; align-items: center;">
                    <div
                        style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <span>Secure Verification</span>
                </div>
                <div style="display: flex; gap: 16px; align-items: center;">
                    <div
                        style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-bar-chart"></i>
                    </div>
                    <span>Progress Monitoring</span>
                </div>
            </div>
        </div>

        <div class="auth-form-container">
            <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">Create
                Account</h2>
            <p style="color: var(--text-muted); margin-bottom: 40px; font-weight: 500;">Fill in your details to get
                started.</p>

            <?php if ($message): ?>
                <script>
                    window.addEventListener('DOMContentLoaded', function () {
                        showToast("<?php echo ($messageType === 'success') ? 'Success!' : 'Registration Error'; ?>", "<?php echo $message; ?>", "<?php echo $messageType; ?>");
                        <?php if ($messageType === 'success'): ?>
                            setTimeout(() => { window.location.href = 'index.php'; }, 2000);
                        <?php endif; ?>
                    });
                </script>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Full Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="full_name" class="form-control" required placeholder="John Doe">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="j.doe">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span style="color: var(--danger);">*</span></label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Office / Station</label>
                    <select name="office_station" id="office_select" class="form-control" required>
                        <option value="">Select your office...</option>
                        <optgroup label="OSDS">
                            <option value="ADMINISTRATIVE (PERSONEL)">ADMINISTRATIVE (PERSONEL)</option>
                            <option value="ADMINISTRATIVE (PROPERTY AND SUPPLY)">ADMINISTRATIVE (PROPERTY AND SUPPLY)
                            </option>
                            <option value="ADMINISTRATIVE (RECORDS)">ADMINISTRATIVE (RECORDS)</option>
                            <option value="ADMINISTRATIVE (CASH)">ADMINISTRATIVE (CASH)</option>
                            <option value="ADMINISTRATIVE (GENERAL SERVICES)">ADMINISTRATIVE (GENERAL SERVICES)</option>
                            <option value="FINANCE (ACCOUNTING)">FINANCE (ACCOUNTING)</option>
                            <option value="FINANCE (BUDGET)">FINANCE (BUDGET)</option>
                            <option value="LEGAL">LEGAL</option>
                            <option value="ICT">ICT</option>
                        </optgroup>
                        <optgroup label="SGOD">
                            <option value="SCHOOL MANAGEMENT MONITORING & EVALUATION">SCHOOL MANAGEMENT MONITORING &
                                EVALUATION</option>
                            <option value="HUMAN RESOURCES DEVELOPMENT">HUMAN RESOURCES DEVELOPMENT</option>
                            <option value="DISASTER RISK REDUCTION AND MANAGEMENT">DISASTER RISK REDUCTION AND
                                MANAGEMENT</option>
                            <option value="EDUCATION FACILITIES">EDUCATION FACILITIES</option>
                            <option value="SCHOOL HEALTH AND NUTRITION">SCHOOL HEALTH AND NUTRITION</option>
                            <option value="SCHOOL HEALTH AND NUTRITION (DENTAL)">SCHOOL HEALTH AND NUTRITION (DENTAL)
                            </option>
                            <option value="SCHOOL HEALTH AND NUTRITION (MEDICAL)">SCHOOL HEALTH AND NUTRITION (MEDICAL)
                            </option>
                        </optgroup>
                        <optgroup label="CID">
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)">CURRICULUM
                                IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)</option>
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)">
                                CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)</option>
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)">CURRICULUM
                                IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)</option>
                            <option value="CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)">
                                CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-control" placeholder="e.g. Teacher I">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="area_of_specialization" class="form-control"
                            placeholder="e.g. Science">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" class="form-control" placeholder="25">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sex</label>
                        <select name="sex" class="form-control">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Rating Period</label>
                    <input type="text" name="rating_period" class="form-control" placeholder="e.g. 2025">
                </div>

                <div style="margin-top: 32px;">
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        REGISTER ACCOUNT
                    </button>
                    <p
                        style="text-align: center; margin-top: 24px; color: var(--text-muted); font-size: 0.9rem; font-weight: 500;">
                        Already have an account? <a href="index.php"
                            style="color: var(--primary); font-weight: 700; text-decoration: none;">Login here</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new TomSelect('#office_select', {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                },
                placeholder: "Type to search office...",
                maxOptions: 50
            });
        });
    </script>
</body>

</html>
