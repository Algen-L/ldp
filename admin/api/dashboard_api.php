<?php
/**
 * Admin Dashboard API
 * Returns JSON data for dashboard analytics.
 */
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'immediate_head' && $_SESSION['role'] !== 'head_hr')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require '../../includes/init_repos.php';

try {
    // 1. Parse Filters
    $filter = $_GET['filter'] ?? 'month';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $filters = [
        'filter_type' => $filter,
        'start_date' => $date_from,
        'end_date' => $date_to
    ];

    // 2. Fetch Data
    $activities = $activityRepo->getAllActivities($filters);

    // 3. Process Stats
    $totalSubmissions = count($activities);
    $totalUsers = $userRepo->getTotalUserCount();

    $pendingCount = 0;
    $approvedCount = 0;

    // Office Distribution
    $osdsCount = 0;
    $cidCount = 0;
    $sgodCount = 0;

    // Submission Frequency
    $frequencyData = [];

    // Map Offices
    $office_map = [];
    $stmt_all_offices = $pdo->query("SELECT name, category FROM offices");
    while ($row = $stmt_all_offices->fetch(PDO::FETCH_ASSOC)) {
        $office_map[strtoupper($row['name'])] = $row['category'];
    }

    foreach ($activities as $act) {
        // Status Counts
        if ($act['status'] === 'Pending')
            $pendingCount++;
        if ($act['status'] === 'Approved')
            $approvedCount++;

        // Office Counts
        $office = strtoupper($act['office_station'] ?? '');
        $category = $office_map[$office] ?? '';

        if ($category === 'OSDS')
            $osdsCount++;
        elseif ($category === 'CID')
            $cidCount++;
        elseif ($category === 'SGOD')
            $sgodCount++;

        // Frequency Data
        $actDate = $act['activity_created_at'] ?? $act['created_at'];
        if (isset($actDate)) {
            $dateKey = date('Y-m-d', strtotime($actDate));
            $frequencyData[$dateKey] = ($frequencyData[$dateKey] ?? 0) + 1;
        }
    }

    // Sort Frequency Data
    ksort($frequencyData);
    $freqLabels = array_keys($frequencyData);
    $freqValues = array_values($frequencyData);

    // 4. Return Response
    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total_submissions' => $totalSubmissions,
            'total_users' => $totalUsers,
            'pending' => $pendingCount,
            'approved' => $approvedCount
        ],
        'charts' => [
            'frequency' => [
                'labels' => $freqLabels,
                'values' => $freqValues
            ],
            'office' => [
                'osds' => $osdsCount,
                'cid' => $cidCount,
                'sgod' => $sgodCount
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
}
?>