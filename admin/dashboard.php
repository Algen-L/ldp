<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head')) {
    header("Location: ../index.php");
    exit;
}



// Filter Logic
$filter = $_GET['filter'] ?? 'month'; // Default to month
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// If dates are provided, force filter to 'custom'
if (!empty($dateFrom) || !empty($dateTo)) {
    $filter = 'custom';
}

$whereClause = "";
$params = [];

if ($filter === 'custom') {
    if (!empty($dateFrom) && !empty($dateTo)) {
        $whereClause = "WHERE DATE(ld.created_at) BETWEEN :date_from AND :date_to";
        $params['date_from'] = $dateFrom;
        $params['date_to'] = $dateTo;
    } elseif (!empty($dateFrom)) {
        $whereClause = "WHERE DATE(ld.created_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    } elseif (!empty($dateTo)) {
        $whereClause = "WHERE DATE(ld.created_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }
} elseif ($filter === 'today') {
    $whereClause = "WHERE DATE(ld.created_at) = CURDATE()";
} elseif ($filter === 'week') {
    $whereClause = "WHERE YEARWEEK(ld.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'month') {
    $whereClause = "WHERE MONTH(ld.created_at) = MONTH(CURDATE()) AND YEAR(ld.created_at) = YEAR(CURDATE())";
}

// Fetch all activities
$stmt = $pdo->prepare("
    SELECT ld.*, u.full_name, u.office_station 
    FROM ld_activities ld 
    JOIN users u ON ld.user_id = u.id 
    $whereClause
    ORDER BY ld.created_at DESC
");
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Statistics
$totalSubmissions = count($activities);

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'");
$totalUsers = $stmt->fetchColumn();

// Count per status
$pendingCount = 0;
$approvedCount = 0;
foreach ($activities as $act) {
    if ($act['status'] === 'Pending')
        $pendingCount++;
    if ($act['status'] === 'Approved')
        $approvedCount++;
}

// Analytics: Submissions by General Office
$osdsCount = 0;
$cidCount = 0;
$sgodCount = 0;

$osdsOffices = ['ADMINISTRATIVE (PERSONEL)', 'ADMINISTRATIVE (PROPERTY AND SUPPLY)', 'ADMINISTRATIVE (RECORDS)', 'ADMINISTRATIVE (CASH)', 'ADMINISTRATIVE (GENERAL SERVICES)', 'FINANCE (ACCOUNTING)', 'FINANCE (BUDGET)', 'LEGAL', 'ICT'];
$sgodOffices = ['SCHOOL MANAGEMENT MONITORING & EVALUATION', 'HUMAN RESOURCES DEVELOPMENT', 'DISASTER RISK REDUCTION AND MANAGEMENT', 'EDUCATION FACILITIES', 'SCHOOL HEALTH AND NUTRITION', 'SCHOOL HEALTH AND NUTRITION (DENTAL)', 'SCHOOL HEALTH AND NUTRITION (MEDICAL)'];
$cidOffices = ['CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)', 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)'];

foreach ($activities as $act) {
    $office = strtoupper($act['office_station'] ?? '');
    if (in_array($office, $osdsOffices)) {
        $osdsCount++;
    } elseif (in_array($office, $cidOffices)) {
        $cidCount++;
    } elseif (in_array($office, $sgodOffices)) {
        $sgodCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LDP</title>
    <?php require 'includes/admin_head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="admin-layout">
        <?php require '../includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb">
                        <h1 class="page-title">Dashboard Overview</h1>
                    </div>
                </div>
                <div class="top-bar-right" style="display: flex; gap: 16px; align-items: center;">
                    <form method="GET" id="filterForm" style="margin: 0; display: flex; gap: 8px; align-items: center;">
                        <div class="filter-group"
                            style="display: flex; gap: 6px; align-items: center; background: var(--bg-secondary); padding: 3px; border-radius: 10px; border: 1px solid var(--border-color);">
                            <select name="filter" id="filterSelect" class="form-select form-select-sm"
                                style="border: none; background: transparent; padding: 4px 10px; font-weight: 600; font-size: 0.85rem; color: var(--text-primary); cursor: pointer; height: 28px; outline: none; min-width: 120px;">
                                <option value="today" <?php echo ($filter === 'today') ? 'selected' : ''; ?>>Today
                                </option>
                                <option value="week" <?php echo ($filter === 'week') ? 'selected' : ''; ?>>This Week
                                </option>
                                <option value="month" <?php echo ($filter === 'month') ? 'selected' : ''; ?>>This Month
                                </option>
                                <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>All Time</option>
                                <option value="custom" <?php echo ($filter === 'custom') ? 'selected' : ''; ?>>Custom
                                    Range</option>
                            </select>

                            <div id="customDateInputs"
                                style="display: <?php echo ($filter === 'custom') ? 'flex' : 'none'; ?>; gap: 4px; align-items: center; padding-left: 6px; border-left: 1px solid var(--border-color);">
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                                    class="form-control form-control-sm"
                                    style="border: 1px solid var(--border-color); border-radius: 6px; padding: 2px 6px; font-size: 0.8rem; height: 28px; background: white; color: var(--text-secondary);" />
                                <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500;">to</span>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                                    class="form-control form-control-sm"
                                    style="border: 1px solid var(--border-color); border-radius: 6px; padding: 2px 6px; font-size: 0.8rem; height: 28px; background: white; color: var(--text-secondary);" />
                                <button type="submit" class="btn btn-primary btn-sm"
                                    style="height: 28px; padding: 0 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="current-date-box">
                        <i class="bi bi-calendar3"></i>
                        <span><?php echo date('l, F d, Y'); ?></span>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                <div class="dashboard-top-grid">
                    <div class="stats-column">
                        <div class="stat-card stat-total">
                            <div class="stat-icon">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Submissions</span>
                                <span class="stat-value"><?php echo number_format($totalSubmissions); ?></span>
                            </div>
                        </div>

                        <div class="stat-card stat-total">
                            <div class="stat-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Total Users</span>
                                <span class="stat-value"><?php echo number_format($totalUsers); ?></span>
                            </div>
                        </div>

                        <div class="stat-card stat-pending">
                            <div class="stat-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Pending</span>
                                <span class="stat-value"><?php echo number_format($pendingCount); ?></span>
                            </div>
                        </div>

                        <div class="stat-card stat-resolved">
                            <div class="stat-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Approved</span>
                                <span class="stat-value"><?php echo number_format($approvedCount); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="analytics-column">
                        <div class="dashboard-card hover-elevate" style="height: 100%;">
                            <div class="card-header">
                                <h2><i class="bi bi-bar-chart-line text-gradient"></i> Submission Frequency by General
                                    Office
                                </h2>
                                <span class="text-muted" style="font-size: 0.85rem; font-weight: 500;">OSDS vs CID vs
                                    SGOD</span>
                            </div>
                            <div class="card-body"
                                style="padding: 30px 40px; height: calc(100% - 70px); display: flex; align-items: center;">
                                <div style="height: 100%; width: 100%; position: relative; min-height: 300px;">
                                    <canvas id="officeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Recent Activity Section -->
                    <div class="dashboard-card hover-elevate">
                        <div class="card-header">
                            <h2><i class="bi bi-activity text-gradient"></i> Recent Activity Logs</h2>
                            <a href="submissions.php" class="btn btn-secondary btn-sm">
                                View Submissions <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Submission Date</th>
                                            <th>User / Personnel</th>
                                            <th>Activity Description</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Options</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($activities)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5">
                                                    <div class="empty-state">
                                                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                                        <p class="mt-3">No activity logs recorded yet.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach (array_slice($activities, 0, 8) as $act):
                                                $row_class = '';
                                                $office = strtoupper($act['office_station'] ?? '');
                                                if (in_array($office, $osdsOffices))
                                                    $row_class = 'row-osds';
                                                elseif (in_array($office, $cidOffices))
                                                    $row_class = 'row-cid';
                                                elseif (in_array($office, $sgodOffices))
                                                    $row_class = 'row-sgod';
                                                ?>
                                                <tr class="<?php echo $row_class; ?>">
                                                    <td>
                                                        <span
                                                            class="cell-primary"><?php echo date('M d, Y', strtotime($act['created_at'])); ?></span>
                                                        <span
                                                            class="cell-secondary"><?php echo date('h:i A', strtotime($act['created_at'])); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="cell-primary">
                                                            <?php echo htmlspecialchars($act['full_name']); ?>
                                                        </div>
                                                        <div class="cell-secondary">
                                                            <?php echo htmlspecialchars($act['office_station']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="cell-primary"><?php echo htmlspecialchars($act['title']); ?></span>
                                                        <span class="cell-secondary text-truncate" style="max-width: 250px;">
                                                            <?php echo htmlspecialchars($act['type_ld'] ?? 'Training Activity'); ?>
                                                        </span>
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
                                                        <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>"
                                                            class="btn btn-secondary btn-sm">
                                                            Review
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO L&D Passbook System. <span class="text-muted">Digital Service
                        Excellence.</span></p>
            </footer>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterSelect = document.getElementById('filterSelect');
            const customDateInputs = document.getElementById('customDateInputs');
            const filterForm = document.getElementById('filterForm');

            filterSelect.addEventListener('change', function () {
                if (this.value === 'custom') {
                    customDateInputs.style.display = 'flex';
                } else {
                    customDateInputs.style.display = 'none';
                    // Clear dates when switching away from custom
                    filterForm.querySelector('input[name="date_from"]').value = '';
                    filterForm.querySelector('input[name="date_to"]').value = '';
                    filterForm.submit();
                }
            });

            const ctx = document.getElementById('officeChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['OSDS', 'CID', 'SGOD'],
                    datasets: [{
                        label: 'Total Submissions',
                        data: [<?php echo $osdsCount; ?>, <?php echo $cidCount; ?>, <?php echo $sgodCount; ?>],
                        backgroundColor: [
                            'rgba(249, 115, 22, 0.8)', // Orange (OSDS)
                            'rgba(234, 179, 8, 0.8)',  // Yellow (CID)
                            'rgba(59, 130, 246, 0.8)'  // Blue (SGOD)
                        ],
                        borderColor: [
                            '#f97316',
                            '#eab308',
                            '#3b82f6'
                        ],
                        borderWidth: 2,
                        borderRadius: 12,
                        barThickness: 80,
                        maxBarThickness: 100
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                precision: 0,
                                color: '#64748b',
                                font: {
                                    family: "'Plus Jakarta Sans', sans-serif",
                                    weight: '600'
                                },
                                padding: 10
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#1e293b',
                                font: {
                                    family: "'Plus Jakarta Sans', sans-serif",
                                    size: 13,
                                    weight: '700'
                                },
                                padding: 10
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>