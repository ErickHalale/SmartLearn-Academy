<?php
include 'includes/config.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Redirect to dashboard if already logged in
function requireGuest() {
    if (isLoggedIn()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Get current user data
function getCurrentUser() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        // Decide source table based on session education_level
        $edu = isset($_SESSION['education_level']) ? $_SESSION['education_level'] : null;
        if ($edu === 'secondary') {
            $sql = "SELECT * FROM secondary_users WHERE id = :id";
        } else {
            // default to primary
            $sql = "SELECT * FROM primary_users WHERE id = :id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}
?>