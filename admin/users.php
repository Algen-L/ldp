<?php
session_start();
require '../includes/init_repos.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head')) {
    header("Location: ../index.php");
    exit;
}

// 1. Define Office Categories for Search Logic
$osdsOffices = ['ADMINISTRATIVE (PERSONEL)', 'ADMINISTRATIVE (PROPERTY AND SUPPLY)', 'ADMINISTRATIVE (RECORDS)', 'ADMINISTRATIVE (CASH)', 'ADMINISTRATIVE (GENERAL SERVICES)', 'FINANCE (ACCOUNTING)', 'FINANCE (BUDGET)', 'LEGAL', 'ICT'];
$sgodOffices = ['SCHOOL MANAGEMENT MONITORING & EVALUATION', 'HUMAN RESOURCES DEVELOPMENT', 'DISASTER RISK REDUCTION AND MANAGEMENT', 'EDUCATION FACILITIES', 'SCHOOL HEALTH AND NUTRITION', 'SCHOOL HEALTH AND NUTRITION (DENTAL)', 'SCHOOL HEALTH AND NUTRITION (MEDICAL)'];
$cidOffices = ['CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)', 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)'];

// Handle Log Filtering
$filters = [
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0,
    'action_type' => isset($_GET['action_type']) ? $_GET['action_type'] : '',
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : '',
    'limit' => 100
];

// Special categorization handling
if ($filters['search']) {
    $search_upper = strtoupper($filters['search']);
    if ($search_upper === 'OSDS') {
        $filters['offices'] = $osdsOffices;
        $filters['search'] = '';
    } elseif ($search_upper === 'CID') {
        $filters['offices'] = $cidOffices;
        $filters['search'] = '';
    } elseif ($search_upper === 'SGOD') {
        $filters['offices'] = $sgodOffices;
        $filters['search'] = '';
    }
}

