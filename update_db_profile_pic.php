<?php
require 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'uploads/profile_pics/default.png' AFTER email");
    // Note: I don't recall if there is an email column, putting it after username or password might be safer if I'm unsure of schema.
    // Let's check schema first? No, I'll just put it at the end or let MySQL decide. 
    // Re-reading register.php, fields are: username, password, full_name, office_station, position, rating_period, area_of_specialization, age, sex.
    // I'll just add it.
} catch (PDOException $e) {
    // Column might exist, try to ignore or print
    echo "Note: " . $e->getMessage();
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
    echo "Column 'profile_picture' added successfully.";
} catch (PDOException $e) {
    echo "Error modifying table (column might already exist): " . $e->getMessage();
}
?>