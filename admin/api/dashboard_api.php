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

    // --- HR SPECIFIC OVERRIDES ---
    if ($_SESSION['role'] === 'head_hr') {
        // Today's Logins
        $stmt_logins = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'Logged In' AND DATE(created_at) = CURRENT_DATE");
        $stmt_logins->execute();
        $todayLogins = $stmt_logins->fetchColumn();

        // New Registrations
        $date_filter_sql = "";
        $date_params = [];
        if ($filter === 'today')
            $date_filter_sql = "AND DATE(created_at) = CURRENT_DATE";
        elseif ($filter === 'week')
            $date_filter_sql = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        elseif ($filter === 'month')
            $date_filter_sql = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        elseif ($filter === 'custom' && $date_from && $date_to) {
            $date_filter_sql = "AND DATE(created_at) BETWEEN ? AND ?";
            $date_params = [$date_from, $date_to];
        }
        $stmt_new_users = $pdo->prepare("SELECT COUNT(*) FROM users WHERE 1=1 $date_filter_sql");
        $stmt_new_users->execute($date_params);
        $newRegistrations = $stmt_new_users->fetchColumn();

        // Active Today
        $stmt_active_today = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE DATE(created_at) = CURRENT_DATE");
        $stmt_active_today->execute();
        $activeToday = $stmt_active_today->fetchColumn();

        // Growth Chart Data (Last 30 days)
        $stmt_growth = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        $stmt_growth->execute();
        $growthDataRaw = $stmt_growth->fetchAll(PDO::FETCH_KEY_PAIR);
        $growthLabels = [];
        $growthValues = [];
        $begin = new DateTime('30 days ago');
        $end = new DateTime('tomorrow');
        foreach (new DatePeriod($begin, new DateInterval('P1D'), $end) as $date) {
            $k = $date->format("Y-m-d");
            $growthLabels[] = $k;
            $growthValues[] = $growthDataRaw[$k] ?? 0;
        }

        // Office Population
        $popOSDS = 0;
        $popCID = 0;
        $popSGOD = 0;
        $stmt_pop = $pdo->query("SELECT office_station FROM users WHERE is_active = 1");
        while ($u = $stmt_pop->fetch(PDO::FETCH_ASSOC)) {
            $cat = $office_map[strtoupper($u['office_station'])] ?? '';
            if ($cat === 'OSDS')
                $popOSDS++;
            elseif ($cat === 'CID')
                $popCID++;
            elseif ($cat === 'SGOD')
                $popSGOD++;
        }

        echo json_encode([
            'status' => 'success',
            'isHR' => true,
            'stats' => [
                'today_logins' => $todayLogins,
                'total_users' => $totalUsers,
                'new_registrations' => $newRegistrations,
                'active_today' => $activeToday
            ],
            'charts' => [
                'frequency' => ['labels' => $growthLabels, 'values' => $growthValues],
                'office' => ['osds' => $popOSDS, 'cid' => $popCID, 'sgod' => $popSGOD]
            ]
        ]);
        exit;
    }

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