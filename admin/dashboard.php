<?php
session_start();
require '../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ../index.php");
    exit;
}



// Fetch all activities
$stmt = $pdo->query("
    SELECT ld.*, u.full_name, u.office_station 
    FROM ld_activities ld 
    JOIN users u ON ld.user_id = u.id 
    ORDER BY ld.created_at DESC
");
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LDP</title>
    <?php require 'includes/admin_head.php'; ?>
    <style>
        .events-table th,
        .events-table td {
            text-align: center;
            border: 1px solid #ddd;
            padding: 12px;
        }

        .events-table {
            border-collapse: collapse;
            width: 100%;
        }

        .events-table tr:hover {
            background-color: #f5f5f5;
        }

        .table-wrapper {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            display: block;
            /* Ensure it behaves like a block for scrolling */
        }

        .events-table thead th {
            position: sticky;
            top: 0;
            background-color: #f1f5f9;
            /* Light gray background to match header */
            z-index: 1;
        }
    </style>
</head>

<body>

    <div class="dashboard-container">
        <div class="sidebar">
            <?php require '../includes/sidebar.php'; ?>
        </div>

        <div class="main-content">
            <div class="passbook-container"
                style="width: 800px; max-width: 95%; margin: 20px auto; background-color: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);">
                <div class="header">
                    <h1>Admin Dashboard</h1>
                    <p>Overview of Learning & Development Activities</p>
                </div>

                <div class="stats-container">
                    <div class="stat-card">
                        <h3>
                            <?php echo $totalSubmissions; ?>
                        </h3>
                        <p>Total Submissions</p>
                    </div>
                    <div class="stat-card">
                        <h3>
                            <?php echo $totalUsers; ?>
                        </h3>
                        <p>Total Users</p>
                    </div>


                </div>

                <div style="margin-bottom: 15px; font-weight: bold; color: var(--primary-blue);">Recent Submissions
                </div>

                <div class="table-wrapper">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Office</th>
                                <th>Activity Title</th>
                                <th>Competency</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $act): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($act['date_attended']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($act['full_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($act['office_station']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($act['title']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($act['competency']); ?>
                                    </td>
                                    <td>
                                        <a href="../pages/view_activity.php?id=<?php echo $act['id']; ?>" class="btn"
                                            style="padding: 5px 10px; font-size: 0.8em; text-decoration: none; margin-top: 0;">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>

</html>