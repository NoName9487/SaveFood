<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$__uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
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
    <title>SavePlate - Food Inventory Management</title>
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
            padding: 0;
            border-radius: 12px;
            width: 450px;
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
            padding: 25px;
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

        /* Add Inventory Styles */
        .add-inventory-section {
            margin-top: 40px;
            padding: 0 20px;
        }

        .category-selection {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 20px;
            padding: 40px 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .category-selection h3 {
            color: var(--text-dark);
            margin-bottom: 40px;
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            position: relative;
        }

        .category-selection h3::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            border-radius: 2px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .category-card {
            background: white;
            border-radius: 16px;
            padding: 35px 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid #f1f3f4;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border-color: var(--primary);
        }

        .category-card:hover::before {
            transform: scaleX(1);
        }

        .category-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
            display: block;
            transition: all 0.3s ease;
        }

        .category-card:hover i {
            transform: scale(1.1);
            color: var(--primary-dark);
        }

        .category-card span {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.4;
            transition: color 0.3s ease;
        }

        .category-card:hover span {
            color: var(--primary-dark);
        }

        .add-items-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            margin-top: 20px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 3px solid #f0f0f0;
        }

        .section-header h3 {
            color: var(--text-dark);
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .food-selection {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .food-selection h4 {
            color: var(--text-dark);
            margin-bottom: 25px;
            font-size: 1.3rem;
            font-weight: 600;
            text-align: center;
        }

        .food-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .food-item-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid #e9ecef;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            font-weight: 500;
        }

        .food-item-card:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: var(--primary);
        }

        .food-item-card.selected {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .selected-food-display {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 2px solid #c3e6cb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .selected-food-display h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 1.2rem;
            font-weight: 600;
        }

        .add-item-form {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            overflow: hidden;
        }

        .form-header {
            background: #4CAF50;
            padding: 24px 32px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .item-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            font-weight: 500;
        }

        .item-name {
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .change-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 8px 16px;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .change-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .form-content {
            padding: 32px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group label i {
            color: #4CAF50;
            font-size: 0.9rem;
        }

        .form-input {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #374151;
        }

        .form-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .form-hint {
            color: #6b7280;
            font-size: 0.8rem;
            margin-top: 4px;
        }

        .form-footer {
            background: #f9fafb;
            padding: 20px 32px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 1px solid #e5e7eb;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }

        /* Success Popup Styles */
        .success-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .success-popup-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .success-popup-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .success-popup-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 15px;
        }

        .success-popup-message {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .success-popup-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .success-popup-actions .btn {
            padding: 12px 25px;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .add-inventory-section {
                padding: 0 10px;
                margin-top: 20px;
            }

            .category-selection {
                padding: 25px 20px;
                margin-bottom: 20px;
            }

            .category-selection h3 {
                font-size: 1.5rem;
                margin-bottom: 30px;
            }

            .category-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 15px;
            }

            .category-card {
                padding: 25px 15px;
            }

            .category-card i {
                font-size: 2.5rem;
                margin-bottom: 15px;
            }

            .category-card span {
                font-size: 1rem;
            }

            .add-items-section {
                padding: 25px 20px;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .section-header h3 {
                font-size: 1.4rem;
            }

            .food-selection {
                padding: 20px;
            }

            .food-items-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 15px;
            }

            .food-item-card {
                padding: 15px 10px;
                font-size: 0.9rem;
            }

            .selected-food-display {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 20px;
            }

            .add-item-form {
                padding: 25px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-actions {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 16px 24px;
            }

            .success-popup-actions {
                flex-direction: column;
                gap: 15px;
            }

            .success-popup-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .category-card {
                padding: 20px 10px;
            }

            .category-card i {
                font-size: 2rem;
                margin-bottom: 10px;
            }

            .category-card span {
                font-size: 0.9rem;
            }

            .food-items-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .food-item-card {
                padding: 12px 8px;
                font-size: 0.85rem;
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
                <a href="/bit216_assignment/claimed_donation.php" class="nav-link <?php echo $current_page == 'available_donation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span class="label">Claimed Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/meal_plan1.php" class="nav-link <?php echo $current_page == 'meal_plan1.php' && isset($_GET['section']) && $_GET['section'] == 'stats' ? 'active' : ''; ?>">
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
                <h1 class="page-title">Food Inventory Management</h1>
                <p class="page-subtitle">Manage your food items and reduce waste</p>
            </div>

            <!-- Add Inventory Section -->
            <div id="add-inventory-section" class="add-inventory-section">
                <!-- Step 1: Category Selection -->
                <div id="category-selection" class="category-selection">
                    <h3><i class="fas fa-layer-group"></i> Step 1: Select Food Category</h3>
                    <div class="category-grid">
                        <div class="category-card" data-category="Fruits & Vegetables">
                            <i class="fas fa-apple-alt"></i>
                            <span>Fruits & Vegetables</span>
                        </div>
                        <div class="category-card" data-category="Dairy & Eggs">
                            <i class="fas fa-egg"></i>
                            <span>Dairy & Eggs</span>
                        </div>
                        <div class="category-card" data-category="Meat & Fish">
                            <i class="fas fa-drumstick-bite"></i>
                            <span>Meat & Fish</span>
                        </div>
                        <div class="category-card" data-category="Grains & Cereals">
                            <i class="fas fa-bread-slice"></i>
                            <span>Grains & Cereals</span>
                        </div>
                        <div class="category-card" data-category="Canned Goods">
                            <i class="fas fa-box"></i>
                            <span>Canned Goods</span>
                        </div>
                        <div class="category-card" data-category="Beverages">
                            <i class="fas fa-wine-bottle"></i>
                            <span>Beverages</span>
                        </div>
                        <div class="category-card" data-category="Snacks">
                            <i class="fas fa-cookie-bite"></i>
                            <span>Snacks</span>
                        </div>
                        <div class="category-card" data-category="Condiments">
                            <i class="fas fa-mortar-pestle"></i>
                            <span>Condiments</span>
                        </div>
                        <div class="category-card" data-category="Frozen Foods">
                            <i class="fas fa-snowflake"></i>
                            <span>Frozen Foods</span>
                        </div>
                        <div class="category-card" data-category="Other">
                            <i class="fas fa-ellipsis-h"></i>
                            <span>Other</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Add Items to Selected Category -->
                <div id="add-items-section" class="add-items-section" style="display: none;">
                    <div class="section-header">
                        <button id="back-to-categories" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Categories
                        </button>
                        <h3><i class="fas fa-plus"></i> Step 2: Add Items to <span id="selected-category-name"></span></h3>
                    </div>
                    
                    <!-- Step 2a: Food Item Selection -->
                    <div id="food-selection" class="food-selection">
                        <h4><i class="fas fa-utensils"></i> Select Food Item</h4>
                        <div id="food-items-grid" class="food-items-grid">
                            <!-- Food items will be populated here based on category -->
                        </div>
                    </div>

                    <!-- Step 2b: Add Item Form -->
                    <div id="add-item-form" class="add-item-form" style="display: none;">
                        <!-- Clean Header -->
                        <div class="form-header">
                            <div class="header-content">
                                <div class="item-info">
                                    <span class="item-label">Adding:</span>
                                    <span class="item-name" id="selected-food-name"></span>
                                </div>
                                <button id="change-food" class="change-btn">
                                    <i class="fas fa-edit"></i>
                                    <span>Change</span>
                                </button>
                            </div>
                        </div>
                        
                        <form id="addItemForm">
                            <input type="hidden" id="selected_category" name="category">
                            <input type="hidden" id="selected_food_item" name="item_name">
                            
                            <!-- Form Fields -->
                            <div class="form-content">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="quantity">
                                            <i class="fas fa-weight"></i>
                                            <span>Quantity *</span>
                                        </label>
                                        <input type="text" id="quantity" name="quantity" class="form-input" placeholder="e.g., 2 cans, 500g, 1 dozen" required>
                                        <small class="form-hint" id="quantity-hint">Must start with a number</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="expiry_date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Expiry Date *</span>
                                        </label>
                                        <input type="date" id="expiry_date" name="expiry_date" class="form-input" required>
                                        <small class="form-hint" id="expiry-hint">Select when this item will expire</small>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="storage_location">
                                            <i class="fas fa-box"></i>
                                            <span>Storage Location *</span>
                                        </label>
                                        <select id="storage_location" name="storage_location" class="form-input" required oninvalid="this.setCustomValidity('Please select a storage location')" oninput="this.setCustomValidity('')">
                                            <option value="">Select Location</option>
                                            <option value="Refrigerator">Refrigerator</option>
                                            <option value="Freezer">Freezer</option>
                                            <option value="Pantry">Pantry</option>
                                            <option value="Kitchen Cabinet">Kitchen Cabinet</option>
                                            <option value="Basement">Basement</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="notes">
                                            <i class="fas fa-sticky-note"></i>
                                            <span>Notes</span>
                                        </label>
                                        <textarea id="notes" name="notes" class="form-input" rows="3" placeholder="Any additional notes about this item..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-footer">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-plus"></i>
                                    <span>Add Item</span>
                                </button>
                                <button type="button" id="cancel-add" class="btn-secondary">
                                    <i class="fas fa-times"></i>
                                    <span>Cancel</span>
                                </button>
                            </div>
                        </form>
                    </div>
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

    <!-- Success Popup -->
    <div id="success-popup" class="success-popup" style="display: none;">
        <div class="success-popup-content">
            <div class="success-popup-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="success-popup-title">Item Added Successfully!</div>
            <div class="success-popup-message">
                Your food item has been added to the inventory. What would you like to do next?
            </div>
            <div class="success-popup-actions">
                <button id="view-inventory-btn" class="btn btn-primary">
                    <i class="fas fa-archive"></i> View Inventory
                </button>
                <button id="add-another-btn" class="btn btn-secondary">
                    <i class="fas fa-plus"></i> Add Another Item
                </button>
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
            
            // Load unread count for notif dot (shared with notification page)
            function updateSidebarNotificationCount(){
                try{
                    var formData = new FormData();
                    formData.append('action','get_unread_count');
                    formData.append('user_id', <?php echo (int)($_SESSION['user_id'] ?? 0); ?>);
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
                showFoodSelection(category);
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

        // Add Inventory JavaScript Functions
        const foodItems = {
            'Fruits & Vegetables': [
                'Apples', 'Bananas', 'Oranges', 'Grapes', 'Strawberries', 'Blueberries', 'Raspberries',
                'Carrots', 'Broccoli', 'Spinach', 'Lettuce', 'Tomatoes', 'Cucumbers', 'Bell Peppers',
                'Onions', 'Potatoes', 'Sweet Potatoes', 'Avocados', 'Lemons', 'Limes'
            ],
            'Dairy & Eggs': [
                'Milk', 'Cheese', 'Yogurt', 'Butter', 'Cream', 'Eggs', 'Cottage Cheese',
                'Sour Cream', 'Cream Cheese', 'Mozzarella', 'Cheddar', 'Parmesan'
            ],
            'Meat & Fish': [
                'Chicken Breast', 'Ground Beef', 'Salmon', 'Tuna', 'Pork Chops', 'Turkey',
                'Bacon', 'Sausage', 'Shrimp', 'Crab', 'Lobster', 'Beef Steak'
            ],
            'Grains & Cereals': [
                'Rice', 'Pasta', 'Bread', 'Oats', 'Quinoa', 'Barley', 'Wheat Flour',
                'Cornmeal', 'Cereal', 'Granola', 'Crackers', 'Tortillas'
            ],
            'Canned Goods': [
                'Canned Tomatoes', 'Canned Beans', 'Canned Corn', 'Canned Soup', 'Canned Tuna',
                'Canned Vegetables', 'Canned Fruit', 'Pasta Sauce', 'Coconut Milk'
            ],
            'Beverages': [
                'Water', 'Juice', 'Soda', 'Coffee', 'Tea', 'Energy Drinks', 'Sports Drinks',
                'Wine', 'Beer', 'Sparkling Water', 'Milk', 'Almond Milk'
            ],
            'Snacks': [
                'Chips', 'Nuts', 'Crackers', 'Cookies', 'Candy', 'Popcorn', 'Trail Mix',
                'Granola Bars', 'Pretzels', 'Dried Fruit', 'Jerky'
            ],
            'Condiments': [
                'Ketchup', 'Mustard', 'Mayonnaise', 'Hot Sauce', 'Soy Sauce', 'Vinegar',
                'Olive Oil', 'Salt', 'Pepper', 'Herbs', 'Spices', 'Garlic', 'Ginger'
            ],
            'Frozen Foods': [
                'Frozen Vegetables', 'Frozen Fruit', 'Ice Cream', 'Frozen Pizza', 'Frozen Meals',
                'Frozen Chicken', 'Frozen Fish', 'Frozen Berries', 'Frozen Yogurt'
            ],
            'Other': [
                'Honey', 'Jam', 'Peanut Butter', 'Almond Butter', 'Coconut Oil', 'Baking Soda',
                'Baking Powder', 'Vanilla Extract', 'Chocolate Chips', 'Coconut Flakes'
            ]
        };

        // Category selection
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', function() {
                const category = this.dataset.category;
                showFoodSelection(category);
            });
        });

        // Back to categories
        document.getElementById('back-to-categories').addEventListener('click', function() {
            showCategorySelection();
        });

        // Change food item
        document.getElementById('change-food').addEventListener('click', function() {
            const category = document.getElementById('selected_category').value;
            showFoodSelection(category);
        });

        // Cancel add
        document.getElementById('cancel-add').addEventListener('click', function() {
            showCategorySelection();
        });

        function showCategorySelection() {
            document.getElementById('category-selection').style.display = 'block';
            document.getElementById('add-items-section').style.display = 'none';
            document.getElementById('addItemForm').reset();
        }

        function showFoodSelection(category) {
            document.getElementById('category-selection').style.display = 'none';
            document.getElementById('add-items-section').style.display = 'block';
            document.getElementById('food-selection').style.display = 'block';
            document.getElementById('add-item-form').style.display = 'none';
            
            document.getElementById('selected-category-name').textContent = category;
            document.getElementById('selected_category').value = category;
            
            // Populate food items
            const foodGrid = document.getElementById('food-items-grid');
            const items = foodItems[category] || [];
            
            foodGrid.innerHTML = items.map(item => `
                <div class="food-item-card" data-item="${item}">
                    ${item}
                </div>
            `).join('');
            
            // Add click listeners to food items
            document.querySelectorAll('.food-item-card').forEach(card => {
                card.addEventListener('click', function() {
                    const item = this.dataset.item;
                    selectFoodItem(item);
                });
            });
        }

        function selectFoodItem(item) {
            document.getElementById('selected_food_item').value = item;
            document.getElementById('selected-food-name').textContent = item;
            
            document.getElementById('food-selection').style.display = 'none';
            document.getElementById('add-item-form').style.display = 'block';
            
            // Reset form but keep selected values
            document.getElementById('addItemForm').reset();
            document.getElementById('selected_category').value = document.getElementById('selected-category-name').textContent;
            document.getElementById('selected_food_item').value = item;
        }

        // Set minimum date to today for expiry date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('expiry_date').setAttribute('min', today);
        });

        // Real-time quantity validation
        document.getElementById('quantity').addEventListener('input', function() {
            const quantity = this.value.trim();
            const hint = document.getElementById('quantity-hint');
            
            if (quantity === '') {
                hint.textContent = 'Must start with a number';
                hint.style.color = '#6b7280';
            } else if (/^\d+/.test(quantity)) {
                hint.textContent = '✓ Valid quantity format';
                hint.style.color = '#10b981';
            } else {
                hint.textContent = '❌ Must start with a number (e.g., 1 can, 2 bottles)';
                hint.style.color = '#ef4444';
            }
        });

        // Real-time expiry date validation
        document.getElementById('expiry_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to start of day for accurate comparison
            const hint = document.getElementById('expiry-hint');
            
            if (this.value === '') {
                hint.textContent = 'Select when this item will expire';
                hint.style.color = '#6b7280';
            } else if (selectedDate < today) {
                hint.textContent = '❌ Expiry date cannot be in the past';
                hint.style.color = '#ef4444';
            } else {
                hint.textContent = '✓ Valid expiry date';
                hint.style.color = '#10b981';
            }
        });

        // Add item form submission
        document.getElementById('addItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Client-side validation for quantity
            const quantity = document.getElementById('quantity').value.trim();
            if (!/^\d+/.test(quantity)) {
                alert('Quantity must start with a number (e.g., "1 can", "2 bottles", "500g")');
                return;
            }
            
            // Client-side validation for expiry date
            const expiryDate = document.getElementById('expiry_date').value;
            if (expiryDate) {
                const selectedDate = new Date(expiryDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0); // Reset time to start of day for accurate comparison
                
                if (selectedDate < today) {
                    alert('❌ Error: Expiry date cannot be in the past. Please select today or a future date.');
                    document.getElementById('expiry_date').focus();
                    return;
                }
            }
            
            const formData = new FormData(this);
            formData.append('action', 'add_item');

            fetch('food_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success popup instead of alert
                    showSuccessPopup();
                    // Reset form but keep the category and food item
                    const category = document.getElementById('selected_category').value;
                    const foodItem = document.getElementById('selected_food_item').value;
                    this.reset();
                    document.getElementById('selected_category').value = category;
                    document.getElementById('selected_food_item').value = foodItem;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });

        // Success popup functions
        function showSuccessPopup() {
            document.getElementById('success-popup').style.display = 'flex';
        }

        function hideSuccessPopup() {
            document.getElementById('success-popup').style.display = 'none';
        }

        // Success popup button event listeners
        document.getElementById('view-inventory-btn').addEventListener('click', function() {
            hideSuccessPopup();
            // Navigate to inventory page
            window.location.href = 'view_inventory.php';
        });

        document.getElementById('add-another-btn').addEventListener('click', function() {
            hideSuccessPopup();
            // Go back to food selection for the same category
            const category = document.getElementById('selected_category').value;
            showFoodSelection(category);
        });

        // Close success popup when clicking outside
        document.getElementById('success-popup').addEventListener('click', function(e) {
            if (e.target === this) {
                hideSuccessPopup();
            }
        });
    </script>
</body>
</html>
