<?php
require 'includes/db.php';

try {
    $sql = "ALTER TABLE ld_activities ADD COLUMN type_ld_others VARCHAR(255) AFTER type_ld";
    $pdo->exec($sql);
    echo "Column 'type_ld_others' added successfully.";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage();
}
?>