$logs = $logRepo->getAllLogs($filters);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Dashboard</title>
    <?php require '../includes/admin_head.php'; ?>
    <style>
        /* --- New Premium Filter Styles --- */
        .premium-filter-container {
            background: white;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }

        .filter-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            width: 100%;
        }

        /* Custom Select Component */
        .custom-select-wrapper {
            position: relative;
            min-width: 180px;
        }

        #userSelect {
            flex: 1;
        }

        .custom-select-trigger {
            height: 40px;
            padding: 0 12px;
            background: white;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .custom-select-trigger:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }

        .custom-select-trigger.active {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 76, 117, 0.1);
        }

        .custom-select-text {
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .custom-select-trigger i {
            font-size: 0.75rem;
            color: var(--text-muted);
            transition: transform 0.3s ease;
        }

        .custom-select-trigger.active i {
            transform: rotate(180deg);
        }

        .custom-select-options {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            width: 100%;
            background: white;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            animation: dropdownFade 0.2s ease-out;
        }

        @keyframes dropdownFade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .custom-select-options.show {
            display: block;
        }

        .custom-option {
            padding: 8px 12px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .custom-option:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .custom-option.selected {
            background: rgba(15, 76, 117, 0.05);
            color: var(--primary);
            font-weight: 600;
        }

        /* Date Picker Range */
        .date-range-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            padding: 0 10px;
            border-radius: 8px;
            border: 1.5px solid var(--border-color);
            height: 40px;
        }

        .date-range-pills i {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .date-pill-input {
            border: none;
            background: transparent;
            font-size: 0.8rem;
            color: var(--text-primary);
            font-weight: 600;
            outline: none;
            width: 95px;
        }

        /* Action Buttons */
        .apply-btn {
            height: 40px;
            padding: 0 16px;
            background: linear-gradient(135deg, #0f4c75 0%, #1b6ca8 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .apply-btn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .reset-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            border: 1.5px solid var(--border-color);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .reset-btn:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: #fee2e2;
        }

        /* Activity List Density */
        .activity-logs-scroll-container {
            max-height: calc(100vh - 320px);
            min-height: 400px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }

        .activity-logs-scroll-container::-webkit-scrollbar {
            width: 6px;
        }

        .activity-logs-scroll-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .activity-logs-scroll-container::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 10px;
        }

        .activity-item {
            padding: 12px 16px;
        }

        .activity-user {
            font-size: 0.82rem;
            font-weight: 600;
        }

        .activity-time {
            font-size: 0.72rem;
        }

        .activity-desc {
            font-size: 0.8rem;
        }

        .log-type-update {
            border-left: 3px solid #ff9800;
            background: #fff3e0;
        }

        .log-type-review {
            border-left: 3px solid #4caf50;
            background: #e8f5e9;
        }

        .log-type-recommend {
            border-left: 3px solid #2196f3;
            background: #e3f2fd;
        }

        .log-type-approve {
            border-left: 3px solid #9c27b0;
            background: #f3e5f5;
        }

        .log-type-user-mgmt {
            border-left: 3px solid #607d8b;
            background: #eceff1;
        }

        .log-type-update .activity-icon {
            background-color: #ffe0b2;
            color: #ef6c00;
        }

        .log-type-review .activity-icon {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .log-type-recommend .activity-icon {
            background-color: #bbdefb;
            color: #1565c0;
        }

        .log-type-approve .activity-icon {
            background-color: #e1bee7;
            color: #7b1fa2;
        }

        .log-type-update .activity-desc strong {
            color: #ef6c00 !important;
        }
    </style>
</head>

<body>
    <?php
    function getLogIcon($action)
    {
        if (strpos($action, 'Logged In') !== false) {
            return '<i class="bi bi-box-arrow-in-right"></i>';
        } elseif (strpos($action, 'Logged Out') !== false) {
            return '<i class="bi bi-box-arrow-right"></i>';
        } elseif (strpos($action, 'Submitted') !== false) {
            return '<i class="bi bi-file-earmark-plus"></i>';
        } elseif (strpos($action, 'Updated') !== false || strpos($action, 'Certificate') !== false) {
            return '<i class="bi bi-pencil-square"></i>';
        } elseif (strpos($action, 'Reviewed') !== false) {
            return '<i class="bi bi-shield-check"></i>';
        } elseif (strpos($action, 'Recommended') !== false) {
            return '<i class="bi bi-hand-thumbs-up"></i>';
        } elseif (strpos($action, 'Approved') !== false) {
            return '<i class="bi bi-trophy"></i>';
        } elseif (strpos($action, 'User') !== false || strpos($action, 'Status') !== false) {
            return '<i class="bi bi-person-gear"></i>';
        } elseif (strpos($action, 'Viewed') !== false) {
            return '<i class="bi bi-eye"></i>';
        } elseif (strpos($action, 'Profile') !== false) {
            return '<i class="bi bi-person-bounding-box"></i>';
        } else {
            return '<i class="bi bi-journal-text"></i>';
        }
    }

    function getLogClass($action)
    {
        if (strpos($action, 'Logged In') !== false)
            return 'log-type-login';
        if (strpos($action, 'Logged Out') !== false)
            return 'log-type-logout';
        if (strpos($action, 'Submitted') !== false)
            return 'log-type-submission';
        if (strpos($action, 'Updated') !== false || strpos($action, 'Certificate') !== false)
            return 'log-type-update';
        if (strpos($action, 'Reviewed') !== false)
            return 'log-type-review';
        if (strpos($action, 'Recommended') !== false)
            return 'log-type-recommend';
        if (strpos($action, 'Approved') !== false)
            return 'log-type-approve';
        if (strpos($action, 'User') !== false || strpos($action, 'Status') !== false)
            return 'log-type-user-mgmt';
        if (strpos($action, 'Viewed') !== false)
            return 'log-type-view';
        return '';
    }
    ?>

    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Activity Logs</h1>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date-box">
                        <div class="time-section">
                            <span id="real-time-clock"><?php echo date('h:i:s A'); ?></span>
                        </div>
                        <div class="date-section">
                            <i class="bi bi-calendar3"></i>
                            <span><?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <!-- Premium Filter Bar -->
                <div class="premium-filter-container">
                    <form method="GET" class="filter-form" id="logFilterForm">
                        <div class="filter-grid">
                            <!-- Unified search input -->
                            <div class="search-input-wrapper-mini"
                                style="flex: 1; min-width: 250px; position: relative;">
                                <i class="bi bi-search"
                                    style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem; z-index: 2;"></i>
                                <input type="text" name="search"
                                    value="<?php echo htmlspecialchars($filters['search']); ?>" class="form-control"
                                    placeholder="Search name, office or category (OSDS, CID, SGOD)..."
                                    style="padding-left: 38px; height: 40px; border-radius: 8px; border: 1.5px solid var(--border-color); font-size: 0.82rem; font-weight: 500;">
                            </div>

                            <!-- Log Type Custom Select -->
                            <div class="custom-select-wrapper" id="actionSelect">
                                <input type="hidden" name="action_type"
                                    value="<?php echo htmlspecialchars($filters['action_type']); ?>">
                                <div class="custom-select-trigger">
                                    <span class="custom-select-text">
                                        <?php
                                        $aText = 'Every Action';
                                        $actions = [
                                            'Logged In' => 'Success Logins',
                                            'Logged Out' => 'Success Logouts',
                                            'Submitted' => 'Submissions',
                                            'Updated' => 'Activity Edits',
                                            'Certificate' => 'Cert Uploads',
                                            'Reviewed' => 'Supervisor Reviews',
                                            'Recommended' => 'ASDS Recommend',
                                            'Approved' => 'Final Approvals',
                                            'Viewed Specific' => 'Detailed Views',
                                            'Viewed' => 'List Views',
                                            'Profile' => 'Profile Changes'
                                        ];
                                        if (isset($actions[$filters['action_type']]))
                                            $aText = $actions[$filters['action_type']];
                                        echo htmlspecialchars($aText);
                                        ?>
                                    </span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="custom-select-options">
                                    <div class="custom-option <?php echo $filters['action_type'] == '' ? 'selected' : ''; ?>"
                                        data-value="">Every Action</div>
                                    <?php foreach ($actions as $val => $label): ?>
                                        <div class="custom-option <?php echo $filters['action_type'] == $val ? 'selected' : ''; ?>"
                                            data-value="<?php echo $val; ?>">
                                            <?php echo $label; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Date Range -->
                            <div class="date-range-pills">
                                <i class="bi bi-calendar-range"></i>
                                <input type="date" name="start_date" value="<?php echo $filters['start_date']; ?>"
                                    class="date-pill-input" title="From Date">
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">TO</span>
                                <input type="date" name="end_date" value="<?php echo $filters['end_date']; ?>"
                                    class="date-pill-input" title="To Date">
                            </div>

                            <!-- Actions -->
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="apply-btn">
                                    <i class="bi bi-funnel-fill"></i> Apply
                                </button>
                                <?php if ($filters['user_id'] > 0 || $filters['action_type'] || $filters['start_date'] || $filters['end_date']): ?>
                                    <a href="users.php" class="reset-btn" title="Reset all filters">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Logs List Section -->
                <div class="dashboard-card hover-elevate" style="margin-bottom: 0;">
                    <div class="card-header" style="padding: 10px 20px;">
                        <h2 style="font-size: 0.95rem;"><i class="bi bi-clock-history text-gradient"></i> Detailed
                            System Events</h2>
                        <span class="result-count" style="font-size: 0.75rem;">Found <?php echo count($logs); ?> recent
                            events</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="activity-logs-scroll-container">
                            <?php if (empty($logs)): ?>
                                <div class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="bi bi-shield-slash text-muted"
                                            style="font-size: 3.5rem; opacity: 0.3;"></i>
                                        <p class="mt-3 text-muted">No system events matched your current filters.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <div class="activity-item <?php echo getLogClass($log['action']); ?>">
                                        <div class="activity-icon">
                                            <?php echo getLogIcon($log['action']); ?>
                                        </div>
                                        <div class="activity-content">
                                            <div
                                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                                <div>
                                                    <span
                                                        class="activity-user"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                                    <span class="mx-2 text-muted">•</span>
                                                    <span
                                                        class="activity-time"><?php echo date('M d, Y • h:i A', strtotime($log['created_at'])); ?></span>
                                                </div>
                                                <div class="activity-time"
                                                    style="font-size: 0.7rem; background: var(--bg-primary); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                                                    <i class="bi bi-pc-display me-1"></i>
                                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                                </div>
                                            </div>
                                            <div class="activity-desc">
                                                <strong
                                                    style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($log['action']); ?></strong>
                                                <?php if ($log['details']): ?>
                                                    <div class="activity-details-box">
                                                        <?php echo htmlspecialchars($log['details']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Developed by Algen
                        D. Loveres and Cedrick V. Bacaresas</span></p>
            </footer>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Generic Custom Select Handler
            const setupCustomSelect = (containerId) => {
                const container = document.getElementById(containerId);
                if (!container) return;

                const trigger = container.querySelector('.custom-select-trigger');
                const options = container.querySelector('.custom-select-options');
                const text = container.querySelector('.custom-select-text');
                const hiddenInput = container.querySelector('input[type="hidden"]');
                const optionItems = container.querySelectorAll('.custom-option');

                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    // Close other dropdowns first
                    document.querySelectorAll('.custom-select-options').forEach(opt => {
                        if (opt !== options) opt.classList.remove('show');
                    });
                    document.querySelectorAll('.custom-select-trigger').forEach(trig => {
                        if (trig !== trigger) trig.classList.remove('active');
                    });

                    options.classList.toggle('show');
                    trigger.classList.toggle('active');
                });

                optionItems.forEach(item => {
                    item.addEventListener('click', () => {
                        const val = item.getAttribute('data-value');
                        hiddenInput.value = val;
                        text.textContent = item.textContent.trim();

                        // Update UI
                        optionItems.forEach(i => i.classList.remove('selected'));
                        item.classList.add('selected');

                        options.classList.remove('show');
                        trigger.classList.remove('active');
                    });
                });
            };

            setupCustomSelect('actionSelect');

            // Global Click to close dropdowns
            document.addEventListener('click', () => {
                document.querySelectorAll('.custom-select-options').forEach(opt => opt.classList.remove('show'));
                document.querySelectorAll('.custom-select-trigger').forEach(trig => trig.classList.remove('active'));
            });
        });
    </script>
</body>

</html>