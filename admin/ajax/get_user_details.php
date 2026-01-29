<?php
// Prevent any unexpected output from corrupting JSON
ob_start();
error_reporting(E_ERROR | E_PARSE); // Only show critical errors, suppress notices/warnings

session_start();
// session_start already called on line 6
// db.php/init_repos.php will be handled in block below

// Set header for JSON response
header('Content-Type: application/json');

try {
    // Check if user is logged in as admin/super_admin/immediate_head/head_hr
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'immediate_head', 'head_hr'])) {
        throw new Exception('Unauthorized access');
    }

    if (!isset($_GET['user_id'])) {
        throw new Exception('User ID missing');
    }

    $user_id = (int) $_GET['user_id'];

    // Initialize repositories (adjust paths for ajax/ subdirectory)
    require_once '../../includes/init_repos.php';

    // 1. Fetch Basic User Info
    $user = $userRepo->getUserById($user_id);

    if (!$user) {
        throw new Exception('User not found');
    }

    // 2. Fetch Submission Stats
    $stats = $activityRepo->getUserStats($user_id);

    // Helper to generate date range
    function getDateRange($type)
    {
        $dates = [];
        $now = new DateTime();

        if ($type === 'week') {
            // Last 7 days (Daily)
            for ($i = 6; $i >= 0; $i--) {
                $d = clone $now;
                $d->modify("-$i days");
                $key = $d->format('Y-m-d');
                $label = $d->format('D'); // Mon, Tue...
                $dates[$key] = ['label' => $label, 'count' => 0];
            }
        } elseif ($type === 'month') {
            // Last 4 weeks (Weekly)
            for ($i = 3; $i >= 0; $i--) {
                $d = clone $now;
                $d->modify("-$i weeks");
                $d->modify('monday this week'); // Align to start of week
                $key = $d->format('oW'); // YearWeek
                $label = 'W' . $d->format('W'); // W42
                // Optional: legible label like "Oct 23"
                $dates[$key] = ['label' => $d->format('M d'), 'count' => 0];
            }
        } else {
            // Default: Last 12 months (Year view) -> Monthly
            for ($i = 11; $i >= 0; $i--) {
                $d = clone $now;
                $d->modify("first day of -$i months");
                $key = $d->format('Y-m');
                $dates[$key] = ['label' => $d->format('M'), 'count' => 0];
            }
        }
        return $dates;
    }

    // Timeline analysis
    $timeline = $_GET['timeline'] ?? 'week';
    $rangeData = getDateRange($timeline);
    $timelineResults = $activityRepo->getTimelineData($user_id, $timeline);

    // Merge DB results into the empty range
    foreach ($timelineResults as $row) {
        $k = $row['time_key'];
        if (isset($rangeData[$k])) {
            $rangeData[$k]['count'] = (int) $row['count'];
        }
    }

    // Re-index to array for JSON
    $activity_data = array_values($rangeData);

    // 4. Fetch Certificates (use status filter Approved as per logic or limit to ones with certificate_path)
    $all_user_activities = $activityRepo->getActivitiesByUser($user_id);
    $certificates = array_values(array_filter($all_user_activities, function ($a) {
        return !empty($a['certificate_path']);
    }));

    // 5. Fetch Recent Logs
    $logs = $logRepo->getLogsByUser($user_id, 5);

    // 6. Fetch Submissions
    $submissions = $all_user_activities; // Reuse the list to avoid duplicate query


    // Consolidate Data
    $response = [
        'user' => $user,
        'stats' => [
            'total' => (int) $stats['total'],
            'approved' => (int) $stats['approved'],
            'pending' => (int) $stats['pending'],
            'completion_rate' => $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0
        ],
        'activity_data' => $activity_data,
        'certificates' => $certificates,
        'submissions' => $submissions,
        'logs' => $logs
    ];

    // Clear buffer before output
    ob_clean();
    echo json_encode($response);

} catch (Exception $e) {
    ob_clean(); // Clear any partial output
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>