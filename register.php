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

        /* Simplified Office Dropdown Design */
        .ts-wrapper {
            position: relative;
        }

        .ts-wrapper.form-control {
            padding: 0 !important;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            height: auto !important;
        }

        .ts-control {
            background: var(--bg-secondary) !important;
            border: 2px solid var(--border-color) !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
            color: var(--text-primary) !important;
            font-family: inherit !important;
            font-size: 0.95rem !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
            min-height: 48px !important;
        }

        .ts-control:hover {
            border-color: var(--primary) !important;
            background: white !important;
        }

        .ts-control:focus,
        .ts-control.focus {
            border-color: var(--primary) !important;
            background: white !important;
            box-shadow: 0 0 0 3px rgba(15, 76, 117, 0.1) !important;
            outline: none !important;
        }

        .ts-control input {
            color: var(--text-primary) !important;
            font-weight: 500 !important;
        }

        .ts-control input::placeholder {
            color: var(--text-muted) !important;
        }

        /* Dropdown Container */
        .ts-dropdown {
            background: white !important;
            border: 1px solid var(--border-light) !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1) !important;
            margin-top: 6px !important;
            padding: 8px !important;
            z-index: 2000 !important;
        }

        /* Category Headers */
        .ts-dropdown .optgroup-header {
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 0.7rem !important;
            letter-spacing: 0.08em !important;
            padding: 10px 12px 6px !important;
            margin-top: 6px !important;
            background: rgba(15, 76, 117, 0.08) !important;
            border-radius: 8px !important;
            border-left: 3px solid var(--primary) !important;
            color: var(--primary) !important;
        }

        .ts-dropdown .optgroup-header:first-child {
            margin-top: 0 !important;
        }

        /* Office Options */
        .ts-dropdown .option {
            padding: 10px 12px !important;
            border-radius: 8px !important;
            font-size: 0.9rem !important;
            color: var(--text-secondary) !important;
            font-weight: 500 !important;
            margin: 2px 0 !important;
            transition: all 0.15s ease !important;
            cursor: pointer !important;
        }

        .ts-dropdown .option:hover {
            background: rgba(15, 76, 117, 0.08) !important;
            color: var(--text-primary) !important;
        }

        .ts-dropdown .option.active {
            background: var(--primary-gradient) !important;
            color: white !important;
            font-weight: 600 !important;
        }

        /* Scrollbar */
        .ts-dropdown-content::-webkit-scrollbar {
            width: 6px;
        }

        .ts-dropdown-content::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        .ts-dropdown-content::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
            opacity: 0.5;
        }

        .ts-dropdown-content::-webkit-scrollbar-thumb:hover {
            opacity: 0.8;
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