<?php
// Turn off error reporting to prevent red text from showing
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'connect.php'; // Include your database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Initialize variables
// Testing database
$household_size = '';
$address = '';
$success_message = '';
$error_message = '';

// Get user data from database
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        // User not found, redirect to login
        header("Location: login_register.php");
        exit();
    }
    
    // Assign household_size and address values
    $household_size = $userData['household_size'] ?? '';
    $address = $userData['address'] ?? '';
    
} catch (Exception $e) {
    // Silent error - don't display to user
    // You might want to log this error instead
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update user profile
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $household_size = $_POST['household_size'] ?? '';
        $address = $_POST['address'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, household_size = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $email, $household_size, $address, $_SESSION['user_id']]);
            
            // Update session username if changed
            $_SESSION['username'] = $name;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update the variables with new values
            $household_size = $userData['household_size'] ?? '';
            $address = $userData['address'] ?? '';
            
            echo "<script>alert('Profile updated successfully!');</script>";
        } catch (Exception $e) {
            echo "<script>alert('Error updating profile. Please try again.');</script>";
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo "<script>alert('All password fields are required.');</script>";
        } elseif ($new_password !== $confirm_password) {
            echo "<script>alert('New passwords do not match.');</script>";
        } elseif (strlen($new_password) < 6) {
            echo "<script>alert('New password must be at least 6 characters long.');</script>";
        } else {
            try {
                // First get the password from database
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $passwordData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($passwordData && isset($passwordData['password'])) {
                    // Verify current password
                    if (password_verify($current_password, $passwordData['password'])) {
                        // Hash new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Update password in database
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                        echo "<script>alert('Password changed successfully');</script>";
                    } else {
                       echo "<script>alert('Current password is incorrect');</script>";
                    }
                } else {
                    echo "<script>alert('Error retrieving password information.');</script>";
                }
            } catch (Exception $e) {
                echo "<script>alert('Error changing password. Please try again.');</script>";
            }
        }
    } elseif (isset($_POST['logout'])) {
        // Logout user
        session_destroy();
        header("Location: login_register.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePlate - MainpageAftlogin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2ecc71;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Read-only input styles */
        input[readonly], textarea[readonly] {
            background: #e9ecef !important;
            cursor: not-allowed !important;
        }

        input:read-write, textarea:read-write {
            background: #fff !important;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .logo i {
            color: white;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 25px;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 5px 10px;
            border-radius: var(--radius);
        }
        
        nav a:hover, nav a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .user-btn {
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            transition: background 0.3s;
        }
        
        .user-btn:hover {
            background: #3d8b40;
        }
        
        .user-btn i {
            margin-left: 8px;
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 15px;
            width: 200px;
            z-index: 100;
            display: none;
            margin-top: 10px;
        }
        
        .user-dropdown.active {
            display: block;
        }
        
        .user-dropdown .user-profile {
            display: flex;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 10px;
        }
        
        .user-dropdown .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .user-dropdown .user-details {
            color: var(--dark);
        }
        
        .user-dropdown .user-details .name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .user-dropdown .user-details .email {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .user-dropdown .menu-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            color: var(--dark);
            text-decoration: none;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .user-dropdown .menu-item:hover {
            color: var(--primary);
        }
        
        .user-dropdown .menu-item i {
            color: var(--gray);
            margin-right: 10px;
            width: 20px;
            transition: color 0.3s;
        }
        
        .user-dropdown .menu-item:hover i {
            color: var(--primary);
        }
        
        .user-dropdown .logout-btn {
            color: #dc3545 !important;
        }
        
        .user-dropdown .logout-btn:hover {
            color: #c82333 !important;
            background-color: rgba(220, 53, 69, 0.1) !important;
        }
        
        .user-dropdown .logout-btn i {
            color: #dc3545 !important;
        }
        
        .user-dropdown .logout-btn:hover i {
            color: #c82333 !important;
        }
        
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #27ae60;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ccc;
            color: var(--dark);
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
            color: var(--primary);
        }
        
        .btn-secondary {
            background: #ccc;
            color: var(--dark);
        }
        
        .btn-warning {
            background-color: #e0a800;
            color: #000;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-danger {
            background-color: var(--accent);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 30px;
            color: white;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        .hero {
            height: 85vh;
            background-size: cover;
            background-position: center;
            transition: background-image 0.5s ease-in-out;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Dark overlay for better text visibility */
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }
        
        .hero > .container {
            position: relative;
            z-index: 2;
        }
        
        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(255, 255, 255, 0.3);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
        }
        
        .carousel-control:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        .carousel-control.prev {
            left: 20px;
        }
        
        .carousel-control.next {
            right: 20px;
        }
        
        .carousel-control i {
            color: white;
            font-size: 24px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }
        
        .carousel-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }
        
        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .indicator.active {
            background-color: white;
            transform: scale(1.2);
        }
        
        .features {
            padding: 80px 0;
            background-color: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
            font-size: 2.2rem;
            color: var(--dark);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: var(--light);
            border-radius: var(--radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        /* Why Choose Us Section */
        .why-choose-us {
            padding: 80px 0;
            background-color: white;
        }
        
        .benefits-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .benefit-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }
        
        .benefit-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow);
        }
        
        .benefit-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
        }
        
        .benefit-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .benefit-card p {
            color: var(--gray);
            line-height: 1.6;
        }
        
        /* About Section Styles */
        .about {
            padding: 80px 0;
            background-color: #f5f5f5;
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        
        .about-text h2 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            color: var(--secondary);
        }
        
        .about-text p {
            margin-bottom: 20px;
            font-size: 1.1rem;
            line-height: 1.8;
        }
        
        .impact-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--dark);
        }
        
        .about-image {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .mission-section {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            padding: 60px 0;
            color: white;
            text-align: center;
            margin-top: 60px;
        }
        
        .mission-statement {
            max-width: 800px;
            margin: 0 auto;
            font-size: 1.4rem;
            line-height: 1.8;
            font-style: italic;
        }
        
        .mission-statement i {
            color: rgba(255, 255, 255, 0.7);
            font-size: 2rem;
            display: block;
            margin-bottom: 20px;
        }
        
        footer {
            background-color: var(--dark);
            color: white;
            padding: 40px 0;
            text-align: center;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer-section {
            flex: 1;
            min-width: 250px;
        }
        
        .footer-section h3 {
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        .footer-section a {
            color: white;
            text-decoration: none;
        }
        
        .footer-section a:hover {
            color: var(--primary);
        }
        
        .footer-bottom {
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        
        /* Ensure modal is displayed as flex when shown */
        .modal.show {
            display: flex !important;
        }
        
        .modal-content {
            background: #fff;
            padding: 0;
            border-radius: 12px;
            width: 450px;
            max-width: 90%;
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            max-height: 90vh;
            overflow: hidden;
            z-index: 10000; /* Even higher z-index */
        }
        
        .modal-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        
        .modal-header h2 {
            color: white;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            padding: 25px;
            overflow-y: auto;
            max-height: calc(90vh - 80px);
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: white;
            z-index: 10;
        }

        .close-modal:hover {
            color: #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: bold;
            margin-bottom: 6px;
            display: block;
            color: var(--dark);
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
        }
        
        .form-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .form-actions .btn {
            min-width: 120px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
            display: flex;
            align-items: center;
            max-width: 350px;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: #4CAF50;
            border-left: 5px solid #388E3C;
        }
        
        .notification.error {
            background: #F44336;
            border-left: 5px solid #D32F2F;
        }
        
        .notification.info {
            background: #2196F3;
            border-left: 5px solid #1976D2;
        }
        
        .notification i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .notification .close-btn {
            margin-left: 15px;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .about-content {
                grid-template-columns: 1fr;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .carousel-control {
                width: 40px;
                height: 40px;
            }
            
            .carousel-control i {
                font-size: 18px;
            }
            
            .impact-stats {
                grid-template-columns: 1fr;
            }
            
            .benefits-container {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Modal Styles */
        .profile-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .profile-avatar i {
            font-size: 30px;
            color: white;
        }

        .profile-details h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .profile-details p {
            margin: 0;
            color: #6c757d;
            font-size: 0.95rem;
        }

        /* Settings Modal Styles */
        .password-info-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .password-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .password-icon i {
            font-size: 30px;
            color: white;
        }

        .password-info h3 {
            margin: 0 0 5px 0;
            color: #856404;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .password-info p {
            margin: 0;
            color: #856404;
            font-size: 0.95rem;
            opacity: 0.8;
        }

        /* Enhanced form styling for profile and settings */
        #passwordModal .form-group,
        #settingsModal .form-group {
            margin-bottom: 20px;
        }

        #passwordModal .form-group label,
        #settingsModal .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        #passwordModal .form-group label i,
        #settingsModal .form-group label i {
            color: #4CAF50;
            width: 16px;
        }

        #passwordModal input[readonly],
        #settingsModal input[readonly] {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            color: #6c757d;
        }

        #passwordModal input[readonly]:focus,
        #settingsModal input[readonly]:focus {
            background-color: #ffffff;
            border-color: #4CAF50;
            color: #495057;
        }
    </style>
</head>
<body>
    <!-- Notification Popup -->
    <div class="notification" id="notification">
        <i id="notification-icon"></i>
        <span id="notification-message"></span>
        <button class="close-btn" id="close-notification">&times;</button>
    </div>

    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
                <span class="close-modal" id="closeSettings">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="password-info-card">
                        <div class="password-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="password-info">
                            <h3>Security Settings</h3>
                            <p>Update your password to keep your account secure</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="currentPassword"><i class="fas fa-key"></i> Current Password</label>
                        <input type="password" id="currentPassword" name="current_password" required class="form-control" placeholder="Enter your current password">
                    </div>
                    
                    <div class="form-group">
                        <label for="newPassword"><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" id="newPassword" name="new_password" required class="form-control" placeholder="Enter your new password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword"><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirm_password" required class="form-control" placeholder="Confirm your new password">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelPassword">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Profile Modal -->
    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> User Profile</h2>
                <span class="close-modal" id="closePassword">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="profileForm">
                    <div class="profile-info-card">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="profile-details">
                            <h3><?php echo htmlspecialchars($userData['username']); ?></h3>
                            <p><?php echo htmlspecialchars($userData['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="userName"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" id="userName" name="name" value="<?php echo htmlspecialchars($userData['username']); ?>" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="userEmail"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="userEmail" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="userHouseholdSize"><i class="fas fa-users"></i> Household Size</label>
                        <input type="number" id="userHouseholdSize" name="household_size" value="<?php echo htmlspecialchars($household_size); ?>" min="1" max="20" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="userAddress"><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea id="userAddress" name="address" rows="3" readonly class="form-control"><?php echo htmlspecialchars($address); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="editProfileBtn" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button type="submit" name="update_profile" id="saveProfileBtn" class="btn btn-primary" style="display:none;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" id="cancelEditBtn" class="btn btn-secondary" style="display:none;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" id="cancelSettingsBtn" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-leaf"></i>
                    <span>SavePlate</span>
                </div>
                
                <nav>
                    <ul>
                        <li><a href="index.php" class="active">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#why-choose-us">Why Choose Us</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </nav>
                <div class="user-info">
                    <button class="user-btn" id="userBtn">
                        <span id="userButtonText"><?php echo htmlspecialchars(explode(' ', $userData['username'])[0]); ?></span> <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-profile">
                            <div class="user-details">
                                <div class="name"><?php echo htmlspecialchars($userData['username']); ?></div>
                                <div class="email"><?php echo htmlspecialchars($userData['email']); ?></div>
                            </div>
                        </div>
                        <div class="menu-item" id="profileBtn">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </div>
                        <div class="menu-item" id="settingsBtn">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </div>
                        <form method="POST" action="" id="logoutForm">
                            <button type="submit" name="logout" class="menu-item logout-btn" style="background: none; border: none; width: 100%; text-align: left;">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <section class="hero" id="hero">
        <div class="carousel-control prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="carousel-control next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </div>
        
        <div class="carousel-indicators" id="indicators"></div>
        
        <div class="container">
            <h1>Reduce Food Waste, Save Money</h1>
            <p>SavePlate helps Malaysian households manage their food inventory, reduce waste, and donate surplus food to those in need.</p>
            <a href="#features" class="btn btn-primary">Learn More</a>
        </div>
    </section>
    
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Key Features</h2>
            
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Food Inventory Management</h3>
                    <p>Track your food items with expiry dates, quantities, and storage locations.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;" onclick="window.location.href='/bit216_assignment/add_item.php'"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-bell"></i>
                    <h3>Expiry Alerts</h3>
                    <p>Get notifications before your food items expire so you can use them in time.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;" onclick="window.location.href='/bit216_assignment/notification.php'"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-hands-helping"></i>
                    <h3>Donation Facilitation</h3>
                    <p>Easily donate surplus food to people in need or local charities.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;" onclick="window.location.href='/bit216_assignment/mydonation.php'"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-utensils"></i>
                    <h3>Meal Planning</h3>
                    <p>Plan meals based on your inventory to reduce waste and save money.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;" onclick="window.location.href='/bit216_assignment/meal_plan1.php'"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Food Analytics</h3>
                    <p>Track your food-saving progress with visual reports and insights.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;" onclick="window.location.href='/bit216_assignment/food_analytics_dashboard.php'"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Privacy Protection</h3>
                    <p>Your data is secure with robust privacy settings and 2FA options.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;"> Explore More </button>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Why Choose Us Section -->
    <section class="why-choose-us" id="why-choose-us">
        <div class="container">
            <h2 class="section-title">Why Choose SavePlate</h2>
            
            <div class="benefits-container">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Reduce Food Waste</h3>
                    <p>Our smart tracking system helps you use food before it expires, significantly reducing household waste.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Save Money</h3>
                    <p>By minimizing food waste and optimizing your grocery shopping, you can save hundreds annually.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Help Your Community</h3>
                    <p>Easily donate surplus food to those in need, making a positive impact in your community.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Track Your Progress</h3>
                    <p>Get detailed insights into your consumption patterns and see your environmental impact.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3>Plan Your Meals Wisely</h3>
                    <p>Plan weekly meals using your current food inventory, helping reduce waste and optimize ingredient usage before expiry.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Eat Healthier</h3>
                    <p>With better food tracking, you’ll always have fresh ingredients at hand — making it easier to prepare nutritious meals for you and your family.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="about" id="about">
        <div class="container">
            <h2 class="section-title">About SavePlate</h2>
            
            <div class="about-content">
                <div class="about-text">
                    <h2>Reducing Food Waste, Saving Money</h2>
                    <p>SavePlate is an innovative platform dedicated to tackling one of Malaysia's pressing issues - food waste. We provide households with smart tools to manage their food inventory, reduce waste, and ultimately save money while contributing to a more sustainable future.</p>
                    
                    <p>At SavePlate, we believe that reducing food waste shouldn't be complicated. Our mission is to empower Malaysian families with simple yet effective tools that make food management effortless, economical, and environmentally friendly.</p>
                    
                    <div class="impact-stats">
                        <div class="stat-box">
                            <div class="stat-number">40%</div>
                            <div class="stat-label">Average Reduction in Food Waste</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">RM150</div>
                            <div class="stat-label">Monthly Savings per Household</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">50K+</div>
                            <div class="stat-label">Meals Donated to Communities</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">100+</div>
                            <div class="stat-label">Tonnes of Food Saved from Landfills</div>
                        </div>
                    </div>
                </div>
                
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1571902943202-507ec2618e8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&h=600&q=80" alt="Food saving illustration">
                </div>
            </div>
            
            <div class="mission-section">
                <div class="mission-statement">
                    <i class="fas fa-quote-left"></i>
                    <p>When you use SavePlate, you're not just saving money - you're joining a community dedicated to creating a sustainable food culture in Malaysia. Together, we can make a significant impact on food waste reduction while keeping more money in your pocket.</p>
                </div>
            </div>
        </div>
    </section>
    
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>SavePlate</h3>
                    <p>Helping Malaysian households reduce food waste through intelligent inventory management and donation facilitation.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <p><a href="#features">Features</a></p>
                    <p><a href="#why-choose-us">Why Choose Us</a></p>
                    <p><a href="#about">About Us</a></p>
                    <p><a href="#">Privacy Policy</a></p>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-envelope"></i> info@saveplate.com</p>
                    <p><i class="fas fa-phone"></i> +60 3 1234 5678</p>
                    <p><i class="fas fa-map-marker-alt"></i> Kuala Lumpur, Malaysia</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 SavePlate. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Image carousel functionality
        const images = [
            "https://images.unsplash.com/photo-1542838132-92c53300491e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&h=900&q=80",
            "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&h=900&q=80",
            "https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&h=900&q=80" 
        ];

        let currentIndex = 0;
        const heroSection = document.getElementById("hero");
        const indicatorsContainer = document.getElementById("indicators");

        // Create indicators
        images.forEach((_, index) => {
            const indicator = document.createElement("div");
            indicator.classList.add("indicator");
            if (index === 0) indicator.classList.add("active");
            indicator.addEventListener("click", () => {
                currentIndex = index;
                updateCarousel();
            });
            indicatorsContainer.appendChild(indicator);
        });

        // Function to change slide
        function changeSlide(direction) {
            currentIndex = (currentIndex + direction + images.length) % images.length;
            updateCarousel();
        }

        // Update carousel display
        function updateCarousel() {
            heroSection.style.backgroundImage = `url('${images[currentIndex]}')`;
            
            // Update active indicator
            document.querySelectorAll(".indicator").forEach((indicator, index) => {
                if (index === currentIndex) {
                    indicator.classList.add("active");
                } else {
                    indicator.classList.remove("active");
                }
            });
        }

        // Auto-advance slides
        let slideInterval = setInterval(() => changeSlide(1), 5000);

        // Pause auto-advancement when hovering over carousel
        heroSection.addEventListener("mouseenter", () => {
            clearInterval(slideInterval);
        });

        heroSection.addEventListener("mouseleave", () => {
            slideInterval = setInterval(() => changeSlide(1), 5000);
        });

        // Initialize carousel
        updateCarousel();

        // Feature cards animation
        const featureCards = document.querySelectorAll('.feature-card');
        featureCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // User dropdown functionality
        const userBtn = document.getElementById('userBtn');
        const userDropdown = document.getElementById('userDropdown');

        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking elsewhere
        document.addEventListener('click', function() {
            userDropdown.classList.remove('active');
        });

        // Prevent dropdown from closing when clicking inside it
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Modal functionality
        const settingsModal = document.getElementById('settingsModal');
        const passwordModal = document.getElementById('passwordModal');
        const settingsBtn = document.getElementById('settingsBtn');
        const profileBtn = document.getElementById('profileBtn');
        const editProfileBtn = document.getElementById('editProfileBtn');
        const saveProfileBtn = document.getElementById('saveProfileBtn');
        const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');
        const profileForm = document.getElementById('profileForm');

        // Store original values for cancel operation
        let originalValues = {};

        // Open settings modal
        settingsBtn.addEventListener('click', function() {
            settingsModal.classList.add('show');
            userDropdown.classList.remove('active');
        });

        // Open profile modal
        profileBtn.addEventListener('click', function() {
            passwordModal.classList.add('show');
            userDropdown.classList.remove('active');
            
            // Store original values when opening modal
            originalValues = {
                name: document.getElementById('userName').value,
                email: document.getElementById('userEmail').value,
                household_size: document.getElementById('userHouseholdSize').value,
                address: document.getElementById('userAddress').value
            };
            
            // Ensure form is in read-only mode when opened
            setFormReadOnly(true);
        });

        // Enable editing
        editProfileBtn.addEventListener('click', function() {
            setFormReadOnly(false);
        });

        // Cancel editing
        document.getElementById('cancelEditBtn').addEventListener('click', function() {
            // Restore original values
            document.getElementById('userName').value = originalValues.name;
            document.getElementById('userEmail').value = originalValues.email;
            document.getElementById('userHouseholdSize').value = originalValues.household_size;
            document.getElementById('userAddress').value = originalValues.address;
            
            setFormReadOnly(true);
        });

        // Close modals
        document.getElementById('closeSettings').addEventListener('click', function() {
            settingsModal.classList.remove('show');
        });

        document.getElementById('closePassword').addEventListener('click', function() {
            passwordModal.classList.remove('show');
            setFormReadOnly(true); // Reset to read-only when closing
        });

        cancelSettingsBtn.addEventListener('click', function() {
            passwordModal.classList.remove('show');
            setFormReadOnly(true); // Reset to read-only when closing
        });

        // Function to close profile modal
        function closeProfileModal() {
            passwordModal.classList.remove('show');
            setFormReadOnly(true); // Reset to read-only when closing
        }

        // Helper function to set form read-only state
        function setFormReadOnly(isReadOnly) {
            const inputs = document.querySelectorAll('#profileForm input, #profileForm textarea');
            
            inputs.forEach(input => {
                input.readOnly = isReadOnly;
            });
            
            // Toggle button visibility
            if (isReadOnly) {
                editProfileBtn.style.display = 'inline-block';
                saveProfileBtn.style.display = 'none';
                cancelEditBtn.style.display = 'none';
                cancelSettingsBtn.style.display = 'inline-block';
            } else {
                editProfileBtn.style.display = 'none';
                saveProfileBtn.style.display = 'inline-block';
                cancelEditBtn.style.display = 'inline-block';
                cancelSettingsBtn.style.display = 'none';
            }
        }

        <?php
// Turn off error reporting to prevent red text from showing
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'connect.php'; // Include your database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Initialize variables
$household_size = '';
$address = '';
$success_message = '';
$error_message = '';

// Get user data from database
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        // User not found, redirect to login
        header("Location: login_register.php");
        exit();
    }
    
    // Assign household_size and address values
    $household_size = $userData['household_size'] ?? '';
    $address = $userData['address'] ?? '';
    
} catch (Exception $e) {
    // Silent error - don't display to user
    // You might want to log this error instead
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update user profile
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $household_size = $_POST['household_size'] ?? '';
        $address = $_POST['address'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, household_size = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $email, $household_size, $address, $_SESSION['user_id']]);
            
            // Update session username if changed
            $_SESSION['username'] = $name;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update the variables with new values
            $household_size = $userData['household_size'] ?? '';
            $address = $userData['address'] ?? '';
            
            $success_message = "Profile updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating profile. Please try again.";
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            try {
                // First get the password from database
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $passwordData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($passwordData && isset($passwordData['password'])) {
                    // Verify current password
                    if (password_verify($current_password, $passwordData['password'])) {
                        // Hash new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Update password in database
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Current password is incorrect.";
                    }
                } else {
                    $error_message = "Error retrieving password information.";
                }
            } catch (Exception $e) {
                $error_message = "Error changing password. Please try again.";
            }
        }
    } elseif (isset($_POST['logout'])) {
        // Logout user
        session_destroy();
        header("Location: login_register.php");
        exit();
    }
}
?><?php
// Turn off error reporting to prevent red text from showing
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'connect.php'; // Include your database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Initialize variables
$household_size = '';
$address = '';
$success_message = '';
$error_message = '';

