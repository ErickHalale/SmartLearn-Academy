<?php
// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root'); // Change to your database username
if (!defined('DB_PASS')) define('DB_PASS', 'facebook123.'); // Change to your database password
if (!defined('DB_NAME')) define('DB_NAME', 'smartlearn'); // Using your existing database 'getway'

// Function to check and create table if it doesn't exist
if (!function_exists('checkAndCreateTable')) {
    function checkAndCreateTable($pdo) {
        try {
            // Create legacy users table if needed for compatibility
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                username VARCHAR(100) NOT NULL,
                school_name VARCHAR(255) NOT NULL,
                education_level ENUM('primary','secondary') NOT NULL,
                grade TINYINT UNSIGNED NULL,
                form TINYINT UNSIGNED NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_users_username (username),
                INDEX idx_users_education_level (education_level),
                INDEX idx_users_grade (grade),
                INDEX idx_users_form (form)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Ensure split user tables exist (avoid creating legacy 'users')
            $pdo->exec("CREATE TABLE IF NOT EXISTS primary_users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                username VARCHAR(100) NOT NULL,
                school_name VARCHAR(255) NOT NULL,
                education_level ENUM('primary','secondary') NOT NULL,
                grade TINYINT UNSIGNED NULL,
                form TINYINT UNSIGNED NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_users_username (username),
                INDEX idx_users_education_level (education_level),
                INDEX idx_users_grade (grade),
                INDEX idx_users_form (form)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                username VARCHAR(100) NOT NULL,
                school_name VARCHAR(255) NOT NULL,
                education_level ENUM('primary','secondary') NOT NULL,
                grade TINYINT UNSIGNED NULL,
                form TINYINT UNSIGNED NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_users_username (username),
                INDEX idx_users_education_level (education_level),
                INDEX idx_users_grade (grade),
                INDEX idx_users_form (form)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch(PDOException $e) {
            error_log("Table creation error: " . $e->getMessage());
        }
    }
}

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists, if not create it
    checkAndCreateTable($pdo);
    
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>