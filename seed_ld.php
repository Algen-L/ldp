<?php
require 'db.php';

// Get a user
$stmt = $pdo->query("SELECT id FROM users LIMIT 1");
$user = $stmt->fetch();

if ($user) {
    $user_id = $user['id'];
    $activities = [
        [
            'title' => 'Advanced Project Management',
            'date_attended' => '2023-11-20',
            'venue' => 'Manila Conference Center',
            'modality' => 'Formal Training',
            'competency' => 'Leadership',
            'type_ld' => 'Managerial',
            'conducted_by' => 'PMI Philippines',
            'approved_by' => 'Director Smith',
            'status' => 'Pending'
        ],
        [
            'title' => 'Web Security Fundamentals',
            'date_attended' => '2023-12-05',
            'venue' => 'Online Zoom',
            'modality' => 'Job-Embedded Learning',
            'competency' => 'Technical Skills',
            'type_ld' => 'Technical',
            'conducted_by' => 'CyberSec Inc',
            'approved_by' => 'IT Head Jones',
            'status' => 'Approved'
        ]
    ];

    foreach ($activities as $act) {
        $sql = "INSERT INTO ld_activities (user_id, title, date_attended, venue, modality, competency, type_ld, conducted_by, approved_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            $act['title'],
            $act['date_attended'],
            $act['venue'],
            $act['modality'],
            $act['competency'],
            $act['type_ld'],
            $act['conducted_by'],
            $act['approved_by'],
            $act['status']
        ]);
    }
    echo "L&D Activities seeded for user ID: $user_id";
} else {
    echo "No users found.";
}
?>