// Get user data from database
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        // User not found, redirect to login
        header("Location: login_register.php");
        exit();
    }
    
    // Assign household_size and address values
    $household_size = $userData['household_size'] ?? '';
    $address = $userData['address'] ?? '';
    
} catch (Exception $e) {
    // Silent error - don't display to user
    // You might want to log this error instead
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update user profile
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $household_size = $_POST['household_size'] ?? '';
        $address = $_POST['address'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, household_size = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $email, $household_size, $address, $_SESSION['user_id']]);
            
            // Update session username if changed
            $_SESSION['username'] = $name;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update the variables with new values
            $household_size = $userData['household_size'] ?? '';
            $address = $userData['address'] ?? '';
            
            $success_message = "Profile updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating profile. Please try again.";
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            try {
                // First get the password from database
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $passwordData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($passwordData && isset($passwordData['password'])) {
                    // Verify current password
                    if (password_verify($current_password, $passwordData['password'])) {
                        // Hash new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Update password in database
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Current password is incorrect.";
                    }
                } else {
                    $error_message = "Error retrieving password information.";
                }
            } catch (Exception $e) {
                $error_message = "Error changing password. Please try again.";
            }
        }
    } elseif (isset($_POST['logout'])) {
        // Logout user
        session_destroy();
        header("Location: login_register.php");
        exit();
    }
}
?><?php
// Turn off error reporting to prevent red text from showing
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'connect.php'; // Include your database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Initialize variables
$household_size = '';
$address = '';
$success_message = '';
$error_message = '';

