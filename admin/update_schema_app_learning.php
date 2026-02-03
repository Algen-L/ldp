<?php
require '../includes/db.php';

try {
    $sql = "ALTER TABLE ld_activities 
            ADD COLUMN application_learning TEXT NULL AFTER reflection,
            ADD COLUMN application_file_path VARCHAR(255) NULL AFTER application_learning";

    $pdo->exec($sql);
    echo "Columns added successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>