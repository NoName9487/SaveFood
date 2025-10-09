<?php
session_start();

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Authenticate user and return user ID
 * This function should be called to get the current user's ID
 */
function authenticateUser() {
    if (!isLoggedIn()) {
        // Redirect to login page if not authenticated
        header('Location: login.php');
        exit();
    }
    
    return $_SESSION['user_id'];
}

/**
 * Get current user's information
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    // You can extend this to fetch user details from database
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'Unknown',
        'email' => $_SESSION['email'] ?? ''
    ];
}

/**
 * Logout user
 */
function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

/**
 * Require authentication for a page
 * Call this at the top of pages that require login
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Check if user has specific role/permission
 * You can extend this based on your user roles system
 * Testing the database
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Add your permission logic here
    // For now, just return true if user is logged in
    return true;
}
?>

