<?php
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'ldp_db';

try {
    // Connect to MySQL server first to create database if not exists
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");

    // Connect to the specific database
    $pdo->exec("USE `$dbname`");

    // Create users table
    // Fields based on "Learning & Development Passbook"
    // Name, Office/Station, Position, Rating Period, Area of Specialization, Age, Sex
    // Plus id, username, password, role (admin/user)
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        office_station VARCHAR(100),
        position VARCHAR(100),
        rating_period VARCHAR(100),
        area_of_specialization VARCHAR(100),
        age INT,
        sex VARCHAR(20),
        role VARCHAR(20) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);

    // Create events table for "Recent Events Attended"
    $sql_events = "CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_name VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        status VARCHAR(50) DEFAULT 'Attended',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_events);

    // Create ld_activities table
    $sql_ld = "CREATE TABLE IF NOT EXISTS ld_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        date_attended TEXT,
        venue VARCHAR(255),
        modality VARCHAR(100),
        competency VARCHAR(255),
        type_ld VARCHAR(100),
        type_ld_others VARCHAR(255),
        conducted_by VARCHAR(255),
        approved_by VARCHAR(255),
        workplace_application TEXT,
        workplace_image_path LONGTEXT,
        organizer_signature_path VARCHAR(255),
        signature_path VARCHAR(255),
        reviewed_by_supervisor TINYINT(1) DEFAULT 0,
        recommending_asds TINYINT(1) DEFAULT 0,
        approved_sds TINYINT(1) DEFAULT 0,
        reviewed_at DATETIME NULL,
        recommended_at DATETIME NULL,
        approved_at DATETIME NULL,
        reflection TEXT,
        rating_period VARCHAR(100),
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_ld);

    // Create activity_logs table
    $sql_logs = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_logs);

} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}
?>