// Get user data from database
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        // User not found, redirect to login
        header("Location: login_register.php");
        exit();
    }
    
    // Assign household_size and address values
    $household_size = $userData['household_size'] ?? '';
    $address = $userData['address'] ?? '';
    
} catch (Exception $e) {
    // Silent error - don't display to user
    // You might want to log this error instead
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update user profile
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $household_size = $_POST['household_size'] ?? '';
        $address = $_POST['address'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, household_size = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $email, $household_size, $address, $_SESSION['user_id']]);
            
            // Update session username if changed
            $_SESSION['username'] = $name;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update the variables with new values
            $household_size = $userData['household_size'] ?? '';
            $address = $userData['address'] ?? '';
            
            $success_message = "Profile updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating profile. Please try again.";
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            try {
                // First get the password from database
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $passwordData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($passwordData && isset($passwordData['password'])) {
                    // Verify current password
                    if (password_verify($current_password, $passwordData['password'])) {
                        // Hash new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Update password in database
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Current password is incorrect.";
                    }
                } else {
                    $error_message = "Error retrieving password information.";
                }
            } catch (Exception $e) {
                $error_message = "Error changing password. Please try again.";
            }
        }
    } elseif (isset($_POST['logout'])) {
        // Logout user
        session_destroy();
        header("Location: login_register.php");
        exit();
    }
}
?>
        // Display notifications based on PHP variables
        <?php if (!empty($success_message)): ?>
            showNotification("<?php echo $success_message; ?>", "success");
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            showNotification("<?php echo $error_message; ?>", "error");
        <?php endif; ?>
    </script>
</body>

</html>
