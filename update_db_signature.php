<?php
require 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE ld_activities ADD COLUMN signature_path VARCHAR(255) AFTER status");
    echo "Column 'signature_path' added successfully.";
} catch (PDOException $e) {
    echo "Error modifying table (column might already exist): " . $e->getMessage();
}
?>