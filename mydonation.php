<?php
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
        
        // Safely assign household_size and address values (handle NULL values)
        $household_size = $userData['household_size'] ?? '';
        $address = $userData['address'] ?? '';
        
    } catch (Exception $e) {
        die("Error retrieving user data: " . $e->getMessage());
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $newName = $_POST['name'];
        $newEmail = $_POST['email'];
        $newHouseholdSize = $_POST['household_size'];
        $newAddress = $_POST['address'];

        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, household_size = ?, address = ? WHERE id = ?");
        $stmt->execute([$newName, $newEmail, $newHouseholdSize, $newAddress, $_SESSION['user_id']]);
        echo "<script>alert('Profile changed successfully');</script>";

        // Refresh user data
        $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        $household_size = $userData['household_size'] ?? '';
        $address = $userData['address'] ?? '';
    }


        // Change password (PDO-based, consistent with getConnection())
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                echo "<script>alert('All password fields are required');</script>";
            } elseif ($new_password !== $confirm_password) {
                echo "<script>alert('New passwords do not match');</script>";
            } elseif (strlen($new_password) < 6) {
                echo "<script>alert('New password must be at least 6 characters long');</script>";
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $passwordData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($passwordData && isset($passwordData['password']) && password_verify($current_password, $passwordData['password'])) {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $update->execute([$hashed, $_SESSION['user_id']]);
                        echo "<script>alert('Password changed successfully');</script>";
                    } else {
                        echo "<script>alert('Current password is incorrect');</script>";
                    }
                } catch (Exception $e) {
                    echo "<script>alert('Error changing password. Please try again.');</script>";
                }
            }
        }
        
        // Handle logout
        if (isset($_POST['logout'])) {
            // Destroy session and redirect to mainpage_aftlogin.php
            session_destroy();
            header("Location: mainpage_aftlogin.php");
            exit();
        }

    $current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePlate - Mydonation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --secondary: #FF9800;
            --secondary-dark: #F57C00;
            --accent: #8BC34A;
            --danger: #F44336;
            --warning: #FFC107;
            --success: #4CAF50;
            --info: #2196F3;
            --light-bg: #F1F8E9;
            --dark-text: #1B5E20;
            --light-text: #FFFFFF;
            --card-bg: #FFFFFF;
            --shadow: 0 4px 12px rgba(76, 175, 80, 0.15);
            --shadow-hover: 0 8px 20px rgba(76, 175, 80, 0.25);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F5F5F5;
            color: #2C3E50;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: #2C3E50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: width 0.3s ease;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 600;
            cursor: pointer;
        }

        .sidebar-header i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .brand-text { display: inline; }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }

        .nav-link:hover {
            background-color: #34495E;
            color: white;
            border-left-color: var(--primary);
        }

        .nav-link.active {
            background-color: var(--primary);
            color: white;
            border-left-color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .nav-link .label { white-space: nowrap; }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2C3E50;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .form-group input[readonly],
        .form-group textarea[readonly] {
            background-color: #e9ecef;
            color: #6c757d;
        }

        .actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-warning::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
        }

        .btn-warning:hover::before {
            left: 100%;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Notification dot on nav bell */
        .nav-link .notif-dot {
            position: absolute;
            top: 6px;
            left: 36px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 10px;
            background: #F44336;
            color: #fff;
            font-size: 10px;
            line-height: 16px;
            text-align: center;
            font-weight: 700;
        }
        .sidebar.collapsed .nav-link .notif-dot { left: 38px; }

        /* Collapsed state */
        .sidebar.collapsed { width: 72px; }
        .sidebar.collapsed .brand-text { display: none; }
        .sidebar.collapsed .nav-link { justify-content: center; }
        .sidebar.collapsed .nav-link .label { display: none; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            position: relative; /* Added for proper z-index context */
            z-index: 1; /* Lower than modal */
        }
        .sidebar.collapsed + .main-content { margin-left: 72px; }
        body.sidebar-collapsed .main-content { margin-left: 72px; }

        .container {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
            margin-bottom: 30px;
            flex: 1;
            z-index: 1; /* Lower than modal */
        }



        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 30px 0 24px 0;
            color: var(--dark-text);
            padding: 0 20px;
        }

        .section-title i {
            color: var(--primary);
            font-size: 24px;
        }

        /* Page Header Styling */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--light-text);
            padding: 30px;
            text-align: left;
            position: relative;
            overflow: hidden;
            margin-bottom: 0;
            border-radius: 16px 16px 0 0;
        }

        .page-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M20,20 L80,20 L80,80 L20,80 Z" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></svg>');
            background-size: 30px 30px;
            opacity: 0.3;
        }

        .page-title {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }

        .page-title::before {
            content: "🍃";
            margin-right: 10px;
        }

        .page-subtitle {
            font-size: 20px;
            opacity: 0.9;
            margin-left: 65px;
            position: relative;
            z-index: 1;
        }

        /* User Menu Styling */
        .user-menu {
            position: absolute;
            top: 30px;
            right: 30px;
            z-index: 1000;
            padding-top: 35px;
            padding-right: 40px;
        }

        .user-btn {
            background: var(--primary);
            color: white;
            border: 2px solid var(--primary-dark);
            padding: 10px 18px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .user-btn:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .user-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 10px;
            background: #fff;
            border-radius: 10px;
            box-shadow: var(--shadow-hover);
            width: 260px;
            overflow: hidden;
            z-index: 5000;
        }

        .user-dropdown.show {
            display: block;
        }


        .user-profile {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .user-profile .name {
            font-size: 1rem;
            font-weight: 600;
            color: #2C3E50;
        }

        .user-profile .email {
            font-size: 0.9rem;
            color: #7F8C8D;
            margin-top: 4px;
        }

        .user-dropdown .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #2C3E50;
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .user-dropdown .menu-item:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .logout-btn {
            color: var(--danger) !important;
            font-weight: 600;
        }

        /* Profile Modal Styling - FIXED Z-INDEX */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999; /* Very high z-index to ensure it's on top */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
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
            width: 500px;
            max-width: 90%;
            position: relative;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
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
            padding: 30px;
            overflow-y: auto;
            max-height: calc(90vh - 80px);
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: white;
            z-index: 10;
        }

        .close-btn:hover {
            color: #f0f0f0;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .form-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
            display: block;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 25px;
        }

        /* Content area spacing */
        .tab-content {
            padding: 20px 0;
        }

        /* Footer Styles */
        .footer {
            background-color: #2C3E50;
            color: white;
            padding: 20px 30px;
            text-align: center;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            position: relative;
            z-index: 1; /* Lower than modal */
        }

        .sidebar.collapsed ~ .footer {
            margin-left: 72px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-copyright {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .footer-social {
            display: flex;
            gap: 15px;
        }

        .footer-social a {
            color: white;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .footer-social a:hover {
            color: var(--primary-light);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .footer {
                margin-left: 0;
                padding: 20px;
            }

            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .mobile-menu-toggle {
                display: block;
            }
            
            .category-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .category-card {
                padding: 20px;
            }
            
            .category-card i {
                font-size: 2rem;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .section-title {
                margin: 20px 0 20px 0;
                padding: 0 15px;
            }
            
            .category-selection {
                padding: 0 15px;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .food-items-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 15px;
            }
            
            .food-item-card {
                padding: 15px;
            }
            
            .food-item-card i {
                font-size: 1.5rem;
            }
        }

        /* Donation Popup Styles */
        .donation-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        }

        .donation-popup-content {
            background: white;
            border-radius: 16px;
            padding: 0;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .donation-popup-header {
            background: linear-gradient(135deg, var(--success) 0%, #2E7D32 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .donation-popup-header h3 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .donation-popup-content form {
            padding: 30px;
        }

        .donation-item-info {
            background: var(--light-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border: 2px solid var(--primary-light);
        }

        .donation-item-info h4 {
            margin: 0 0 10px 0;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }

        .donation-item-info p {
            margin: 0;
            color: var(--dark-text);
            font-size: 14px;
        }

        .donation-popup-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .donation-popup-actions .btn {
            min-width: 120px;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 15px;
            margin-left: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .status-badge.available {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border-color: #2e7d32;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border-color: #b45309;
        }

        .status-badge.claimed {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            border-color: #1565C0;
        }

        /* Donation Card Styles */
        .donation-card {
            border: 2px solid rgba(76, 175, 80, 0.1);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06), 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .donation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #4CAF50 0%, #45a049 50%, #4CAF50 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .donation-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 8px 20px rgba(76, 175, 80, 0.15);
            border-color: #4CAF50;
        }

        .donation-card:hover::before {
            opacity: 1;
        }

        .donation-card .card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 50%, #2e7d32 100%);
            position: relative;
            overflow: hidden;
        }

        .donation-card .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        /* Available for Claim Donation Styles */
        .donation-card.available-for-claim {
            border-color: rgba(76, 175, 80, 0.2);
            background: linear-gradient(135deg, #f8fff8 0%, #ffffff 100%);
        }

        .donation-card.available-for-claim:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 8px 20px rgba(76, 175, 80, 0.15);
        }

        .donation-card.available-for-claim .card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 50%, #2e7d32 100%);
            position: relative;
        }


        .donation-card.available-for-claim .card-actions .btn-success {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            border: none;
            padding: 12px 24px;
            font-weight: 700;
            border-radius: 25px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .donation-card.available-for-claim .card-actions .btn-success::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .donation-card.available-for-claim .card-actions .btn-success:hover {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }

        .donation-card.available-for-claim .card-actions .btn-success:hover::before {
            left: 100%;
        }

        .donation-card.available-for-claim .detail-value {
            color: #4CAF50;
            font-weight: 600;
        }


        /* Inventory Grid and Cards */
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .inventory-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06), 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(76, 175, 80, 0.1);
            position: relative;
        }

        .inventory-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 8px 20px rgba(76, 175, 80, 0.15);
            border-color: #4CAF50;
        }

        .card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 50%, #2e7d32 100%);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        .item-name {
            font-size: 1.4rem;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .category-badge {
            background: rgba(255, 255, 255, 0.25);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
            text-transform: capitalize;
        }

        .category-badge:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
        }

        .item-details {
            padding: 25px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 18px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(76, 175, 80, 0.1);
            transition: all 0.3s ease;
        }

        .detail-row:hover {
            background: rgba(76, 175, 80, 0.05);
            border-radius: 8px;
            padding: 12px 15px;
            margin: 0 -15px 18px -15px;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: #4CAF50;
            font-weight: 700;
            font-size: 1rem;
        }

        .card-actions {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid rgba(76, 175, 80, 0.1);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-success {
            background-color: var(--success);
            color: white;
            border: 1px solid var(--success);
        }

        .btn-success:hover {
            background-color: #2E7D32;
            border-color: #2E7D32;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-danger::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
        }

        .btn-danger:hover::before {
            left: 100%;
        }

        .btn-info {
            background-color: var(--info);
            color: white;
            border: 1px solid var(--info);
        }

        .btn-info:hover {
            background-color: #1976D2;
            border-color: #1976D2;
        }

        /* Loading and Empty States */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 10px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #666;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 25px;
            color: #cbd5e0;
            opacity: 0.8;
            display: block;
        }

        .empty-state h3 {
            margin-bottom: 15px;
            color: #2d3748;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .empty-state p {
            margin-bottom: 35px;
            color: #718096;
            font-size: 1rem;
            line-height: 1.6;
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
        }

        .empty-state .btn {
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .empty-state .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 0 20px;
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px;
            overflow: hidden;
        }

        .tab-nav button {
            flex: 1;
            padding: 15px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-nav button.active {
            background: var(--primary);
            color: white;
        }

        .tab-nav button:hover:not(.active) {
            background: #e9ecef;
        }

        .tab-content {
            display: none;
            padding: 20px;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .donation-popup-content {
                width: 95%;
                margin: 10px;
            }

            .donation-popup-actions {
                flex-direction: column;
            }

            .donation-popup-actions .btn {
                width: 100%;
            }

            .inventory-grid {
                grid-template-columns: 1fr;
                padding: 15px;
            }

            .tab-nav {
                flex-direction: column;
            }

            .tab-nav button {
                border-bottom: 1px solid #dee2e6;
            }

            .tab-nav button:last-child {
                border-bottom: none;
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
        #profileModal .form-group,
        #settingsModal .form-group {
            margin-bottom: 20px;
        }

        #profileModal .form-group label,
        #settingsModal .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        #profileModal .form-group label i,
        #settingsModal .form-group label i {
            color: #4CAF50;
            width: 16px;
        }

        #profileModal input[readonly],
        #settingsModal input[readonly] {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            color: #6c757d;
        }

        #profileModal input[readonly]:focus,
        #settingsModal input[readonly]:focus {
            background-color: #ffffff;
            border-color: #4CAF50;
            color: #495057;
        }

        /* Pickup Modal Styles */
        .pickup-info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 1px solid #90caf9;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .pickup-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }

        .pickup-icon i {
            font-size: 30px;
            color: white;
        }

        .pickup-info h3 {
            margin: 0 0 5px 0;
            color: #1565C0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .pickup-info p {
            margin: 0;
            color: #1565C0;
            font-size: 0.95rem;
            opacity: 0.8;
        }

        /* Enhanced form styling for pickup modal */
        #pickup-message-modal .form-group {
            margin-bottom: 25px;
        }

        #pickup-message-modal .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            font-weight: 600;
            color: #495057;
        }

        #pickup-message-modal .form-group label i {
            color: #2196F3;
            width: 16px;
        }

        #pickup-message-modal .form-control {
            width: 100%;
            padding: 15px 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }

        #pickup-message-modal .form-control:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        /* Form actions spacing */
        #pickup-message-modal .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        #pickup-message-modal .form-actions .btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            min-width: 140px;
        }

        /* Professional Edit Donation Modal Styles */
        .edit-donation-modal {
            width: 600px;
            max-width: 95%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: none;
        }

        .edit-item-info-card {
            background: linear-gradient(135deg, #f8fffe 0%, #f0fdf4 100%);
            border: 1px solid #d1fae5;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
            overflow: hidden;
        }

        .edit-item-info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981 0%, #059669 50%, #047857 100%);
        }

        .edit-item-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.25);
            position: relative;
        }

        .edit-item-icon::after {
            content: '';
            position: absolute;
            inset: 2px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            border-radius: 14px;
            pointer-events: none;
        }

        .edit-item-icon i {
            font-size: 32px;
            color: white;
            z-index: 1;
        }

        .edit-item-info h3 {
            margin: 0 0 8px 0;
            color: #064e3b;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .edit-item-info p {
            margin: 0;
            color: #065f46;
            font-size: 1rem;
            opacity: 0.8;
            line-height: 1.5;
        }

        .form-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-section-title {
            color: #111827;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 24px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
            position: relative;
        }

        .form-section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }

        .form-section-title i {
            color: #10b981;
            font-size: 1.1rem;
        }

        /* Professional form styling for edit modal */
        #editDonationModal .form-group {
            margin-bottom: 28px;
        }

        #editDonationModal .form-group label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        #editDonationModal .form-group label i {
            color: #10b981;
            width: 18px;
            font-size: 16px;
        }

        #editDonationModal .form-group input,
        #editDonationModal .form-group textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            background: #ffffff;
            transition: all 0.2s ease;
            font-family: inherit;
            color: #111827;
        }

        #editDonationModal .form-group input:focus,
        #editDonationModal .form-group textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: #f9fafb;
        }

        #editDonationModal .form-group input::placeholder,
        #editDonationModal .form-group textarea::placeholder {
            color: #9ca3af;
            font-style: italic;
        }

        #editDonationModal .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        /* Professional actions styling */
        #editDonationModal .actions {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 16px;
            justify-content: flex-end;
        }

        #editDonationModal .actions .btn {
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            min-width: 150px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        #editDonationModal .actions .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        #editDonationModal .actions .btn:hover::before {
            left: 100%;
        }

        #editDonationModal .actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        #editDonationModal .actions .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        #editDonationModal .actions .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        #editDonationModal .actions .btn-secondary {
            background: #ffffff;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        #editDonationModal .actions .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            color: #374151;
        }

        /* Professional item details card */
        .professional-details-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .professional-details-card h4 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }

        .professional-details-card h4::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }

        .professional-details-card h4 i {
            color: #10b981;
            font-size: 1rem;
        }

        .professional-details-card .item-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .professional-details-card .item-detail {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 16px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .professional-details-card .item-detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .professional-details-card .item-detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        /* Professional Delete Confirmation Modal Styles */
        .delete-modal {
            width: 500px;
            max-width: 95%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: none;
        }

        .delete-modal .modal-content {
            display: flex;
            flex-direction: column;
        }

        .delete-modal .modal-header {
            width: 100%;
            margin: 0;
            flex-shrink: 0;
        }

        .delete-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
        }

        .delete-warning-card {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }

        .delete-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px auto;
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.25);
        }

        .delete-icon i {
            font-size: 36px;
            color: white;
        }

        .delete-question {
            font-size: 1.25rem;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .delete-item-name {
            background: #ffffff;
            border: 2px solid #fecaca;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #dc2626;
            margin: 0 auto 20px auto;
            display: inline-block;
            min-width: 120px;
        }

        .delete-warning {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.95rem;
            color: #dc2626;
            font-weight: 500;
        }

        .delete-warning i {
            font-size: 1rem;
        }

        /* Professional actions styling for delete modal */
        #deleteConfirmModal .actions {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        #deleteConfirmModal .actions .btn {
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            min-width: 150px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        #deleteConfirmModal .actions .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        #deleteConfirmModal .actions .btn:hover::before {
            left: 100%;
        }

        #deleteConfirmModal .actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        #deleteConfirmModal .actions .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
        }

        #deleteConfirmModal .actions .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        }

        #deleteConfirmModal .actions .btn-secondary {
            background: #ffffff;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        #deleteConfirmModal .actions .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            color: #374151;
        }

        /* Claimer Details Modal Styles */
        .claimer-info {
            padding: 20px 0;
        }

        .claimer-info .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .claimer-info .info-row label {
            font-weight: 600;
            color: #495057;
            min-width: 140px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .claimer-info .info-row label i {
            color: #007bff;
            width: 16px;
        }

        .claimer-info .info-row span {
            color: #212529;
            flex: 1;
            word-break: break-word;
        }

        .claimer-info .info-row:last-child {
            margin-bottom: 0;
        }

        /* Responsive design for claimer info */
        @media (max-width: 768px) {
            .claimer-info .info-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .claimer-info .info-row label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }

    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2 id="brand">
                <i class="fas fa-leaf"></i>
                <span class="brand-text">SavePlate</span>
            </h2>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/bit216_assignment/mainpage_aftlogin.php" class="nav-link <?php echo $current_page == 'mainpage_aftlogin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span class="label">Home</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/add_item.php" class="nav-link <?php echo $current_page == 'add_item.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span class="label">Add Item</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/view_inventory.php" class="nav-link <?php echo $current_page == 'view_inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-archive"></i>
                    <span class="label">View Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/mydonation.php" class="nav-link <?php echo $current_page == 'mydonation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    <span class="label">My Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/claimed_donation.php" class="nav-link <?php echo $current_page == 'available_donation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span class="label">Claimed Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/meal_plan1.php" class="nav-link <?php echo $current_page == 'meal_plan1.php' ? 'active' : ''; ?>">
                    <i class="fas fa-utensils"></i>
                    <span class="label">Meal Plan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/food_analytics_dashboard.php" class="nav-link <?php echo $current_page == 'food_analytics_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-poll"></i>
                    <span class="label">Food Analysis</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/notification.php" class="nav-link <?php echo $current_page == 'notification.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span class="label">Notifications</span>
                    <span class="notif-dot" id="sidebar-notification-count" style="display: none;">0</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- User Menu -->
        <div class="user-menu">
            <button class="user-btn" id="userBtn">
                <?php echo htmlspecialchars(explode(' ', $userData['username'])[0]); ?> 
                <i class="fas fa-chevron-down"></i>
            </button>

            <div class="user-dropdown" id="userDropdown">
                <div class="user-profile">
                    <div class="name"><?php echo htmlspecialchars($userData['username']); ?></div>
                    <div class="email"><?php echo htmlspecialchars($userData['email']); ?></div>
                </div>
                <hr>
                <a href="javascript:void(0)" class="menu-item" onclick="openProfileModal()">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="javascript:void(0)" class="menu-item" onclick="openSettingsModal()"><i class="fas fa-cog"></i> Settings</a>
                <form method="POST" action="">
                    <button type="submit" name="logout" class="menu-item logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Donations</h1>
                <p class="page-subtitle">Manage your donations and help reduce food waste</p>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="showTab('my-donations')">
                    <i class="fas fa-heart"></i>
                    My Donations
                </button>
                <button class="tab-btn" onclick="showTab('available-donations')">
                    <i class="fas fa-hand-holding-heart"></i>
                    Available Donations
                </button>
            </div>

            <!-- My Donations Tab -->
            <div id="my-donations" class="tab-content active">
                <div class="section-title">
                    <i class="fas fa-heart"></i>
                    <h2>Your Donations</h2>
                </div>
                <p style="padding: 0 20px; margin-bottom: 20px; color: #666;">View and manage your active food donations. These are items you've converted from your inventory to help reduce food waste.</p>
                

                <!-- Active Donations Section -->
                <div style="margin: 20px;">
                    
                    <!-- Search and Filter Controls -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
                        <!-- Top Row: Search and Filters -->
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
                            <div style="flex: 1; min-width: 250px;">
                                <input type="text" id="donations-search" placeholder="Search by item name, category, donor, or location..." 
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                            </div>
                            <div>
                                <select id="donations-category-filter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px;">
                                    <option value="">All Categories</option>
                                    <option value="fruits_vegetables">Fruits & Vegetables</option>
                                    <option value="dairy_eggs">Dairy & Eggs</option>
                                    <option value="meat_fish">Meat & Fish</option>
                                    <option value="grains_cereals">Grains & Cereals</option>
                                    <option value="canned_goods">Canned Goods</option>
                                    <option value="beverages">Beverages</option>
                                    <option value="snacks">Snacks</option>
                                    <option value="condiments">Condiments</option>
                                    <option value="frozen_foods">Frozen Foods</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <select id="donations-status-filter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 120px;">
                                    <option value="">All Status</option>
                                    <option value="available">Available</option>
                                    <option value="claimed">Claimed</option>
                                </select>
                            </div>
                            <div>
                                <button onclick="clearDonationsFilters()" class="btn btn-secondary btn-sm" style="background: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 5px; font-size: 14px;">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                        
                        <!-- Bottom Row: Refresh Button and Results Count -->
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <button onclick="loadDonations()" class="btn btn-primary btn-sm" style="background: var(--primary); color: white; border: none; padding: 8px 12px; border-radius: 5px; font-size: 14px;">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <span id="donations-count" style="color: #666; font-size: 14px;">Loading donations...</span>
                        </div>
                    </div>
                    
                    <div id="donations-container">
                        <div class="loading"><i class="fas fa-spinner"></i> Loading donations...</div>
                    </div>
                </div>
            </div>

            <!-- Available Donations Tab -->
            <div id="available-donations" class="tab-content">
                <div class="section-title">
                    <i class="fas fa-hand-holding-heart"></i>
                    <h2>Available Donations</h2>
                </div>
                <p style="padding: 0 20px; margin-bottom: 20px; color: #666;">Browse and claim available food donations from other users in your community. Help reduce food waste by claiming items you need.</p>
                
                <!-- Search and Filter Controls -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px; border-left: 4px solid #2196F3;">
                    <!-- Top Row: Search and Filters -->
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
                        <div style="flex: 1; min-width: 250px;">
                            <input type="text" id="available-donations-search" placeholder="Search by item name, category, donor, or location..." 
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                        </div>
                        <div>
                            <select id="available-donations-category-filter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px;">
                                <option value="">All Categories</option>
                                <option value="fruits_vegetables">Fruits & Vegetables</option>
                                <option value="dairy_eggs">Dairy & Eggs</option>
                                <option value="meat_fish">Meat & Fish</option>
                                <option value="grains_cereals">Grains & Cereals</option>
                                <option value="canned_goods">Canned Goods</option>
                                <option value="beverages">Beverages</option>
                                <option value="snacks">Snacks</option>
                                <option value="condiments">Condiments</option>
                                <option value="frozen_foods">Frozen Foods</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <select id="available-donations-expiry-filter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px;">
                                <option value="">All Expiry Times</option>
                                <option value="urgent">Expires within 3 days</option>
                                <option value="soon">Expires within 7 days</option>
                                <option value="normal">Expires after 7 days</option>
                            </select>
                        </div>
                        <div>
                            <button onclick="clearAvailableDonationsFilters()" class="btn btn-secondary btn-sm" style="background: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 5px; font-size: 14px;">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                    
                    <!-- Bottom Row: Refresh Button and Results Count -->
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <button onclick="loadAvailableDonations()" class="btn btn-primary btn-sm" style="background: var(--primary); color: white; border: none; padding: 8px 12px; border-radius: 5px; font-size: 14px;">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <span id="available-donations-count" style="color: #666; font-size: 14px;">Loading available donations...</span>
                    </div>
                </div>
                
                <div id="available-donations-container" style="margin-left: 0px;">
                    <div class="loading"><i class="fas fa-spinner"></i> Loading available donations...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> User Profile</h2>
                <span class="close-btn" onclick="closeProfileModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="profileForm">
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
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($userData['username']); ?>" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Household Size</label>
                        <input type="number" name="household_size" value="<?php echo htmlspecialchars($household_size); ?>" min="1" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" readonly class="form-control"><?php echo htmlspecialchars($address); ?></textarea>
                    </div>
                    
                    <div class="actions">
                        <button type="button" id="editProfileBtn" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button type="submit" name="update_profile" id="saveProfileBtn" class="btn btn-primary" style="display:none;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" onclick="closeProfileModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
                <span class="close-btn" onclick="closeSettingsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
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
                        <label><i class="fas fa-key"></i> Current Password</label>
                        <input type="password" name="current_password" required class="form-control" placeholder="Enter your current password">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="new_password" required class="form-control" placeholder="Enter your new password">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" name="confirm_password" required class="form-control" placeholder="Confirm your new password">
                    </div>
                    
                    <div class="actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                        <button type="button" onclick="closeSettingsModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Donation Popup -->
    <div id="donation-popup" class="donation-popup" style="display: none;">
        <div class="donation-popup-content">
            <div class="donation-popup-header">
                <h3><i class="fas fa-hand-holding-heart"></i> Convert to Donation</h3>
                <button id="close-donation-popup" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="donationForm">
                <input type="hidden" id="donation_item_id" name="item_id">
                
                <div class="donation-item-info">
                    <h4 id="donation-item-name"></h4>
                    <p id="donation-item-details"></p>
                </div>
                
                <div class="form-group">
                    <label for="pickup_location"><i class="fas fa-map-marker-alt"></i> Pickup Location *</label>
                    <input type="text" id="pickup_location" name="pickup_location" class="form-control" placeholder="e.g., 123 Main St, City, State" required>
                </div>
                
                <div class="form-group">
                    <label for="availability"><i class="fas fa-clock"></i> Availability *</label>
                    <input type="text" id="availability" name="availability" class="form-control" placeholder="e.g., Weekdays 9-5, Weekends 10-2" required>
                </div>
                
                <div class="form-group">
                    <label for="phone_number"><i class="fas fa-phone"></i> Phone Number *</label>
                    <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="e.g., 123-456-7890" required>
                </div>
                
                <div class="form-group">
                    <label for="additional_notes"><i class="fas fa-sticky-note"></i> Additional Notes</label>
                    <textarea id="additional_notes" name="additional_notes" class="form-control" rows="3" placeholder="Any special instructions or notes for pickup..."></textarea>
                </div>
                
                <div class="donation-popup-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-heart"></i> Create Donation
                    </button>
                    <button type="button" id="cancel-donation" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pickup Message Modal -->
    <div id="pickup-message-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-check"></i> Schedule Pickup</h2>
                <span class="close-btn" onclick="closePickupMessageModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="pickup-info-card">
                    <div class="pickup-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="pickup-info">
                        <h3>Arrange Your Pickup</h3>
                        <p>Please provide your contact information and preferred pickup time to coordinate with the donor.</p>
                    </div>
                </div>
                
                <form id="pickupMessageForm">
                    <input type="hidden" id="pickup_donation_id" name="donation_id">
                    
                    <div class="form-group">
                        <label for="pickup_date"><i class="fas fa-calendar"></i> Preferred Pickup Date & Time *</label>
                        <input type="datetime-local" id="pickup_date" name="pickup_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="claimer_name"><i class="fas fa-user"></i> Your Name *</label>
                        <input type="text" id="claimer_name" name="claimer_name" class="form-control" required placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="claimer_phone"><i class="fas fa-phone"></i> Your Phone Number *</label>
                        <input type="tel" id="claimer_phone" name="claimer_phone" class="form-control" required placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="pickup_message"><i class="fas fa-comment"></i> Message to Donor</label>
                        <textarea id="pickup_message" name="pickup_message" class="form-control" rows="3" placeholder="Any special requests or questions for the donor..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Request Pickup
                        </button>
                        <button type="button" onclick="closePickupMessageModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Donation Modal -->
    <div id="editDonationModal" class="modal" style="display: none;">
        <div class="modal-content edit-donation-modal">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Donation</h2>
                <span class="close-btn" onclick="closeEditDonationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editDonationForm">
                    <input type="hidden" id="edit_donation_id" name="donation_id">
                    
                    <div class="edit-item-info-card">
                        <div class="edit-item-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="edit-item-info">
                            <h3>Update Donation Details</h3>
                            <p>Modify the pickup information and availability for your donation</p>
                        </div>
                    </div>
                    
                    <div class="item-details-card professional-details-card">
                        <h4><i class="fas fa-info-circle"></i> Item Details</h4>
                        <div class="item-details-grid">
                            <div class="item-detail">
                                <span class="item-detail-label">Item:</span>
                                <span class="item-detail-value" id="edit_item_name"></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">Category:</span>
                                <span class="item-detail-value" id="edit_item_category"></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">Quantity:</span>
                                <span class="item-detail-value" id="edit_item_quantity"></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">Expiry:</span>
                                <span class="item-detail-value" id="edit_item_expiry"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="form-section-title"><i class="fas fa-edit"></i> Edit Information</h4>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Pickup Location *</label>
                            <input type="text" name="pickup_location" id="edit_pickup_location" placeholder="e.g., 123 Main St, City, State" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Availability *</label>
                            <input type="text" name="availability" id="edit_availability" placeholder="e.g., Weekdays 9-5, Weekends 10-2" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Additional Notes</label>
                            <textarea name="additional_notes" id="edit_notes" rows="3" placeholder="Any special instructions or notes for pickup..."></textarea>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" name="update_donation" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Donation
                        </button>
                        <button type="button" onclick="closeEditDonationModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal" style="display: none;">
        <div class="modal-content delete-modal">
            <div class="modal-header delete-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Return Confirmation</h2>
                <span class="close-btn" onclick="closeDeleteConfirmModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="delete-warning-card">
                    <div class="delete-icon">
                        <i class="fas fa-undo"></i>
                    </div>
                    <div class="delete-question">Return this item to your inventory?</div>
                    <div class="delete-item-name" id="delete_item_name"></div>
                </div>
                
                <div class="actions">
                    <button type="button" onclick="confirmReturnDonation()" class="btn btn-danger">
                        <i class="fas fa-undo"></i> Return to Inventory
                    </button>
                    <button type="button" onclick="closeDeleteConfirmModal()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Claimer Details Modal -->
    <div id="claimerDetailsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Claimer Details</h3>
                <span class="close" onclick="closeClaimerDetailsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="claimerDetailsContent">
                    <!-- Claimer details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeClaimerDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-copyright">
                © 2014-2025 SavePlate. All rights reserved.
            </div>
            <div class="footer-social">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </footer>

    <script>    
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
            console.log("Sidebar toggled");
        }

        // Desktop collapse/expand toggle via brand + persistence
        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('sidebar');
            const collapsed = sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            
            // Adjust footer margin when sidebar collapses/expands
            const footer = document.querySelector('.footer');
            if (collapsed) {
                footer.style.marginLeft = '72px';
            } else {
                footer.style.marginLeft = '280px';
            }
            
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}
            console.log("Sidebar collapse toggled");
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
            }
        });

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Load data for the selected tab
            if (tabName === 'my-donations') {
                loadDonations();
            } else if (tabName === 'available-donations') {
                loadAvailableDonations();
            }
        }

        // Toggle user dropdown
        document.getElementById("userBtn").addEventListener("click", function () {
            const dropdown = document.getElementById("userDropdown");
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
        });

        // Close dropdown if clicked outside
        window.addEventListener("click", function(e) {
            if (!document.getElementById("userBtn").contains(e.target) && 
                !document.getElementById("userDropdown").contains(e.target)) {
                document.getElementById("userDropdown").style.display = "none";
            }
        });

        function openProfileModal() {
            document.getElementById("profileModal").style.display = "flex";
        }

        function closeProfileModal() {
            document.getElementById("profileModal").style.display = "none";
        }

        // Close when clicking outside modal
        window.onclick = function(event) {
            const profileModal = document.getElementById("profileModal");
            const settingsModal = document.getElementById("settingsModal");
            const editDonationModal = document.getElementById("editDonationModal");
            const deleteConfirmModal = document.getElementById("deleteConfirmModal");
            const pickupMessageModal = document.getElementById("pickup-message-modal");
            const claimerDetailsModal = document.getElementById("claimerDetailsModal");
            
            if (event.target === profileModal) {
                closeProfileModal();
            }
            if (event.target === settingsModal) {
                closeSettingsModal();
            }
            if (event.target === editDonationModal) {
                closeEditDonationModal();
            }
            if (event.target === deleteConfirmModal) {
                closeDeleteConfirmModal();
            }
            if (event.target === pickupMessageModal) {
                closePickupMessageModal();
            }
            if (event.target === claimerDetailsModal) {
                closeClaimerDetailsModal();
            }
        }

        document.getElementById("editProfileBtn").addEventListener("click", function () {
            let inputs = document.querySelectorAll("#profileForm input, #profileForm textarea");

            inputs.forEach(el => {
                el.removeAttribute("readonly");
                el.style.background = "#fff"; // white when editable
            });

            document.getElementById("editProfileBtn").style.display = "none";
            document.getElementById("saveProfileBtn").style.display = "inline-block";
        });

        function openSettingsModal() {
            document.getElementById("settingsModal").style.display = "flex";
            const dd = document.getElementById('userDropdown');
            if (dd) dd.style.display = 'none';
        }
        
        function closeSettingsModal() {
            document.getElementById("settingsModal").style.display = "none";
        }

        // Donation popup button event listeners
        document.getElementById('close-donation-popup').addEventListener('click', function() {
            hideDonationPopup();
        });

        document.getElementById('cancel-donation').addEventListener('click', function() {
            hideDonationPopup();
        });

        // Donation form submission
        document.getElementById('donationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'convert_to_donation');

            console.log('Submitting donation form with data:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }

            fetch('food_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    showAlert('Item successfully converted to donation!', 'success');
                    hideDonationPopup();
                    loadDonations(); // Refresh donations
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error: ' + error.message, 'error');
            });
        });

        // Load donations
        function loadDonations() {
            const container = document.getElementById('donations-container');
            if (!container) return; // Not on donations section
            container.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i> Loading donations...</div>';

            fetch('food_inventory.php?action=get_donations')
            .then(response => response.json())
            .then(data => {
                console.log('Donations data received:', data);
                if (data.success) {
                    console.log('Donations array:', data.data);
                    // Debug: Log each donation's category
                    data.data.forEach((donation, index) => {
                        console.log(`Donation ${index}:`, {
                            id: donation.id,
                            item_name: donation.item_name, 
                            category: donation.category, 
                            category_type: typeof donation.category,
                            raw_donation: donation
                        });
                    });
                    displayDonations(data.data);
                } else {
                    container.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Error loading donations</div>';
                }
            })
            .catch(error => {
                container.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Error: ' + error.message + '</div>';
            });
        }


        // Load available donations
        function loadAvailableDonations() {
            const container = document.getElementById('available-donations-container');
            if (!container) {
                console.log('Available donations container not found');
                return; // Not on available donations section
            }
            console.log('Loading available donations...');
            container.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i> Loading available donations...</div>';

            fetch('food_inventory.php?action=get_available_donations')
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.success) {
                    // Debug: Log each available donation's category
                    data.data.forEach((donation, index) => {
                        console.log(`Available Donation ${index}:`, donation.item_name, 'Category:', donation.category, 'Type:', typeof donation.category);
                    });
                    displayAvailableDonations(data.data);
                } else {
                    container.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Error loading available donations: ' + (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading available donations:', error);
                container.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Error: ' + error.message + '</div>';
            });
        }

        // Store original donations data for filtering
        let allDonations = [];
        let allAvailableDonations = [];

        // Display donations
        function displayDonations(donations) {
            console.log('displayDonations called with:', donations);
            allDonations = donations; // Store for filtering
            console.log('allDonations stored:', allDonations);
            
            // Update category filter with only categories that exist in user's donations
            updateDonationsCategoryFilter();
            
            filterAndDisplayDonations();
        }

        // Filter and display donations based on search and filter criteria
        function filterAndDisplayDonations() {
            const container = document.getElementById('donations-container');
            const searchTerm = document.getElementById('donations-search').value.toLowerCase();
            const statusFilter = document.getElementById('donations-status-filter').value;
            const categoryFilter = document.getElementById('donations-category-filter').value;
            
            let filteredDonations = allDonations.filter(donation => {
                const matchesSearch = !searchTerm || 
                    donation.item_name.toLowerCase().includes(searchTerm) ||
                    donation.category.toLowerCase().includes(searchTerm) ||
                    donation.pickup_location.toLowerCase().includes(searchTerm) ||
                    (donation.notes && donation.notes.toLowerCase().includes(searchTerm));
                
                const matchesStatus = !statusFilter || donation.status === statusFilter;
                const matchesCategory = !categoryFilter || donation.category === categoryFilter;
                
                return matchesSearch && matchesStatus && matchesCategory;
            });
            
            if (filteredDonations.length === 0) {
                // Update results count
                const countElement = document.getElementById('donations-count');
                if (countElement) {
                    countElement.textContent = `Showing 0 of ${allDonations.length} donations`;
                }
                
                if (allDonations.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-heart"></i>
                            <h3>No donations yet</h3>
                            <p>When you convert items to donations, they will appear here</p>
                            <a href="/bit216_assignment/view_inventory.php" class="btn btn-primary">Donate Your First Item</a>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No donations found</h3>
                            <p>Try adjusting your search or filter criteria</p>
                            <button onclick="clearDonationsFilters()" class="btn btn-primary"><i class="fas fa-times"></i> Clear Filters</button>
                        </div>
                    `;
                }
                return;
            }

            // Update results count
            const countElement = document.getElementById('donations-count');
            if (countElement) {
                countElement.textContent = `Showing ${filteredDonations.length} of ${allDonations.length} donations`;
            }

            let html = '<div class="section-title" style="margin-bottom: 20px;">';
            html += '</div>';
            html += '<div class="inventory-grid">';
            
            filteredDonations.forEach(donation => {
                console.log('Donation ID:', donation.id, 'Status:', donation.status, 'Category:', donation.category, 'Requester:', donation.requester_name);
                const expiryDate = new Date(donation.expiry_date);
                const today = new Date();
                const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                
                let statusBadge = '';
                if (donation.status === 'claimed') {
                    statusBadge = '<div class="status-badge claimed">Claimed</div>';
                } else if (donation.status === 'available') {
                    statusBadge = '<div class="status-badge available">Available</div>';
                } else if (donation.status === 'pending') {
                    statusBadge = '<div class="status-badge pending">Pending</div>';
                }
                
                html += `
                    <div class="inventory-card donation-card">
                        <div class="card-header">
                            <div class="item-name">${escapeHtml(donation.item_name)}</div>
                            <div class="category-badge">${formatCategoryName(donation.category)}</div>
                        </div>
                        
                        <div class="item-details">
                            <div class="detail-row">
                                <span class="detail-label">Quantity:</span>
                                <span class="detail-value">${escapeHtml(donation.quantity)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Expiry:</span>
                                <span class="detail-value">${formatDate(donation.expiry_date)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Pickup Location:</span>
                                <span class="detail-value">${escapeHtml(donation.pickup_location)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Availability:</span>
                                <span class="detail-value">${escapeHtml(donation.availability)}</span>
                            </div>
                            ${donation.status === 'pending' && donation.requester_name ? `
                                <div class="detail-row">
                                    <span class="detail-label">Requested by:</span>
                                    <span class="detail-value">${escapeHtml(donation.requester_name)}</span>
                                </div>
                                ${donation.pickup_message ? `<div class="detail-row">
                                    <span class="detail-label">Message:</span>
                                    <span class="detail-value">${escapeHtml(donation.pickup_message)}</span>
                                </div>` : ''}
                            ` : ''}
                            ${donation.notes ? `<div class="detail-row">
                                <span class="detail-label">Notes:</span>
                                <span class="detail-value">${escapeHtml(donation.notes)}</span>
                            </div>` : ''}
                        </div>

                        ${statusBadge}

                        ${donation.status === 'available' || donation.status === 'pending' ? 
                            `<div class="card-actions">
                                ${donation.status === 'available' ? 
                                    `<button onclick="editDonation(${donation.id})" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Edit</button>` : 
                                    ''
                                }
                                ${donation.status === 'pending' ? 
                                    `<button onclick="approvePickup(${donation.id})" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                                     <button onclick="rejectPickup(${donation.id})" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Reject</button>` : 
                                    ''
                                }
                                ${donation.status === 'available' ? 
                                    `<button onclick="deleteDonation(${donation.id})" class="btn btn-danger btn-sm"><i class="fas fa-undo"></i> Return</button>` : 
                                    ''
                                }
                            </div>` : 
                            ''
                        }
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        // Display available donations
        function displayAvailableDonations(donations) {
            console.log('Displaying available donations:', donations);
            allAvailableDonations = donations; // Store for filtering
            
            // Update category filter with only categories that exist in available donations
            updateAvailableDonationsCategoryFilter();
            
            filterAndDisplayAvailableDonations();
        }

        // Filter and display available donations based on search and filter criteria
        function filterAndDisplayAvailableDonations() {
            const container = document.getElementById('available-donations-container');
            const searchTerm = document.getElementById('available-donations-search').value.toLowerCase();
            const categoryFilter = document.getElementById('available-donations-category-filter').value;
            const expiryFilter = document.getElementById('available-donations-expiry-filter').value;
            
            let filteredDonations = allAvailableDonations.filter(donation => {
                const matchesSearch = !searchTerm || 
                    donation.item_name.toLowerCase().includes(searchTerm) ||
                    donation.category.toLowerCase().includes(searchTerm) ||
                    donation.donor_name.toLowerCase().includes(searchTerm) ||
                    donation.pickup_location.toLowerCase().includes(searchTerm) ||
                    (donation.notes && donation.notes.toLowerCase().includes(searchTerm));
                
                const matchesCategory = !categoryFilter || donation.category === categoryFilter;
                
                // Expiry filter
                let matchesExpiry = true;
                if (expiryFilter) {
                    const expiryDate = new Date(donation.expiry_date);
                    const today = new Date();
                    const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                    
                    if (expiryFilter === 'urgent') {
                        matchesExpiry = daysUntilExpiry <= 3;
                    } else if (expiryFilter === 'soon') {
                        matchesExpiry = daysUntilExpiry <= 7 && daysUntilExpiry > 3;
                    } else if (expiryFilter === 'normal') {
                        matchesExpiry = daysUntilExpiry > 7;
                    }
                }
                
                return matchesSearch && matchesCategory && matchesExpiry;
            });
            
            // Update count display
            const countElement = document.getElementById('available-donations-count');
            if (countElement) {
                countElement.textContent = `Showing ${filteredDonations.length} of ${allAvailableDonations.length} donations`;
            }
            
            if (filteredDonations.length === 0) {
                if (allAvailableDonations.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-hand-holding-heart"></i>
                            <h3>No available donations</h3>
                            <p>There are currently no donations available for claiming. Check back later!</p>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No donations found</h3>
                            <p>Try adjusting your search or filter criteria</p>
                            <button onclick="clearAvailableDonationsFilters()" class="btn btn-primary"><i class="fas fa-times"></i> Clear Filters</button>
                        </div>
                    `;
                }
                return;
            }

            let html = '<div class="section-title" style="margin-bottom: 20px;">';
            html += '</div>';
            html += '<div class="inventory-grid">';
            
            filteredDonations.forEach(donation => {
                const expiryDate = new Date(donation.expiry_date);
                const today = new Date();
                const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                
                html += `
                    <div class="inventory-card donation-card available-for-claim">
                        <div class="card-header">
                            <div class="item-name">${escapeHtml(donation.item_name)}</div>
                            <div class="category-badge">${formatCategoryName(donation.category)}</div>
                        </div>
                        
                        <div class="item-details">
                            <div class="detail-row">
                                <span class="detail-label">Quantity:</span>
                                <span class="detail-value">${escapeHtml(donation.quantity)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Expiry:</span>
                                <span class="detail-value">${formatDate(donation.expiry_date)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Pickup Location:</span>
                                <span class="detail-value">${escapeHtml(donation.pickup_location)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Availability:</span>
                                <span class="detail-value">${escapeHtml(donation.availability)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Donor:</span>
                                <span class="detail-value">${escapeHtml(donation.donor_name)}</span>
                            </div>
                            ${donation.notes ? `<div class="detail-row">
                                <span class="detail-label">Notes:</span>
                                <span class="detail-value">${escapeHtml(donation.notes)}</span>
                            </div>` : ''}
                        </div>

                        <div class="card-actions">
                            <button onclick="claimDonation(${donation.id})" class="btn btn-success btn-sm"><i class="fas fa-hand-holding-heart"></i> Claim</button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        // Format date for display
        function formatDate(dateString) {
            const date = new Date(dateString);
            const day = date.getDate();
            const month = date.toLocaleDateString('en-US', { month: 'long' });
            const year = date.getFullYear();
            return `${day} ${month} ${year}`;
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Utility function to format category names
        function formatCategoryName(category) {
            console.log('formatCategoryName called with:', category, 'type:', typeof category);
            
            // Handle null, undefined, empty string, or '1' (which seems to be a default value)
            if (!category || category === '1' || category === 'null' || category === 'undefined') {
                console.log('Category is empty/null/1, returning Other');
                return 'Other';
            }
            
            // Handle numeric categories
            if (category === '1') return 'Other';
            
            // Handle database-friendly category names (underscore format)
            const categoryMap = {
                'fruits_vegetables': 'Fruits & Vegetables',
                'dairy_eggs': 'Dairy & Eggs',
                'meat_fish': 'Meat & Fish',
                'grains_cereals': 'Grains & Cereals',
                'canned_goods': 'Canned Goods',
                'beverages': 'Beverages',
                'snacks': 'Snacks',
                'condiments': 'Condiments',
                'frozen_foods': 'Frozen Foods',
                'other': 'Other'
            };
            
            // Check if it's a mapped category (database format)
            if (categoryMap[category]) {
                console.log('Found mapped category:', category, '->', categoryMap[category]);
                return categoryMap[category];
            }
            
            // Handle display names that are already properly formatted
            const displayNames = [
                'Fruits & Vegetables',
                'Dairy & Eggs', 
                'Meat & Fish',
                'Grains & Cereals',
                'Canned Goods',
                'Beverages',
                'Snacks',
                'Condiments',
                'Frozen Foods',
                'Other'
            ];
            
            if (displayNames.includes(category)) {
                console.log('Found display name category:', category);
                return category;
            }
            
            // Handle invalid data (like addresses in category field)
            if (category.includes('shah alam') || category.includes('selangor') || category.includes('Friday')) {
                console.log('Invalid category data detected:', category, '-> returning Other');
                return 'Other';
            }
            
            // For other categories, format by splitting underscores and capitalizing
            const formatted = category.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' & ');
            console.log('Formatted category:', category, '->', formatted);
            return formatted;
        }


        // Show donation popup
        function showDonationPopup(item) {
            // Populate the donation form with item details
            document.getElementById('donation_item_id').value = item.id;
            document.getElementById('donation-item-name').textContent = item.item_name;
            document.getElementById('donation-item-details').textContent = 
                `Quantity: ${item.quantity} | Category: ${item.category} | Expires: ${formatDate(item.expiry_date)}`;
            
            // Show the donation popup
            document.getElementById('donation-popup').style.display = 'flex';
        }

        function hideDonationPopup() {
            document.getElementById('donation-popup').style.display = 'none';
            document.getElementById('donationForm').reset();
        }

        // Edit donation
        function editDonation(donationId) {
            console.log('Edit donation called with ID:', donationId);
            console.log('All donations:', allDonations);
            // Find the donation data from the allDonations array
            const donation = allDonations.find(d => d.id == donationId);
            if (!donation) {
                console.log('Donation not found in allDonations array');
                showAlert('Donation not found!', 'error');
                return;
            }
            console.log('Found donation:', donation);
            
            // Populate the edit modal with donation data
            document.getElementById('edit_donation_id').value = donationId;
            document.getElementById('edit_item_name').textContent = donation.item_name;
            document.getElementById('edit_item_category').textContent = donation.category;
            document.getElementById('edit_item_quantity').textContent = donation.quantity;
            document.getElementById('edit_item_expiry').textContent = new Date(donation.expiry_date).toLocaleDateString();
            document.getElementById('edit_pickup_location').value = donation.pickup_location;
            document.getElementById('edit_availability').value = donation.availability;
            document.getElementById('edit_notes').value = donation.notes || '';
            
            // Show the edit modal
            document.getElementById('editDonationModal').style.display = 'flex';
        }

        // Close Edit Donation Modal
        function closeEditDonationModal() {
            document.getElementById('editDonationModal').style.display = 'none';
            document.getElementById('editDonationForm').reset();
        }

        // Delete donation (return to inventory)
        function deleteDonation(donationId) {
            console.log('Delete donation called with ID:', donationId);
            console.log('All donations array:', allDonations);
            console.log('Looking for donation with ID:', donationId);
            
            // Find the donation to get the item name
            const donation = allDonations.find(d => d.id == donationId);
            console.log('Found donation:', donation);
            if (donation) {
                document.getElementById('delete_item_name').textContent = donation.item_name;
                document.getElementById('deleteConfirmModal').setAttribute('data-donation-id', donationId);
                
                document.getElementById('deleteConfirmModal').style.display = 'flex';
            } else {
                console.log('Donation not found in allDonations array');
                showAlert('Donation not found', 'error');
            }
        }

        // Confirm return donation (new function name)
        function confirmReturnDonation() {
            const donationId = document.getElementById('deleteConfirmModal').getAttribute('data-donation-id');
            console.log('=== RETURN DONATION DEBUG ===');
            console.log('Donation ID from modal:', donationId);
            
            const formData = new FormData();
            formData.append('action', 'return_donation_to_inventory');
            formData.append('donation_id', donationId);
            
            console.log('Sending return request for donation ID:', donationId);
            console.log('Form data being sent:', Array.from(formData.entries()));

            fetch('food_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Return response:', data);
                if (data.success) {
                    // Show both custom alert and browser alert for better visibility
                    showAlert('All items returned to inventory successfully!', 'success');
                    alert('✅ Successfully returned items to inventory!');
                    closeDeleteConfirmModal();
                    loadDonations(); // Refresh donations
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Return error:', error);
                showAlert('Error: ' + error.message, 'error');
            });
        }

        // Close Delete Confirmation Modal
        function closeDeleteConfirmModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.getElementById('deleteConfirmModal').removeAttribute('data-donation-id');
        }

        // Approve pickup request
        function approvePickup(donationId) {
            if (confirm('Are you sure you want to approve this pickup request?')) {
                const formData = new FormData();
                formData.append('action', 'approve_pickup');
                formData.append('donation_id', donationId);
                
                fetch('food_inventory.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadDonations(); // Refresh donations
                    } else {
                        showAlert('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error approving pickup:', error);
                    showAlert('An error occurred while approving the pickup request', 'error');
                });
            }
        }

        // Reject pickup request
        function rejectPickup(donationId) {
            if (confirm('Are you sure you want to reject this pickup request?')) {
                const formData = new FormData();
                formData.append('action', 'reject_pickup');
                formData.append('donation_id', donationId);
                
                fetch('food_inventory.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadDonations(); // Refresh donations
                    } else {
                        showAlert('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error rejecting pickup:', error);
                    showAlert('An error occurred while rejecting the pickup request', 'error');
                });
        }
    }

    // View claimer details
    function viewClaimerDetails(donationId) {
        const formData = new FormData();
        formData.append('action', 'get_claimer_details');
        formData.append('donation_id', donationId);

        fetch('food_inventory.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayClaimerDetails(data.data);
            } else {
                showAlert('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching claimer details:', error);
            showAlert('An error occurred while fetching claimer details', 'error');
        });
    }

    // Display claimer details in modal
    function displayClaimerDetails(claimerData) {
        const content = `
            <div class="claimer-info">
                <div class="info-row">
                    <label><i class="fas fa-user"></i> Username:</label>
                    <span>${escapeHtml(claimerData.username)}</span>
                </div>
                <div class="info-row">
                    <label><i class="fas fa-envelope"></i> Email:</label>
                    <span>${escapeHtml(claimerData.email)}</span>
                </div>
                <div class="info-row">
                    <label><i class="fas fa-phone"></i> Phone:</label>
                    <span>${escapeHtml(claimerData.phone || 'Not provided')}</span>
                </div>
                <div class="info-row">
                    <label><i class="fas fa-map-marker-alt"></i> Address:</label>
                    <span>${escapeHtml(claimerData.address || 'Not provided')}</span>
                </div>
                <div class="info-row">
                    <label><i class="fas fa-comment"></i> Pickup Message:</label>
                    <span>${escapeHtml(claimerData.pickup_message || 'No message provided')}</span>
                </div>
                <div class="info-row">
                    <label><i class="fas fa-clock"></i> Request Date:</label>
                    <span>${new Date(claimerData.request_date).toLocaleString()}</span>
                </div>
            </div>
        `;
        
        document.getElementById('claimerDetailsContent').innerHTML = content;
        document.getElementById('claimerDetailsModal').style.display = 'block';
    }

    // Close claimer details modal
    function closeClaimerDetailsModal() {
        document.getElementById('claimerDetailsModal').style.display = 'none';
    }

        // Claim donation
        function claimDonation(donationId) {
            // Show pickup message modal
            showPickupMessageModal(donationId);
        }

        // Show pickup message modal
        function showPickupMessageModal(donationId) {
            document.getElementById('pickup_donation_id').value = donationId;
            document.getElementById('pickup-message-modal').classList.add('show');
            
            // Set minimum date to today
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0); // Set to 9 AM tomorrow
            
            const dateStr = tomorrow.toISOString().slice(0, 16);
            console.log('Setting pickup_date to:', dateStr);
            console.log('Tomorrow date object:', tomorrow);
            
            document.getElementById('pickup_date').value = dateStr;
            
            // Debug: Verify the value was set
            const actualValue = document.getElementById('pickup_date').value;
            console.log('Actual pickup_date value after setting:', actualValue);
        }

        // Close pickup message modal
        function closePickupMessageModal() {
            document.getElementById('pickup-message-modal').classList.remove('show');
            document.getElementById('pickupMessageForm').reset();
        }

        // Handle pickup message form submission
        document.addEventListener('DOMContentLoaded', function() {
            const pickupForm = document.getElementById('pickupMessageForm');
            if (pickupForm) {
                pickupForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Debug: Log form data before submission
                    console.log('Pickup form submission - Form data:');
                    const formData = new FormData(this);
                    formData.append('action', 'request_pickup');
                    
                    // Log all form data
                    for (let [key, value] of formData.entries()) {
                        console.log(key + ': ' + value);
                    }
                    
                    fetch('food_inventory.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            closePickupMessageModal();
                            // Refresh available donations
                            loadAvailableDonations();
                        } else {
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        showAlert('Error: ' + error.message, 'error');
                    });
                });
            }
        });

        // Show alert messages
        function showAlert(message, type) {
            if (type === 'error') {
                // Show error as popup instead of alert
                showErrorPopup(message);
            } else {
                // Show success and info messages as alerts
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                
                let icon = 'exclamation-circle';
                if (type === 'success') icon = 'check-circle';
                else if (type === 'info') icon = 'info-circle';
                
                alert.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
                
                // Insert at the top of the main content
                const mainContent = document.querySelector('.main-content');
                mainContent.insertBefore(alert, mainContent.firstChild);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 5000);
            }
        }

        // Show error popup
        function showErrorPopup(message) {
            const popup = document.createElement('div');
            popup.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #ffebee;
                color: #c62828;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #ffcdd2;
                z-index: 10001;
                max-width: 400px;
                text-align: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            `;
            popup.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            
            document.body.appendChild(popup);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (popup.parentNode) {
                    popup.parentNode.removeChild(popup);
                }
            }, 5000);
        }

        // Clear filters for donations
        function clearDonationsFilters() {
            document.getElementById('donations-search').value = '';
            document.getElementById('donations-status-filter').value = '';
            document.getElementById('donations-category-filter').value = '';
            filterAndDisplayDonations();
        }

        // Clear filters for available donations
        function clearAvailableDonationsFilters() {
            document.getElementById('available-donations-search').value = '';
            document.getElementById('available-donations-category-filter').value = '';
            document.getElementById('available-donations-expiry-filter').value = '';
            filterAndDisplayAvailableDonations();
        }

        // Edit donation form submission
        document.addEventListener('DOMContentLoaded', function() {
            const editDonationForm = document.getElementById('editDonationForm');
            if (editDonationForm) {
                editDonationForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'update_donation');
                    
                    fetch('food_inventory.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('Donation updated successfully!', 'success');
                            closeEditDonationModal();
                            loadDonations(); // Refresh donations
                        } else {
                            showAlert('Error: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('An error occurred while updating the donation.', 'error');
                    });
                });
            }
        });

        // Restore collapsed state and wire brand click
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (localStorage.getItem('sidebarCollapsed') === '1') {
                    document.getElementById('sidebar').classList.add('collapsed');
                    document.body.classList.add('sidebar-collapsed');
                    document.querySelector('.footer').style.marginLeft = '72px';
                }
            } catch(e) {}
            const brand = document.getElementById('brand');
            if (brand) brand.addEventListener('click', toggleSidebarCollapse);
            
            // Check URL parameters for tab switching
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam === 'available') {
                // Switch to available donations tab
                showTab('available-donations');
                // Update the active button
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector('button[onclick="showTab(\'available-donations\')"]').classList.add('active');
            }
            
            function updateSidebarNotificationCount(){
                try{
                    var formData = new FormData();
                    formData.append('action','get_unread_count');
                    formData.append('user_id', <?php echo (int)($userData['id'] ?? 0); ?>);
                    fetch('notification_handler.php',{method:'POST',body:formData})
                        .then(function(r){return r.json();})
                        .then(function(data){
                            var badge = document.getElementById('sidebar-notification-count');
                            if(!badge) return;
                            var count = (data && data.success) ? (data.unread_count||0) : 0;
                            if(count>0){
                                badge.textContent = count;
                                badge.style.display = 'flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        })
                        .catch(function(){});
                }catch(e){}
            }
            updateSidebarNotificationCount();
            setInterval(updateSidebarNotificationCount,30000);

            // Load donations on page load
            loadDonations();

            // Add event listeners for search and filter inputs
            const donationsSearch = document.getElementById('donations-search');
            const donationsStatusFilter = document.getElementById('donations-status-filter');
            const donationsCategoryFilter = document.getElementById('donations-category-filter');
            
            if (donationsSearch) {
                donationsSearch.addEventListener('input', filterAndDisplayDonations);
            }
            if (donationsStatusFilter) {
                donationsStatusFilter.addEventListener('change', filterAndDisplayDonations);
            }
            if (donationsCategoryFilter) {
                donationsCategoryFilter.addEventListener('change', filterAndDisplayDonations);
            }

            const availableDonationsSearch = document.getElementById('available-donations-search');
            const availableDonationsCategoryFilter = document.getElementById('available-donations-category-filter');
            const availableDonationsExpiryFilter = document.getElementById('available-donations-expiry-filter');
            
            if (availableDonationsSearch) {
                availableDonationsSearch.addEventListener('input', filterAndDisplayAvailableDonations);
            }
            if (availableDonationsCategoryFilter) {
                availableDonationsCategoryFilter.addEventListener('change', filterAndDisplayAvailableDonations);
            }
            if (availableDonationsExpiryFilter) {
                availableDonationsExpiryFilter.addEventListener('change', filterAndDisplayAvailableDonations);
            }
        });

        document.addEventListener("DOMContentLoaded", function () {
            const userBtn = document.getElementById("userBtn");
            const userDropdown = document.getElementById("userDropdown");

            if (userBtn && userDropdown) {
                // Toggle dropdown on button click
                userBtn.addEventListener("click", function (e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle("show");
                });

                // Close dropdown when clicking outside
                document.addEventListener("click", function (e) {
                    if (!userBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove("show");
                    }
                });
            }
        });

        // Update category filter for "My Donations" to show only categories that exist
        function updateDonationsCategoryFilter() {
            const categoryFilter = document.getElementById('donations-category-filter');
            if (!categoryFilter) return;
            
            const currentValue = categoryFilter.value;
            
            // Get unique categories from current donations
            const categories = [...new Set(allDonations.map(donation => donation.category).filter(cat => cat && cat.trim() !== ''))];
            
            // Clear existing options except "All Categories"
            categoryFilter.innerHTML = '<option value="">All Categories</option>';
            
            // Only show categories that exist in the current donations
            if (categories.length > 0) {
                // Category display name mapping
                const categoryDisplayNames = {
                    'fruits_vegetables': 'Fruits & Vegetables',
                    'dairy_eggs': 'Dairy & Eggs',
                    'meat_fish': 'Meat & Fish',
                    'grains_cereals': 'Grains & Cereals',
                    'canned_goods': 'Canned Goods',
                    'beverages': 'Beverages',
                    'snacks': 'Snacks',
                    'condiments': 'Condiments',
                    'frozen_foods': 'Frozen Foods',
                    'other': 'Other'
                };
                
                categories.forEach(category => {
                    const displayName = categoryDisplayNames[category] || category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = displayName;
                    categoryFilter.appendChild(option);
                });
            }
            
            // Restore previous selection if it still exists
            if (currentValue && categories.includes(currentValue)) {
                categoryFilter.value = currentValue;
            } else {
                categoryFilter.value = '';
            }
        }

        // Update category filter for "Available Donations" to show only categories that exist
        function updateAvailableDonationsCategoryFilter() {
            const categoryFilter = document.getElementById('available-donations-category-filter');
            if (!categoryFilter) return;
            
            const currentValue = categoryFilter.value;
            
            // Get unique categories from current available donations
            const categories = [...new Set(allAvailableDonations.map(donation => donation.category).filter(cat => cat && cat.trim() !== ''))];
            
            // Clear existing options except "All Categories"
            categoryFilter.innerHTML = '<option value="">All Categories</option>';
            
            // Only show categories that exist in the current available donations
            if (categories.length > 0) {
                // Category display name mapping
                const categoryDisplayNames = {
                    'fruits_vegetables': 'Fruits & Vegetables',
                    'dairy_eggs': 'Dairy & Eggs',
                    'meat_fish': 'Meat & Fish',
                    'grains_cereals': 'Grains & Cereals',
                    'canned_goods': 'Canned Goods',
                    'beverages': 'Beverages',
                    'snacks': 'Snacks',
                    'condiments': 'Condiments',
                    'frozen_foods': 'Frozen Foods',
                    'other': 'Other'
                };
                
                categories.forEach(category => {
                    const displayName = categoryDisplayNames[category] || category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = displayName;
                    categoryFilter.appendChild(option);
                });
            }
            //
            // Restore previous selection if it still exists
            if (currentValue && categories.includes(currentValue)) {
                categoryFilter.value = currentValue;
            } else {
                categoryFilter.value = '';
            }
        }
    </script>
</body>

</html>
