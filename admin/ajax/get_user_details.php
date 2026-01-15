<?php
// Prevent any unexpected output from corrupting JSON
ob_start();
error_reporting(E_ERROR | E_PARSE); // Only show critical errors, suppress notices/warnings

session_start();
require '../../includes/db.php';

// Set header for JSON response
header('Content-Type: application/json');

try {
    // Check if user is logged in as admin/super_admin/immediate_head
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'immediate_head'])) {
        throw new Exception('Unauthorized access');
    }

    if (!isset($_GET['user_id'])) {
        throw new Exception('User ID missing');
    }

    $user_id = (int) $_GET['user_id'];

    // 1. Fetch Basic User Info
    $stmt_user = $pdo->prepare("SELECT id, username, full_name, role, office_station, position, profile_picture, created_at FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // 2. Fetch Submission Stats
    $stmt_stats = $pdo->prepare("SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN approved_sds = 1 THEN 1 ELSE 0 END) as approved,
                                    SUM(CASE WHEN reviewed_by_supervisor = 0 THEN 1 ELSE 0 END) as pending
                                 FROM ld_activities WHERE user_id = ?");
    $stmt_stats->execute([$user_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

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

    // Assuming $timeline is passed via GET or defined elsewhere, e.g., $_GET['timeline']
    $timeline = $_GET['timeline'] ?? 'month'; // Default to month if not specified

    $rangeData = getDateRange($timeline);

    if ($timeline === 'week') {
        // Last 7 Days (Daily)
        $stmt_activity = $pdo->prepare("SELECT 
                                        DATE_FORMAT(created_at, '%Y-%m-%d') as time_key,
                                        COUNT(*) as count
                                       FROM ld_activities 
                                       WHERE user_id = ? AND created_at >= DATE((NOW() - INTERVAL 6 DAY))
                                       GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')");
    } elseif ($timeline === 'month') {
        // Last 4 Weeks (Weekly)
        $stmt_activity = $pdo->prepare("SELECT 
                                        YEARWEEK(created_at, 1) as time_key,
                                        COUNT(*) as count
                                       FROM ld_activities 
                                       WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
                                       GROUP BY YEARWEEK(created_at, 1)");
    } else {
        // Last 12 Months (Monthly)
        $stmt_activity = $pdo->prepare("SELECT 
                                        DATE_FORMAT(created_at, '%Y-%m') as time_key,
                                        COUNT(*) as count
                                       FROM ld_activities 
                                       WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                                       GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
    }

    if (!$stmt_activity->execute([$user_id])) {
        // Log error internally if possible, or just return basic error
        throw new Exception("Error executing activity query");
    }

    // Merge DB results into the empty range
    while ($row = $stmt_activity->fetch(PDO::FETCH_ASSOC)) {
        $k = $row['time_key'];
        if (isset($rangeData[$k])) {
            $rangeData[$k]['count'] = (int) $row['count'];
        }
    }

    // Re-index to array for JSON
    $activity_data = array_values($rangeData);

    // 4. Fetch Certificates
    $stmt_certs = $pdo->prepare("SELECT id, title, date_attended, certificate_path 
                                 FROM ld_activities 
                                 WHERE user_id = ? AND certificate_path IS NOT NULL 
                                 ORDER BY date_attended DESC");
    $stmt_certs->execute([$user_id]);
    $certificates = $stmt_certs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Recent Logs
    $stmt_logs = $pdo->prepare("SELECT action, created_at 
                                FROM activity_logs 
                                WHERE user_id = ? 
                                ORDER BY created_at DESC LIMIT 5");
    $stmt_logs->execute([$user_id]);
    $logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch Submissions
    $stmt_submissions = $pdo->prepare("SELECT id, title, type_ld, created_at, reviewed_by_supervisor, recommending_asds, approved_sds 
                                       FROM ld_activities 
                                       WHERE user_id = ? 
                                       ORDER BY created_at DESC");
    $stmt_submissions->execute([$user_id]);
    $submissions = $stmt_submissions->fetchAll(PDO::FETCH_ASSOC);

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