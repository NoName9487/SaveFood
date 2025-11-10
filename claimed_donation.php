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
    <title>SavePlate - Claimed Donation</title>
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
            --info: #4CAF50;
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
            background-color: var(--warning);
            color: #000;
        }

        .btn-warning:hover {
            background-color: #e0a800;
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

        .modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            width: 450px;
            max-width: 90%;
            position: relative;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            max-height: 90vh;
            overflow-y: auto;
            z-index: 10000; /* Even higher z-index */
        }

        .close-btn {
            position: absolute;
            top: 12px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }

        .close-btn:hover {
            color: #000;
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

        /* Status badges for claimed donations */
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            z-index: 1;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .status-badge.pending { 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border-color: #b45309;
        }
        
        .status-badge.success { 
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border-color: #2e7d32;
        }
        
        .status-badge.rejected { 
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border-color: #991b1b;
        }

        .claims-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .claim-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06), 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(76, 175, 80, 0.1);
            position: relative;
        }

        .claim-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 8px 20px rgba(76, 175, 80, 0.15);
            border-color: #4CAF50;
        }

        .claim-card::before {
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

        .claim-card:hover::before {
            opacity: 1;
        }

        .claim-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 50%, #2e7d32 100%);
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .claim-card .card-header::before {
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

        .claim-card .card-header > div:first-child {
            font-size: 1.3rem;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .claim-card .card-body { 
            padding: 25px; 
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
        }
        
        .claim-card .detail-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 12px 0; 
            border-bottom: 1px solid rgba(76, 175, 80, 0.1);
            margin-bottom: 18px;
            transition: all 0.3s ease;
        }

        .claim-card .detail-row:hover {
            background: rgba(76, 175, 80, 0.05);
            border-radius: 8px;
            padding: 12px 15px;
            margin: 0 -15px 18px -15px;
        }

        .claim-card .detail-row:last-child { 
            border-bottom: none; 
            margin-bottom: 0;
        }
        
        .claim-card .detail-label { 
            color: #2c3e50;
            font-weight: 700;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .claim-card .detail-value {
            color: #4CAF50;
            font-weight: 700;
            font-size: 0.95rem;
        }
        
        /* Empty State Styling */
        .empty-state-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
            grid-column: 1 / -1;
            min-height: 400px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #9CA3AF;
            margin-bottom: 20px;
        }
        
        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
        }
        
        .empty-state-description {
            font-size: 1rem;
            color: #6B7280;
            margin-bottom: 24px;
            max-width: 400px;
            line-height: 1.5;
        }
        
        .empty-state-button {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: underline;
            text-decoration-color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .empty-state-button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        /* Search and Filter Section */
        .search-filter-container {
            background: #f8f9fa;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 20px;
            border-left: 4px solid #2196F3;
        }

        .search-filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .filter-select {
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            min-width: 150px;
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .btn-clear {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-clear:hover {
            background: #5a6268;
        }

        .btn-refresh {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-refresh:hover {
            background: #45a049;
        }

        .results-info {
            color: #6B7280;
            font-size: 14px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .search-filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                min-width: auto;
            }
            
            .filter-select {
                min-width: auto;
            }
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
                <a href="/bit216_assignment/mydonation.php" class="nav-link <?php echo $current_page == 'inventory_interface.php' ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    <span class="label">My Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/claimed_donation.php" class="nav-link <?php echo $current_page == 'claimed_donation.php' ? 'active' : ''; ?>">
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
                <a href="/bit216_assignment/notification.php" class="nav-link <?php echo $current_page == 'noti.php' ? 'active' : ''; ?>">
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
                <h1 class="page-title">Claimed Donation</h1>
                <p class="page-subtitle">Manage your food items and reduce waste</p>
            </div>
            
            <div class="section-title">
                <i class="fas fa-hand-holding-heart"></i>
                <h2>Your Pickup Requests</h2>
            </div>

            <?php
                // Load current user's claims (as claimer) and get categories
                try {
                    $stmt = $pdo->prepare("SELECT dc.id AS claim_id, dc.status AS claim_status, dc.pickup_message, dc.created_at AS claimed_at,
                                                    d.id AS donation_id, d.item_name, d.quantity, d.expiry_date, d.pickup_location, d.availability,
                                                    d.category
                                             FROM donation_claims dc
                                             JOIN donations d ON d.id = dc.donation_id
                                             WHERE dc.claimed_by_user_id = ?
                                             ORDER BY dc.created_at DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get unique categories from user's claims
                    $userCategories = [];
                    foreach ($claims as $claim) {
                        // Only add non-empty categories
                        if (!empty($claim['category']) && !in_array($claim['category'], $userCategories)) {
                            $userCategories[] = $claim['category'];
                        }
                    }
                } catch (Exception $e) {
                    $claims = [];
                    $userCategories = [];
                }
            ?>

            <!-- Search and Filter Section -->
            <div class="search-filter-container">
                <div class="search-filter-row">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by item name, category, donor, or location...">
                    <select id="categoryFilter" class="filter-select">
                        <option value="">All Categories</option>
                        <?php 
                        // Category mapping for display names
                        $categoryDisplayNames = [
                            'fruits_vegetables' => 'Fruits & Vegetables',
                            'dairy_eggs' => 'Dairy & Eggs',
                            'meat_fish' => 'Meat & Fish',
                            'grains_cereals' => 'Grains & Cereals',
                            'canned_goods' => 'Canned Goods',
                            'beverages' => 'Beverages',
                            'snacks' => 'Snacks',
                            'condiments' => 'Condiments',
                            'frozen_foods' => 'Frozen Foods',
                            'other' => 'Other'
                        ];
                        
                        // Only show categories that exist in user's claims
                        if (!empty($userCategories)) {
                            foreach ($userCategories as $category) {
                                $displayName = isset($categoryDisplayNames[$category]) ? $categoryDisplayNames[$category] : ucwords(str_replace('_', ' ', $category));
                                echo "<option value=\"" . htmlspecialchars($category) . "\">" . htmlspecialchars($displayName) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Successful</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button class="btn-clear" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
                <div class="search-filter-row">
                    <button class="btn-refresh" onclick="refreshClaims()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <div class="results-info" id="resultsInfo">Showing all claims</div>
                </div>
            </div>


            <div class="claims-grid">
                <?php if (empty($claims)) : ?>
                    <div class="empty-state-container">
                        <div class="empty-state-icon">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <h3 class="empty-state-title">No pickup requests found</h3>
                        <p class="empty-state-description">Start claiming food donations by browsing available items from the donation page.</p>
                        <a href="/bit216_assignment/mydonation.php" class="empty-state-button">
                            Claimed Your First Item
                        </a>
                    </div>
                <?php else: foreach ($claims as $c): ?>
                    <?php
                        $badgeClass = $c['claim_status'] === 'approved' ? 'success' : ($c['claim_status'] === 'rejected' ? 'rejected' : 'pending');
                        $badgeText = $c['claim_status'] === 'approved' ? 'Successful' : ($c['claim_status'] === 'rejected' ? 'Rejected' : 'Pending');
                    ?>
                    <div class="claim-card" data-category="<?php echo htmlspecialchars($c['category']); ?>">
                        <div class="card-header">
                            <div><?php echo htmlspecialchars($c['item_name']); ?></div>
                            <div class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></div>
                        </div>
                        <div class="card-body">
                            <div class="detail-row"><span class="detail-label">Category:</span><span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $c['category']))); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Quantity:</span><span><?php echo htmlspecialchars($c['quantity']); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Expiry:</span><span><?php echo date('j F Y', strtotime($c['expiry_date'])); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Pickup Location:</span><span><?php echo htmlspecialchars($c['pickup_location']); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Availability:</span><span><?php echo htmlspecialchars($c['availability']); ?></span></div>
                            <?php if (!empty($c['pickup_message'])): ?>
                                <div class="detail-row"><span class="detail-label">Your Message:</span><span><?php echo htmlspecialchars($c['pickup_message']); ?></span></div>
                            <?php endif; ?>
                            <div class="detail-row"><span class="detail-label">Requested At:</span><span><?php echo date('j M Y, g:i A', strtotime($c['claimed_at'])); ?></span></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeProfileModal()">&times;</span>
            <h2>User Profile</h2>
            <form method="POST" id="profileForm">
            <div class="form-group" style="padding-top: 10px;">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($userData['username']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Household Size</label>
                <input type="number" name="household_size" value="<?php echo htmlspecialchars($household_size); ?>" min="1" readonly>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" readonly><?php echo htmlspecialchars($address); ?></textarea>
            </div>
            <div class="actions">
                <button type="button" id="editProfileBtn" class="btn btn-warning">Edit Profile</button>
                <button type="submit" name="update_profile" id="saveProfileBtn" class="btn btn-primary" style="display:none;">Save Changes</button>
                <button type="button" onclick="closeProfileModal()" class="btn btn-secondary">Close</button>
            </div>
            </form>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeSettingsModal()">&times;</span>
            <h2>Change Password</h2>
            <form method="POST">
            <div class="form-group" style="padding-top: 10px;">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <div class="actions">
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                <button type="button" onclick="closeSettingsModal()" class="btn btn-secondary">Cancel</button>
            </div>
            </form>
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
        });

        // Category selection functionality
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove previous selection
                document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
                
                // Add selection to current card
                this.classList.add('selected');
                
                const category = this.getAttribute('data-category');
                alert(`Selected category: ${category}`);
                // In a real application, you would proceed to the next step here
            });
        });

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
        let modal = document.getElementById("profileModal");
        if (event.target === modal) {
            closeProfileModal();
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

        // Search and Filter Functionality
        let allClaims = [];
        
        // Store all claims data for filtering
        document.addEventListener('DOMContentLoaded', function() {
            // Extract claims data from the page
            const claimCards = document.querySelectorAll('.claim-card');
            allClaims = Array.from(claimCards).map(card => {
                const header = card.querySelector('.card-header');
                const body = card.querySelector('.card-body');
                const statusBadge = header.querySelector('.status-badge');
                
                return {
                    element: card,
                    itemName: header.querySelector('div:first-child').textContent.trim(),
                    status: statusBadge ? statusBadge.textContent.trim().toLowerCase() : '',
                    category: getCategoryFromCard(card),
                    location: getLocationFromCard(body),
                    message: getMessageFromCard(body)
                };
            });
            
            // Add event listeners
            document.getElementById('searchInput').addEventListener('input', filterClaims);
            document.getElementById('categoryFilter').addEventListener('change', filterClaims);
            document.getElementById('statusFilter').addEventListener('change', filterClaims);
            
            // Don't override the PHP-generated category filter on initial load
            // Only update it when refreshing or when explicitly needed
        });

        function getCategoryFromCard(card) {
            return card.getAttribute('data-category') || 'other';
        }

        function getLocationFromCard(body) {
            const locationRow = Array.from(body.querySelectorAll('.detail-row')).find(row => 
                row.querySelector('.detail-label').textContent.includes('Pickup Location')
            );
            return locationRow ? locationRow.querySelector('span:last-child').textContent.trim() : '';
        }

        function getMessageFromCard(body) {
            const messageRow = Array.from(body.querySelectorAll('.detail-row')).find(row => 
                row.querySelector('.detail-label').textContent.includes('Your Message')
            );
            return messageRow ? messageRow.querySelector('span:last-child').textContent.trim() : '';
        }

        function filterClaims() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            let visibleCount = 0;
            
            allClaims.forEach(claim => {
                let show = true;
                
                // Search filter
                if (searchTerm) {
                    const searchableText = [
                        claim.itemName,
                        claim.category,
                        claim.location,
                        claim.message
                    ].join(' ').toLowerCase();
                    
                    if (!searchableText.includes(searchTerm)) {
                        show = false;
                    }
                }
                
                // Category filter
                if (categoryFilter && claim.category !== categoryFilter) {
                    show = false;
                }
                
                // Status filter
                if (statusFilter) {
                    const statusMap = {
                        'pending': 'pending',
                        'approved': 'successful',
                        'rejected': 'rejected'
                    };
                    if (claim.status !== statusMap[statusFilter]) {
                        show = false;
                    }
                }
                
                // Show/hide card
                claim.element.style.display = show ? 'block' : 'none';
                if (show) visibleCount++;
            });
            
            // Update results info
            const totalCount = allClaims.length;
            const resultsInfo = document.getElementById('resultsInfo');
            if (visibleCount === totalCount) {
                resultsInfo.textContent = `Showing all ${totalCount} claims`;
            } else {
                resultsInfo.textContent = `Showing ${visibleCount} of ${totalCount} claims`;
            }
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('categoryFilter').value = '';
            document.getElementById('statusFilter').value = '';
            filterClaims();
        }

        function refreshClaims() {
            // Add loading state
            const refreshBtn = document.querySelector('.btn-refresh');
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
            
            // Simulate refresh (in a real app, this would reload data from server)
            setTimeout(() => {
                refreshBtn.innerHTML = originalText;
                refreshBtn.disabled = false;
                
                // Re-extract claims data
                const claimCards = document.querySelectorAll('.claim-card');
                allClaims = Array.from(claimCards).map(card => {
                    const header = card.querySelector('.card-header');
                    const body = card.querySelector('.card-body');
                    const statusBadge = header.querySelector('.status-badge');
                    
                    return {
                        element: card,
                        itemName: header.querySelector('div:first-child').textContent.trim(),
                        status: statusBadge ? statusBadge.textContent.trim().toLowerCase() : '',
                        category: getCategoryFromCard(card),
                        location: getLocationFromCard(body),
                        message: getMessageFromCard(body)
                    };
                });
                
                // Update category filter to show only categories from current claims
                updateCategoryFilter();
                filterClaims();
            }, 1000);
        }
        
        function updateCategoryFilter() {
            const categoryFilter = document.getElementById('categoryFilter');
            const currentValue = categoryFilter.value;
            
            // Get unique categories from current claims
            const categories = [...new Set(allClaims.map(claim => claim.category).filter(cat => cat && cat.trim() !== ''))];
            
            // Clear existing options except "All Categories"
            categoryFilter.innerHTML = '<option value="">All Categories</option>';
            
            // Only show categories that exist in the current claims
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
            //hi
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
