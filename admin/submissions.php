<?php
session_start();
require '../includes/init_repos.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head')) {
    header("Location: ../index.php");
    exit;
}

// Fetch all users for filtering
$all_users = $userRepo->getAllUsers(['admin']);

// Handle Filtering
$filters = [
    'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0,
    'status_filter' => isset($_GET['status']) ? $_GET['status'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : ''
];

// Special divisional keyword handling (OSDS, CID, SGOD)
if ($filters['search']) {
    $search_upper = strtoupper($filters['search']);
    if (in_array($search_upper, ['OSDS', 'CID', 'SGOD'])) {
        $filters['office_division'] = $search_upper;
        $filters['search'] = '';
    }
}

$activities = $activityRepo->getAllActivities($filters);

// Status labels for display
$statuses = [
    'Pending' => 'Pending Approval',
    'Reviewed' => 'Reviewed',
    'Recommending' => 'Recommending',
    'Approved' => 'Approved'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions Management - Admin</title>
    <?php require '../includes/admin_head.php'; ?>
    <style>
        .highlight-pending-approval {
            background-color: #fff7ed !important;
            /* Light Orange background */
            border-left: 4px solid #f97316 !important;
            /* Orange accent border */
            position: relative;
        }

        .highlight-pending-approval::after {
            content: "Needs Approval";
            position: absolute;
            top: 4px;
            right: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            background: #f97316;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .submissions-scroll-container .data-table thead {
            position: sticky;
            top: 0;
            z-index: 20;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

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

        /* Search Input */
        .search-wrapper {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .search-control {
            width: 100%;
            height: 40px;
            padding: 0 12px 0 36px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.82rem;
            transition: all 0.3s ease;
            outline: none;
            background: #f8fafc;
        }

        .search-control:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(15, 76, 117, 0.08);
        }

        /* Custom Select Component */
        .custom-select-wrapper {
            position: relative;
            min-width: 160px;
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
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(15, 76, 117, 0.25);
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(15, 76, 117, 0.35);
            filter: brightness(1.1);
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
            transform: rotate(-180deg);
            background: #fee2e2;
        }

        /* Table Compact adjustments */
        .data-table th {
            padding: 10px 12px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .data-table td {
            padding: 10px 12px;
        }

        .cell-primary {
            font-size: 0.82rem;
            font-weight: 600;
        }

        .cell-secondary {
            font-size: 0.72rem;
        }

        .status-badge {
            padding: 3px 8px;
            font-size: 0.68rem;
        }

        .submissions-scroll-container {
            max-height: calc(100vh - 300px);
            min-height: 400px;
            overflow-y: auto;
            border-top: 1px solid var(--border-color);
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }

        .submissions-scroll-container::-webkit-scrollbar {
            width: 6px;
        }

        .submissions-scroll-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .submissions-scroll-container::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Submission Management</h1>
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
                    <form method="GET" class="filter-form" id="mainFilterForm">
                        <div class="filter-grid">
                            <div class="search-wrapper">
                                <i class="bi bi-search"></i>
                                <input type="text" name="search"
                                    value="<?php echo htmlspecialchars($filters['search']); ?>"
                                    placeholder="Search entries, names, offices or categories (OSDS, CID, SGOD)..."
                                    class="search-control">
                            </div>



                            <!-- Status Custom Select -->
                            <div class="custom-select-wrapper" id="statusSelect">
                                <input type="hidden" name="status"
                                    value="<?php echo htmlspecialchars($filters['status_filter']); ?>">
                                <div class="custom-select-trigger">
                                    <span class="custom-select-text">
                                        <?php
                                        $sText = 'All Statuses';
                                        $statuses = [
                                            'Pending' => 'Pending Approval',
                                            'Reviewed' => 'Reviewed',
                                            'Recommending' => 'Recommending',
                                            'Approved' => 'Approved'
                                        ];
                                        if (isset($statuses[$filters['status_filter']]))
                                            $sText = $statuses[$filters['status_filter']];
                                        echo htmlspecialchars($sText);
                                        ?>
                                    </span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="custom-select-options">
                                    <div class="custom-option <?php echo $filters['status_filter'] == '' ? 'selected' : ''; ?>"
                                        data-value="">All Statuses</div>
                                    <?php foreach ($statuses as $val => $label): ?>
                                        <div class="custom-option <?php echo $filters['status_filter'] == $val ? 'selected' : ''; ?>"
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
                                <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700;">TO</span>
                                <input type="date" name="end_date" value="<?php echo $filters['end_date']; ?>"
                                    class="date-pill-input" title="To Date">
                            </div>

                            <!-- Actions -->
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="apply-btn">
                                    <i class="bi bi-funnel-fill"></i> Apply
                                </button>
                                <?php if ($filters['user_id'] > 0 || $filters['status_filter'] || $filters['search'] || $filters['start_date'] || $filters['end_date']): ?>
                                    <a href="submissions.php" class="reset-btn" title="Reset all filters">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Submissions Table Section -->
                <div class="dashboard-card hover-elevate" style="margin-bottom: 0;">
                    <div class="card-header" style="padding: 10px 20px;">
                        <h2 style="font-size: 0.95rem;"><i class="bi bi-file-earmark-text text-gradient"></i> Activity
                            Submissions</h2>
                        <span class="result-count" style="font-size: 0.75rem;">Showing <?php echo count($activities); ?>
                            total records</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="submissions-scroll-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Submission Date</th>
                                        <th>Personnel Info</th>
                                        <th>Activity Details</th>
                                        <th style="text-align: center;">Approvals</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Options</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activities)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="bi bi-folder2-open text-muted"
                                                        style="font-size: 3.5rem; opacity: 0.4;"></i>
                                                    <p class="mt-3 text-muted">No submissions found matching your filters.
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($activities as $act):
                                            $row_class = '';
                                            if (!empty($act['office_division'])) {
                                                $row_class = 'row-' . strtolower($act['office_division']);
                                            }

                                            // Highlight Logic for Immediate Head
                                            if ($_SESSION['role'] === 'immediate_head' && $act['reviewed_by_supervisor'] && $act['recommending_asds'] && !$act['approved_sds']) {
                                                $row_class .= ' highlight-pending-approval';
                                            }
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td>
                                                    <span class="cell-primary">
                                                        <i class="bi bi-calendar-event text-muted me-2"></i>
                                                        <?php echo date('M d, Y', strtotime($act['created_at'])); ?>
                                                    </span>
                                                    <span class="cell-secondary">Logged
                                                        <?php echo date('M d, Y', strtotime($act['created_at'])); ?></span>
                                                </td>
                                                <td>
                                                    <div class="cell-primary"><?php echo htmlspecialchars($act['full_name']); ?>
                                                    </div>
                                                    <div class="cell-secondary">
                                                        <?php echo htmlspecialchars($act['office_station']); ?>
                                                    </div>
                                                </td>
                                                <td style="max-width: 320px;">
                                                    <div class="cell-primary text-truncate"
                                                        title="<?php echo htmlspecialchars($act['title']); ?>">
                                                        <?php echo htmlspecialchars($act['title']); ?>
                                                    </div>
                                                    <div class="cell-secondary text-truncate">
                                                        <?php echo htmlspecialchars($act['competency']); ?>
                                                    </div>
                                                </td>
                                                <td style="text-align: center;">
                                                    <div class="approval-indicators"
                                                        style="display: flex; gap: 8px; justify-content: center;">
                                                        <span title="Supervisor Reviewed">
                                                            <i class="bi bi-check-circle-fill <?php echo $act['reviewed_by_supervisor'] ? 'text-success' : 'text-muted'; ?>"
                                                                style="font-size: 1.1rem; opacity: <?php echo $act['reviewed_by_supervisor'] ? '1' : '0.4'; ?>;"></i>
                                                        </span>
                                                        <span title="ASDS Recommended">
                                                            <i class="bi bi-check-circle-fill <?php echo $act['recommending_asds'] ? 'text-primary' : 'text-muted'; ?>"
                                                                style="font-size: 1.1rem; opacity: <?php echo $act['recommending_asds'] ? '1' : '0.4'; ?>;"></i>
                                                        </span>
                                                        <span title="SDS Approved">
                                                            <i class="bi bi-check-circle-fill <?php echo $act['approved_sds'] ? 'text-success' : 'text-muted'; ?>"
                                                                style="font-size: 1.1rem; opacity: <?php echo $act['approved_sds'] ? '1' : '0.4'; ?>;"></i>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = 'status-pending';
                                                    $label = 'Pending';
                                                    if ($act['approved_sds']) {
                                                        $status_class = 'status-resolved';
                                                        $label = 'Approved';
                                                    } elseif ($act['recommending_asds']) {
                                                        $status_class = 'status-in_progress';
                                                        $label = 'Recommended';
                                                    } elseif ($act['reviewed_by_supervisor']) {
                                                        $status_class = 'status-accepted';
                                                        $label = 'Reviewed';
                                                    }
                                                    ?>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo $label; ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div class="action-buttons"
                                                        style="display: flex; gap: 8px; justify-content: flex-end;">
                                                        <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>"
                                                            class="btn btn-secondary btn-icon" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="../pages/edit_activity.php?id=<?php echo $act['id']; ?>"
                                                            class="btn btn-outline btn-icon" title="Edit Entry">
                                                            <i class="bi bi-pencil" style="color: var(--primary);"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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


            setupCustomSelect('statusSelect');

            // Global Click to close dropdowns
            document.addEventListener('click', () => {
                document.querySelectorAll('.custom-select-options').forEach(opt => opt.classList.remove('show'));
                document.querySelectorAll('.custom-select-trigger').forEach(trig => trig.classList.remove('active'));
            });
        });
    </script>
</body>

</html>