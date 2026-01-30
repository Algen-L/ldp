<?php
require 'includes/register_handler.php';
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
    <link rel="stylesheet" href="css/pages/register.css?v=<?php echo time(); ?>">
</head>

<body class="register-body">

    <div class="auth-card">
        <div class="auth-sidebar">
            <div class="logo-placeholder">
                <i class="bi bi-journal-check"></i>
            </div>
            <h1 class="sidebar-title">L&D Passbook System</h1>
            <p class="sidebar-desc">Record your professional growth and track your career achievements in one place.</p>

            <div class="sidebar-features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <span>Fast Activity Recording</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <span>Secure Verification</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-bar-chart"></i>
                    </div>
                    <span>Progress Monitoring</span>
                </div>
            </div>
        </div>

        <div class="auth-form-container">
            <h2 class="form-title">Create Account</h2>
            <p class="form-subtitle">Fill in your details to get started.</p>

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
                    <label class="form-label">Full Name <span class="label-required">*</span></label>
                    <input type="text" name="full_name" class="form-control" required placeholder="John Doe">
                </div>

                <div class="register-form-grid">
                    <div class="form-group">
                        <label class="form-label">Username <span class="label-required">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="j.doe">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="label-required">*</span></label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Office / Station</label>
                    <select name="office_station" id="office_select" class="form-control" required>
                        <option value="">Select your office...</option>
                        <?php if (!empty($offices_list)): ?>
                            <?php foreach ($offices_list as $category => $items): ?>
                                <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                    <?php foreach ($items as $office): ?>
                                        <option value="<?php echo htmlspecialchars($office['name']); ?>">
                                            <?php echo htmlspecialchars($office['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="register-form-grid">
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

                <div class="register-form-grid">
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

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg btn-full">
                        REGISTER ACCOUNT
                    </button>
                    <p class="register-footer">
                        Already have an account? <a href="index.php" class="login-link">Login here</a>
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